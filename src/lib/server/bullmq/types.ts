/* eslint-disable @typescript-eslint/no-namespace */
import type { RepeatOptions } from 'bullmq';

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
  Projects = 'Projects',
  Releases = 'Releases',
  Polling = 'Polling',
  System_Startup = 'System (Startup)'
}

export enum JobType {
  // Build Jobs
  Build_Product = 'Build Product',
  // Polling Jobs
  Poll_Build = 'Check Product Build',
  Poll_Release = 'Check Product Release',
  // Project Jobs
  Project_Create = 'Create Project',
  // Publishing Jobs
  Release_Product = 'Release Product',
  // S3 Jobs
  S3_CopyArtifacts = 'Copy Artifacts to S3',
  S3_CopyError = 'Copy Errors to S3',
  // System Jobs
  System_CreateCodeBuildProject = 'Create CodeBuild Project'
}

export namespace Build {
  export interface Product {
    type: JobType.Build_Product;
    buildId: number;
  }
}

export namespace Polling {
  export interface Build {
    type: JobType.Poll_Build;
    buildId: number;
  }

  export interface Release {
    type: JobType.Poll_Release;
    organizationId: number;
    productId: string;
    jobId: number;
    buildId: number;
    releaseId: number;
    publicationId: number;
  }
}

export namespace Project {
  export interface Create {
    type: JobType.Project_Create;
    projectId: number;
  }
}

export namespace Release {
  export interface Product {
    type: JobType.Release_Product;
    releaseId: number;
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
}

export type Job = JobTypeMap[keyof JobTypeMap];

export type BuildJob = JobTypeMap[JobType.Build_Product];
export type S3Job = JobTypeMap[JobType.S3_CopyArtifacts | JobType.S3_CopyError];
export type PublishJob = JobTypeMap[JobType.Release_Product];
export type PollJob = JobTypeMap[JobType.Poll_Build | JobType.Poll_Release];
export type ProjectJob = JobTypeMap[JobType.Project_Create];
export type SystemJob = JobTypeMap[JobType.System_CreateCodeBuildProject];

export type JobTypeMap = {
  [JobType.Build_Product]: Build.Product;
  [JobType.Poll_Build]: Polling.Build;
  [JobType.Poll_Release]: Polling.Release;
  [JobType.Project_Create]: Project.Create;
  [JobType.Release_Product]: Release.Product;
  [JobType.S3_CopyArtifacts]: S3.CopyArtifacts;
  [JobType.S3_CopyError]: S3.CopyErrors;
  [JobType.System_CreateCodeBuildProject]: System.CreateCodeBuildProject;
  // Add more mappings here as needed
};
