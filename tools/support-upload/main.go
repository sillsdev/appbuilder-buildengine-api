// Command support-upload is a test harness for POST /support/{id}/token.
//
// It reads ORIGIN and API_ACCESS_TOKEN from a .env file, requests a read-write
// support token from BuildEngine (authenticating with -token if given, so the
// request can be made as a specific client), zips the given folder, and uploads the zip
// to the S3 location returned by the API. The support id used is printed to
// stdout; all other output goes to stderr.
//
// Usage:
//
//	go run . [-uuid ID] [-token TOKEN] [-env PATH] [-name NAME] FOLDER
package main

import (
	"archive/zip"
	"bufio"
	"bytes"
	"context"
	"crypto/rand"
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"io/fs"
	"log"
	"net/http"
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"time"

	"github.com/aws/aws-sdk-go-v2/aws"
	"github.com/aws/aws-sdk-go-v2/credentials"
	"github.com/aws/aws-sdk-go-v2/feature/s3/manager"
	"github.com/aws/aws-sdk-go-v2/service/s3"
)

var uuidRegex = regexp.MustCompile(`(?i)^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$`)

type tokenResponse struct {
	AccessKeyId     string
	SecretAccessKey string
	SessionToken    string
	Expiration      string
	Region          string
	ReadOnly        bool
	Url             string
}

func main() {
	log.SetFlags(0)
	log.SetPrefix("support-upload: ")

	id := flag.String("uuid", "", "support request id (generated if omitted)")
	envPath := flag.String("env", "", "path to .env file (default: nearest .env in current or parent directories)")
	name := flag.String("name", "support-upload-test", "token holder name sent to the API")
	bearer := flag.String("token", "", "bearer token for the API, e.g. a client's access_token (default: API_ACCESS_TOKEN from .env)")
	flag.Usage = func() {
		fmt.Fprintf(flag.CommandLine.Output(), "Usage: %s [flags] FOLDER\n", os.Args[0])
		flag.PrintDefaults()
	}
	flag.Parse()

	if flag.NArg() != 1 {
		flag.Usage()
		os.Exit(2)
	}
	folder := flag.Arg(0)
	if info, err := os.Stat(folder); err != nil || !info.IsDir() {
		log.Fatalf("%q is not a folder", folder)
	}

	if *id == "" {
		*id = newUUID()
	} else if !uuidRegex.MatchString(*id) {
		log.Fatalf("%q is not a valid uuid", *id)
	}

	if *envPath == "" {
		p, err := findEnvFile()
		if err != nil {
			log.Fatal(err)
		}
		*envPath = p
	}
	env, err := loadEnv(*envPath)
	if err != nil {
		log.Fatalf("reading %s: %v", *envPath, err)
	}
	origin := strings.TrimRight(env["ORIGIN"], "/")
	if origin == "" {
		log.Fatalf("ORIGIN must be set in %s", *envPath)
	}
	apiToken := *bearer
	if apiToken == "" {
		apiToken = env["API_ACCESS_TOKEN"]
	}
	if apiToken == "" {
		log.Fatalf("use -token or set API_ACCESS_TOKEN in %s", *envPath)
	}

	ctx := context.Background()

	log.Printf("requesting token for %s from %s", *id, origin)
	token, err := requestToken(ctx, origin, apiToken, *id, *name)
	if err != nil {
		log.Fatal(err)
	}
	log.Printf("token for %s (region %s, expires %s)", token.Url, token.Region, token.Expiration)

	zipPath, err := zipFolder(folder)
	if err != nil {
		log.Fatalf("zipping %s: %v", folder, err)
	}
	defer os.Remove(zipPath)

	bucket, prefix, err := parseS3Url(token.Url)
	if err != nil {
		log.Fatal(err)
	}
	key := prefix + "/" + filepath.Base(filepath.Clean(folder)) + ".zip"

	if err := upload(ctx, token, zipPath, bucket, key); err != nil {
		log.Fatalf("uploading to s3://%s/%s: %v", bucket, key, err)
	}
	log.Printf("uploaded s3://%s/%s", bucket, key)

	fmt.Println(*id)
}

// newUUID returns a random (version 4) UUID.
func newUUID() string {
	var b [16]byte
	if _, err := rand.Read(b[:]); err != nil {
		log.Fatal(err)
	}
	b[6] = (b[6] & 0x0f) | 0x40
	b[8] = (b[8] & 0x3f) | 0x80
	return fmt.Sprintf("%x-%x-%x-%x-%x", b[0:4], b[4:6], b[6:8], b[8:10], b[10:16])
}

