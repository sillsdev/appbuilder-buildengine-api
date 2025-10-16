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
}
