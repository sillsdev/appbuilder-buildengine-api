# support-upload

Test script for `POST /support/{id}/token`. It requests a read-write support token from BuildEngine,
zips a folder, and uploads the zip to the S3 location in the response's `Url`.

`ORIGIN` and `API_ACCESS_TOKEN` are read from the nearest `.env` in the current or a parent
directory (or from `-env PATH`). `API_ACCESS_TOKEN` is not needed if `-token` is given.

```sh
cd tools/support-upload
go run . [-uuid ID] [-token TOKEN] [-env PATH] [-name NAME] FOLDER
```

- `FOLDER` - folder to zip and upload; the object is named `<folder name>.zip`
- `-uuid` - support request id; a random one is generated if omitted
- `-token` - bearer token for the API (default: `API_ACCESS_TOKEN` from `.env`). Use a client's
  `access_token` to upload as that client; the object then goes under `<client prefix>/<uuid>`.
  The master `API_ACCESS_TOKEN` has no client, so its uploads go under `<uuid>` only.
- `-env` - path to the `.env` file
- `-name` - token holder name sent to the API (default `support-upload-test`)

The support id used is printed to stdout, so it can be captured:

```sh
ID=$(go run . ~/Desktop/MyProject)
```