// findEnvFile looks for .env in the current directory and its parents.
func findEnvFile() (string, error) {
	dir, err := os.Getwd()
	if err != nil {
		return "", err
	}
	for {
		p := filepath.Join(dir, ".env")
		if _, err := os.Stat(p); err == nil {
			return p, nil
		}
		parent := filepath.Dir(dir)
		if parent == dir {
			return "", errors.New("no .env file found; use -env")
		}
		dir = parent
	}
}

// loadEnv parses simple KEY=VALUE lines, ignoring comments and blank lines.
func loadEnv(path string) (map[string]string, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	env := map[string]string{}
	scanner := bufio.NewScanner(f)
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		line = strings.TrimPrefix(line, "export ")
		k, v, ok := strings.Cut(line, "=")
		if !ok {
			continue
		}
		v = strings.TrimSpace(v)
		if len(v) >= 2 && (v[0] == '"' || v[0] == '\'') && v[len(v)-1] == v[0] {
			v = v[1 : len(v)-1]
		}
		env[strings.TrimSpace(k)] = v
	}
	return env, scanner.Err()
}

func requestToken(ctx context.Context, origin, apiToken, id, name string) (*tokenResponse, error) {
	body, err := json.Marshal(map[string]any{"name": name, "ReadOnly": false})
	if err != nil {
		return nil, err
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodPost, origin+"/support/"+id+"/token", bytes.NewReader(body))
	if err != nil {
		return nil, err
	}
	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("Authorization", "Bearer "+apiToken)

	client := &http.Client{Timeout: 30 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return nil, fmt.Errorf("requesting token: %w", err)
	}
	defer resp.Body.Close()

	data, err := io.ReadAll(resp.Body)
	if err != nil {
		return nil, err
	}
	if resp.StatusCode != http.StatusOK {
		return nil, fmt.Errorf("token request failed: %s: %s", resp.Status, data)
	}

	var token tokenResponse
	if err := json.Unmarshal(data, &token); err != nil {
		return nil, fmt.Errorf("decoding token response: %w", err)
	}
	if token.AccessKeyId == "" || token.Url == "" {
		return nil, fmt.Errorf("token response missing credentials or Url: %s", data)
	}
	return &token, nil
}

// zipFolder writes the contents of folder to a temporary zip file and returns its path.
func zipFolder(folder string) (string, error) {
	tmp, err := os.CreateTemp("", "support-upload-*.zip")
	if err != nil {
		return "", err
	}
	defer tmp.Close()

	zw := zip.NewWriter(tmp)
	err = filepath.WalkDir(folder, func(path string, d fs.DirEntry, err error) error {
		if err != nil {
			return err
		}
		rel, err := filepath.Rel(folder, path)
		if err != nil || rel == "." {
			return err
		}
		info, err := d.Info()
		if err != nil {
			return err
		}
		header, err := zip.FileInfoHeader(info)
		if err != nil {
			return err
		}
		header.Name = filepath.ToSlash(rel)
		if d.IsDir() {
			header.Name += "/"
			_, err = zw.CreateHeader(header)
			return err
		}
		if !info.Mode().IsRegular() {
			log.Printf("skipping %s (not a regular file)", rel)
			return nil
		}
		header.Method = zip.Deflate
		w, err := zw.CreateHeader(header)
		if err != nil {
			return err
		}
		f, err := os.Open(path)
		if err != nil {
			return err
		}
		defer f.Close()
		_, err = io.Copy(w, f)
		return err
	})
	if err == nil {
		err = zw.Close()
	}
	if err != nil {
		os.Remove(tmp.Name())
		return "", err
	}
	return tmp.Name(), nil
}

// parseS3Url splits s3://bucket/prefix into its bucket and prefix.
func parseS3Url(url string) (string, string, error) {
	path, ok := strings.CutPrefix(url, "s3://")
	if !ok {
		return "", "", fmt.Errorf("unexpected Url %q", url)
	}
	bucket, prefix, _ := strings.Cut(path, "/")
	if bucket == "" || prefix == "" {
		return "", "", fmt.Errorf("unexpected Url %q", url)
	}
	return bucket, strings.TrimRight(prefix, "/"), nil
}

func upload(ctx context.Context, token *tokenResponse, zipPath, bucket, key string) error {
	f, err := os.Open(zipPath)
	if err != nil {
		return err
	}
	defer f.Close()

	client := s3.New(s3.Options{
		Region:      token.Region,
		Credentials: credentials.NewStaticCredentialsProvider(token.AccessKeyId, token.SecretAccessKey, token.SessionToken),
	})
	_, err = manager.NewUploader(client).Upload(ctx, &s3.PutObjectInput{
		Bucket:      aws.String(bucket),
		Key:         aws.String(key),
		Body:        f,
		ContentType: aws.String("application/zip"),
	})
	return err
}
