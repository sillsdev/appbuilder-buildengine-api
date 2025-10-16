// eslint-disable-next-line @typescript-eslint/no-namespace
export namespace Release {
  export enum Status {
    Initialized = 'initialized',
    Accepted = 'accepted',
    Active = 'active',
    Expired = 'expired',
    Completed = 'completed',
    PostProcessing = 'postprocessing'
  }

  export enum Artifact {
    CloudWatch = 'cloudWatch',
    ConsoleText = 'consoleText',
    PublishUrl = 'publishUrl'
  }
}
