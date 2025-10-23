import type { Job } from 'bullmq';
import { readFile } from 'node:fs/promises';
import type { BuildForPrefix } from '$lib/models/artifacts';
import { Build } from '$lib/models/build';
import { Release } from '$lib/models/release';
import { S3 } from '$lib/server/aws/s3';
import type { BullMQ } from '$lib/server/bullmq';
import { prisma } from '$lib/server/prisma';

export async function save(job: Job<BullMQ.S3.CopyArtifacts>): Promise<unknown> {
  const id = job.data.id;
  const s3 = new S3();
  if (job.data.scope === 'release') {
    const release = await prisma.release.findUnique({
      where: { id },
      include: { build: { include: { job: true } } }
    });
    if (release) {
      await s3.copyS3Folder(release);
      await prisma.release.update({
        where: { id },
        data: { ...release, status: Release.Status.Completed, build: undefined }
      });
      await s3.removeCodeBuildFolder(release);
    }
  } else {
    const build = await prisma.build.findUnique({
      where: { id },
      include: { job: true }
    });
    if (build) {
      if (build.job) {
        s3.copyS3Folder(build);
        let defaultLanguage = await s3.readS3File(build, 'play-listing/default-language.txt');
        console.log(`getExtraContent defaultLanguage: ${defaultLanguage}`);
        const manifestFileContent = await s3.readS3File(build, 'manifest.txt');
        let manifest: Record<string, string | string[] | Record<string, string>> = {};
        if (manifestFileContent) {
          const manifestFiles = manifestFileContent.split('\n');
          if (manifestFiles.length > 0) {
            // Copy index.html to destination folder
            const path = './preview/playlisting/index.html';

            const indexContents = (await readFile(path)).toString();
            s3.writeFileToS3(indexContents, 'play-listing/index.html', build);
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
              const encodedPath = encodeURI('play-listing/' + path);
              publishIndex += `<li><a href="${encodedPath}">play-listing/${path}</a></p></li>`;
              playEncodedRelativePaths.push(encodeURI(path));

              // collect languages
              const langMatches = path.match(/([^/]*)\//);
              if (langMatches) {
                languages.add(langMatches[1]);
              }
            }
          }
          publishIndex += '</ul></body></html>';
          s3.writeFileToS3(publishIndex, 'play-listing.html', build);
          manifest = {
            files: playEncodedRelativePaths,
            languages: Array.from(languages),
            color:
              (await s3.readS3File(build, 'play-listing/primary-color.txt')).trim() || '#cce2ff',
            package: (await s3.readS3File(build, 'package_name.txt')).trim(),
            'download-apk-strings': await getDownloadApkStrings(
              s3,
              build,
              languages,
              defaultLanguage
            ),
            url: build.artifact_url_base + 'play-listing/'
          };
          if (defaultLanguage) {
            manifest['default-language'] = defaultLanguage;
            manifest['icon'] = 'defaultLanguage/images/icon.png';
          }
          const json = JSON.stringify(manifest);
          const jsonFileName = 'play-listing/manifest.json';
          s3.writeFileToS3(json, jsonFileName, build);
        }
        await prisma.build.update({
          where: { id },
          data: {
            ...build,
            status: Build.Status.Completed,
            result: Build.Result.Success,
            job: undefined
          }
        });
        await s3.removeCodeBuildFolder(build);
        job.updateProgress(100);
        return { manifest };
      }
    }
  }
  job.updateProgress(100);
}

export async function error(job: Job<BullMQ.S3.CopyErrors>): Promise<unknown> {
  const id = job.data.id;
  const s3 = new S3();
  if (job.data.scope === 'release') {
    const release = await prisma.release.findUnique({
      where: { id },
      include: { build: { include: { job: true } } }
    });
    if (release) {
      await s3.copyS3Folder(release);
      await prisma.release.update({
        where: { id },
        data: { status: Release.Status.Completed }
      });
    }
  } else {
    const build = await prisma.build.findUnique({ where: { id }, include: { job: true } });
    if (build) {
      await s3.copyS3Folder(build);
      await prisma.build.update({
        where: { id },
        data: { status: Build.Status.Completed }
      });
    }
  }
  job.updateProgress(100);
  return;
}

/**
 * getDownloadApkStrings read the localization strings in download-apk-strings.json
 *
 * @param S3 s3 - S3 client
 * @param Build build - Current build object
 * @param array languages - languages to include
 * @param string defaultLanguage - the default language
 * @return mixed Contents of download-apk-strings.json or default as array
 */
async function getDownloadApkStrings(
  s3: S3,
  build: BuildForPrefix,
  languages: Set<string>,
  defaultLanguage: string
) {
  const languagesNoVariant = new Set(languages.values().map((lang) => lang.substring(0, 2)));

  const strings =
    (await s3.readS3File(build, 'play-listing/download-apk-strings.json')).trim() ||
    JSON.stringify({ en: 'Download APK' });

  const downloadApkStrings = Object.entries(JSON.parse(strings) as Record<string, string>).filter(
    ([lang]) => languagesNoVariant.has(lang)
  );

  return downloadApkStrings.length
    ? Object.fromEntries(downloadApkStrings)
    : { [defaultLanguage]: 'APK' };
}
