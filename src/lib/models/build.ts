import { basename, extname } from 'node:path';

// eslint-disable-next-line @typescript-eslint/no-namespace
export namespace Build {
  export enum Status {
    Initialized = 'initialized',
    Accepted = 'accepted',
    Active = 'active',
    Expired = 'expired',
    PostProcessing = 'postprocessing',
    Completed = 'completed'
  }

  export enum Result {
    Success = 'SUCCESS',
    Failure = 'FAILURE',
    Aborted = 'ABORTED'
  }

  export enum Channel {
    Unpublished = 'unpublished',
    Alpha = 'alpha',
    Beta = 'beta',
    Production = 'production'
  }

  export enum Artifact {
    Unknown = 'unknown',
    AAB = 'aab',
    APK = 'apk',
    VersionCode = 'version_code',
    Version = 'version',
    About = 'about',
    PlayListing = 'play-listing',
    PlayListingManifest = 'play-listing-manifest',
    PackageName = 'package_name',
    PublishProperties = 'publish_properties',
    CloudWatch = 'cloudWatch',
    ConsoleText = 'consoleText',
    WhatsNew = 'whats_new',
    HTML = 'html',
    PWA = 'pwa',
    EncryptedKey = 'encrypted_key',
    AssetPackage = 'asset-package',
    AssetPreview = 'asset-preview',
    AssetNotify = 'asset-notify',
    DataSafetyCsv = 'data-safety-csv'
  }

  export function artifactType(key: string): [Artifact, string] {
    const ext = extname(key);
    let file = basename(key);
    let type = Artifact.Unknown;
    if (file === 'cloudWatch') {
      type = Artifact.CloudWatch;
    } else if (ext === 'log') {
      type = Artifact.ConsoleText;
    } else if (ext === 'aab') {
      type = Artifact.AAB;
    } else if (ext === 'apk') {
      type = Artifact.APK;
    } else if (file === 'version_code.txt') {
      type = Artifact.VersionCode;
    } else if (file === 'version.json') {
      type = Artifact.Version;
    } else if (file === 'package_name.txt') {
      type = Artifact.PackageName;
    } else if (file === 'publish-properties.json') {
      type = Artifact.PublishProperties;
    } else if (file === 'about.txt') {
      type = Artifact.About;
    } else if (file === 'whats_new.txt') {
      type = Artifact.WhatsNew;
    } else if (file === 'html.zip') {
      type = Artifact.HTML;
    } else if (file === 'pwa.zip') {
      type = Artifact.PWA;
    } else if (file === 'private_key.pepk') {
      type = Artifact.EncryptedKey;
    } else if (key.match(/asset-package\/.*\.zip/)) {
      type = Artifact.AssetPackage;
      file = 'asset-package/' + file;
    } else if (key.match(/asset-package\/preview\.html/)) {
      type = Artifact.AssetPreview;
      file = 'asset-package/preview.html';
    } else if (key.match(/asset-package\/notify\.json/)) {
      type = Artifact.AssetNotify;
      file = 'asset-package/notify.json';
    } else if (key.match(/play-listing\/index\.html/)) {
      type = Artifact.PlayListing;
      file = 'play-listing/index.html';
    } else if (key.match(/play-listing\/manifest.json/)) {
      type = Artifact.PlayListingManifest;
      file = 'play-listing/manifest.json';
    } else if (key.match(/data_safety\.csv/)) {
      type = Artifact.DataSafetyCsv;
    }

    return [type, file];
  }
}
