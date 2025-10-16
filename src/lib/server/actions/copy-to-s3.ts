import { readFile } from 'node:fs/promises';
import type { BuildForPrefix } from '../artifacts-provider';
import { S3 } from '../aws/s3';
import { Build } from '../models/build';
import { Release } from '../models/release';
import { prisma } from '../prisma';
import { Utils } from '../utils';

export class CopyToS3Operation {
  private build_id;
  private maxRetries = 50;
  private maxDelay = 30;
  private alertAfter = 5;
  private s3;
  private parms;

  public constructor(id: number, parms: string) {
    this.build_id = id;
    this.parms = parms;
    this.s3 = new S3();
  }
  public async performOperation() {
    console.log(`[${Utils.getPrefix()}] CopyToS3Operation ID: ${this.build_id}`);
    if (this.parms === 'release') {
      const release = await prisma.release.findUnique({ where: { id: this.build_id } });
      if (release) {
        await this.s3.copyS3Folder(release);
        await prisma.release.update({
          where: { id: this.build_id },
          data: { status: Release.Status.Completed }
        });
        await this.s3.removeCodeBuildFolder(release);
      }
    } else {
      const build = await prisma.build.findUnique({ where: { id: this.build_id } });
      if (build) {
        const job = build.job;
        if (job) {
          await this.saveBuild(build);
          await prisma.build.update({
            where: { id: this.build_id },
            data: { status: Build.Status.Completed, result: Build.Result.Success }
          });
          await this.s3.removeCodeBuildFolder(build);
        }
      }
    }
  }
  public getMaximumRetries() {
    return this.maxRetries;
  }
  public getMaximumDelay() {
    return this.maxDelay;
  }
  public getAlertAfterAttemptCount() {
    return this.alertAfter;
  }

  /**
   * @param Build build
   * @param string defaultLanguage
   */
  private async getExtraContent(build: BuildForPrefix, defaultLanguage: string) {
    console.log(`getExtraContent defaultLanguage: ${defaultLanguage}`);
    const manifestFileContent = await this.s3.readS3File(build, 'manifest.txt');
    if (manifestFileContent) {
      const manifestFiles = manifestFileContent.split('\n');
      if (manifestFiles.length > 0) {
        // Copy index.html to destination folder
        const path = './preview/playlisting/index.html';

        const indexContents = (await readFile(path)).toString();
        this.s3.writeFileToS3(indexContents, 'play-listing/index.html', build);
      }
      if (!defaultLanguage) {
        // If defaultLanguage was not found, use first entry with icon
        for (const playListingFile of manifestFiles) {
          const matches = playListingFile.match(/([^/]*)\/images\/icon.png/);
          if (matches) {
            defaultLanguage = matches[1];
            break;
          }
        }
      }

      const playEncodedRelativePaths: string[] = [];
      const languages = new Set<string>();
      let publishIndex = '<html><body><ul>';
      const ignoreFiles = [
        'default-language.txt',
        'primary-color.txt',
        'download-apk-strings.json'
      ];
      for (const path of manifestFiles) {
        if (ignoreFiles.includes(path)) {
          continue;
        }
        if (path) {
          // collect files
          const encodedPath = CopyToS3Operation.encodePath('play-listing/' + path);
          publishIndex += `<li><a href="${encodedPath}">play-listing/${path}</a></p></li>`;
          playEncodedRelativePaths.push(CopyToS3Operation.encodePath(path));

          // collect languages
          const langMatches = path.match(/([^/]*)\//);
          if (langMatches) {
            languages.add(langMatches[1]);
          }
        }
      }
      publishIndex += '</ul></body></html>';
      this.s3.writeFileToS3(publishIndex, 'play-listing.html', build);
      const manifest: Record<string, string | string[] | Record<string, string>> = {
        files: playEncodedRelativePaths,
        languages: Array.from(languages),
        color: await this.getPrimaryColor(build),
        package: await this.getPackageName(build),
        'download-apk-strings': await this.getDownloadApkStrings(build, languages, defaultLanguage),
        url: build.getArtifactUrlBase() + 'play-listing/'
      };
      if (defaultLanguage) {
        manifest['default-language'] = defaultLanguage;
        manifest['icon'] = 'defaultLanguage/images/icon.png';
      }
      const json = JSON.stringify(manifest);
      const jsonFileName = 'play-listing/manifest.json';
      this.s3.writeFileToS3(json, jsonFileName, build);
    }
  }

  private static encodePath(path: string) {
    return encodeURI(path);
  }

  /**
   * getDefaultLanguage reads the default language from default-language.txt
   *
   * @param Build build - Current build object
   * @return string Contents of default-language.txt or empty if file doesn't exist
   */
  private async getDefaultLanguage(build: BuildForPrefix) {
    return await this.s3.readS3File(build, 'play-listing/default-language.txt');
  }

  /**
   * getPrimaryColor read the primary color from primary-color.txt
   *
   * @param Build build - Current build object
   * @return string Contents of primary-color.txt or default value is file doesn't exist
   */
  private async getPrimaryColor(build: BuildForPrefix) {
    return (await this.s3.readS3File(build, 'play-listing/primary-color.txt')).trim() || '#cce2ff';
  }

  /**
   * getDownloadApkStrings read the localization strings in download-apk-strings.json
   *
   * @param Build build - Current build object
   * @param array languages - languages to include
   * @param string defaultLanguage - the default language
   * @return mixed Contents of download-apk-strings.json or default as array
   */
  private async getDownloadApkStrings(
    build: BuildForPrefix,
    languages: Set<string>,
    defaultLanguage: string
  ) {
    const languagesNoVariant = new Set(languages.values().map((lang) => lang.substring(0, 2)));

    const strings =
      (await this.s3.readS3File(build, 'play-listing/download-apk-strings.json')).trim() ||
      JSON.stringify({ en: 'Download APK' });

    const downloadApkStrings = Object.entries(JSON.parse(strings) as Record<string, string>).filter(
      ([lang]) => languagesNoVariant.has(lang)
    );

    return downloadApkStrings.length
      ? Object.fromEntries(downloadApkStrings)
      : { [defaultLanguage]: 'APK' };
  }

  private async getPackageName(build: BuildForPrefix) {
    return (await this.s3.readS3File(build, 'package_name.txt')).trim();
  }

  /**
   * Save the build to S3 +
   * @param Build build
   * @return string
   */
  private async saveBuild(build: BuildForPrefix) {
    this.s3.copyS3Folder(build);
    const defaultLanguage = await this.getDefaultLanguage(build);
    this.getExtraContent(build, defaultLanguage);
  }
}
