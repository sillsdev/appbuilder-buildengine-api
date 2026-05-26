/* eslint-disable @typescript-eslint/no-namespace */
import type { RepeatOptions } from 'bullmq';
import type { BuildForPrefix, ReleaseForPrefix } from '../models/artifacts';

/** Retry a job for 72 hours every 10 minutes. Useful for build engine tasks */
export const Retry0f600 = {
  attempts: (72 * 60) / 10,
  backoff: {
    type: 'fixed',
    delay: 600000 // 10 minute
  }
} as const;

/** Repeat a job every minute */
export const RepeatEveryMinute: RepeatOptions = {
  pattern: '*/1 * * * *' // every minute
} as const;

export enum QueueName {
  Builds = 'Builds',
  S3 = 'S3',
  Releases = 'Releases',
  Grading = 'Grading',
  Polling = 'Polling',
  System_Startup = 'System (Startup)',
  System_Recurring = 'System (Recurring)'
}

export enum JobType {
  // Build Jobs
  Build_Product = 'Build Product',
  Build_Cancel = 'Cancel Build',
  // Polling Jobs
  Poll_Build = 'Check Product Build',
  Poll_Release = 'Check Product Release',
  // Publishing Jobs
  Release_Product = 'Release Product',
  Release_Cancel = 'Cancel Release',
  // Grading Jobs
  Grading_Generate = 'Generate Grading Report',
  // S3 Jobs
  S3_CopyArtifacts = 'Copy Artifacts to S3',
  S3_CopyError = 'Copy Errors to S3',
  // System Jobs
  System_CreateCodeBuildProject = 'Create CodeBuild Project',
  System_RefreshAppVersions = 'Refresh AppVersions'
}

export enum JobSchedulerId {
  RefreshAppVersions = 'RefreshAppVersions'
}

export namespace Build {
  export interface Product {
    type: JobType.Build_Product;
    buildId: number;
  }

  export interface Cancel {
    type: JobType.Build_Cancel;
    guid: string;
    build: BuildForPrefix;
  }
}

export namespace Polling {
  export interface Build {
    type: JobType.Poll_Build;
    buildId: number;
  }

  export interface Release {
    type: JobType.Poll_Release;
    releaseId: number;
  }
}

export namespace Release {
  export interface Product {
    type: JobType.Release_Product;
    releaseId: number;
  }

  export interface Cancel {
    type: JobType.Release_Cancel;
    guid: string;
    release: ReleaseForPrefix;
  }
}

export namespace Grading {
  export interface Generate {
    type: JobType.Grading_Generate;
    gradingResultId: number;
  }
}

export namespace S3 {
  export interface CopyArtifacts {
    type: JobType.S3_CopyArtifacts;
    scope: 'build' | 'release';
    id: number;
  }
  export interface CopyErrors {
    type: JobType.S3_CopyError;
    scope: 'build' | 'release';
    id: number;
  }
}

export namespace System {
  export interface CreateCodeBuildProject {
    type: JobType.System_CreateCodeBuildProject;
  }
  export interface RefreshAppVersions {
    type: JobType.System_RefreshAppVersions;
  }
}

export type Job = JobTypeMap[keyof JobTypeMap];

export type BuildJob = JobTypeMap[JobType.Build_Product | JobType.Build_Cancel];
export type S3Job = JobTypeMap[JobType.S3_CopyArtifacts | JobType.S3_CopyError];
export type PublishJob = JobTypeMap[JobType.Release_Product | JobType.Release_Cancel];
export type GradingJob = JobTypeMap[JobType.Grading_Generate];
export type PollJob = JobTypeMap[JobType.Poll_Build | JobType.Poll_Release];
export type StartupJob = JobTypeMap[
  | JobType.System_CreateCodeBuildProject
  | JobType.System_RefreshAppVersions];
export type RecurringJob = JobTypeMap[JobType.System_RefreshAppVersions];

export type JobTypeMap = {
  [JobType.Build_Product]: Build.Product;
  [JobType.Build_Cancel]: Build.Cancel;
  [JobType.Poll_Build]: Polling.Build;
  [JobType.Poll_Release]: Polling.Release;
  [JobType.Release_Product]: Release.Product;
  [JobType.Release_Cancel]: Release.Cancel;
  [JobType.Grading_Generate]: Grading.Generate;
  [JobType.S3_CopyArtifacts]: S3.CopyArtifacts;
  [JobType.S3_CopyError]: S3.CopyErrors;
  [JobType.System_CreateCodeBuildProject]: System.CreateCodeBuildProject;
  [JobType.System_RefreshAppVersions]: System.RefreshAppVersions;
  // Add more mappings here as needed
};
