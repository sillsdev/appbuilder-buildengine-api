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
  Publishing = 'Publishing',
  Polling = 'Polling',
  System_Startup = 'System (Startup)'
}

export enum JobType {
  // Build Jobs
  Build_Product = 'Build Product',
  Build_PostProcess = 'Postprocess Build',
  // Polling Jobs
  Poll_Build = 'Check Product Build',
  Poll_Publish = 'Check Product Publish',
  // Project Jobs
  Project_Create = 'Create Project',
  // Publishing Jobs
  Publish_Product = 'Publish Product',
  Publish_PostProcess = 'Postprocess Publish',
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

  export interface PostProcess {
    type: JobType.Build_PostProcess;
    productId: string;
    productBuildId: number;
    build: unknown;
  }
}

export namespace Polling {
  export interface Build {
    type: JobType.Poll_Build;
    buildId: number;
  }

  export interface Publish {
    type: JobType.Poll_Publish;
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

export namespace Publish {
  export interface Product {
    type: JobType.Publish_Product;
    productId: string;
    defaultChannel: string;
    defaultTargets: string;
    environment: Record<string, string>;
  }

  export interface PostProcess {
    type: JobType.Publish_PostProcess;
    productId: string;
    publicationId: number;
    release: unknown;
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

export type BuildJob = JobTypeMap[JobType.Build_Product | JobType.Build_PostProcess];
export type S3Job = JobTypeMap[JobType.S3_CopyArtifacts | JobType.S3_CopyError];
export type PublishJob = JobTypeMap[JobType.Publish_Product | JobType.Publish_PostProcess];
export type PollJob = JobTypeMap[JobType.Poll_Build | JobType.Poll_Publish];
export type ProjectJob = JobTypeMap[JobType.Project_Create];
export type SystemJob = JobTypeMap[JobType.System_CreateCodeBuildProject];

export type JobTypeMap = {
  [JobType.Build_Product]: Build.Product;
  [JobType.Build_PostProcess]: Build.PostProcess;
  [JobType.Poll_Build]: Polling.Build;
  [JobType.Poll_Publish]: Polling.Publish;
  [JobType.Project_Create]: Project.Create;
  [JobType.Publish_Product]: Publish.Product;
  [JobType.Publish_PostProcess]: Publish.PostProcess;
  [JobType.S3_CopyArtifacts]: S3.CopyArtifacts;
  [JobType.S3_CopyError]: S3.CopyErrors;
  [JobType.System_CreateCodeBuildProject]: System.CreateCodeBuildProject;
  // Add more mappings here as needed
};
