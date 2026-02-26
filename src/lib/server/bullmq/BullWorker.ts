import { SpanStatusCode, trace } from '@opentelemetry/api';
import type { Exception, Job } from 'bullmq';
import { Worker } from 'bullmq';
import * as Executor from '../job-executors';
import { getQueues, getWorkerConfig } from './queues';
import * as BullMQ from './types';
import { building } from '$app/environment';

const tracer = trace.getTracer('BullWorker');

export abstract class BullWorker<T extends BullMQ.Job> {
  public worker?: Worker;
  constructor(public queue: BullMQ.QueueName) {
    if (!building)
      // Leaving out the bind here is the type of issue that TS unfortunately cannot catch
      this.worker = new Worker<T>(queue, this.runInternal.bind(this), getWorkerConfig());
  }
  private async runInternal(job: Job<T>) {
    return await tracer.startActiveSpan(`${job.queueName} - ${job.data.type}`, async (span) => {
      span.setAttributes({
        'job.id': job.id,
        'job.name': job.name,
        'job.queueName': job.queueName,
        'job.type': job.data.type,
        'job.opts': JSON.stringify(job.opts),
        'job.data': JSON.stringify(job.data)
      });
      try {
        job.updateProgress(0);
        return await this.run(job);
      } catch (error) {
        span.recordException(error as Exception);
        span.setStatus({
          code: SpanStatusCode.ERROR, // Error
          message: (error as Error).message
        });
        console.error(error);
        throw error;
      } finally {
        span.end();
      }
    });
  }
  abstract run(job: Job<T>): Promise<unknown>;
}

export class SystemStartup<J extends BullMQ.StartupJob> extends BullWorker<J> {
  private jobsLeft = 0;
  constructor() {
    super(BullMQ.QueueName.System_Startup);
    const startupJobs = [
      [
        'Create CodeBuild Project (Startup)',
        {
          type: BullMQ.JobType.System_CreateCodeBuildProject
        }
      ],
      [
        'Refresh AppVersions (Startup)',
        {
          type: BullMQ.JobType.System_RefreshAppVersions
        }
      ]
    ] as const;
    startupJobs.forEach(([name, data]) => {
      getQueues().SystemStartup.add(name, data);
    });
    this.jobsLeft = startupJobs.length;
  }
  async run(job: Job<J>) {
    // Close the worker after running the startup jobs
    // This prevents this worker from taking startup jobs when a new instance is started
    // The worker will not actually be closed until all processing jobs are complete
    if (--this.jobsLeft === 0) this.worker?.close();
    switch (job.data.type) {
      case BullMQ.JobType.System_CreateCodeBuildProject:
        return Executor.System.createCodeBuildProject(
          job as Job<BullMQ.System.CreateCodeBuildProject>
        );
      case BullMQ.JobType.System_RefreshAppVersions:
        return Executor.System.refreshAppVersions(job as Job<BullMQ.System.RefreshAppVersions>);
    }
  }
}

export class SystemRecurring<J extends BullMQ.RecurringJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.System_Recurring);
    getQueues().SystemRecurring.upsertJobScheduler(
      BullMQ.JobSchedulerId.RefreshAppVersions,
      {
        pattern: '@hourly',
        immediately: false
      },
      {
        name: 'Refresh AppVersions',
        data: {
          type: BullMQ.JobType.System_RefreshAppVersions
        }
      }
    );
  }
  async run(job: Job<J>) {
    switch (job.data.type) {
      case BullMQ.JobType.System_RefreshAppVersions:
        return Executor.System.refreshAppVersions(job as Job<BullMQ.System.RefreshAppVersions>);
    }
  }
}

export class Builds<J extends BullMQ.BuildJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Builds);
  }
  async run(job: Job<J>) {
    switch (job.data.type) {
      case BullMQ.JobType.Build_Product:
        return Executor.Build.product(job as Job<BullMQ.Build.Product>);
      case BullMQ.JobType.Build_Cancel:
        return Executor.Build.cancel(job as Job<BullMQ.Build.Cancel>);
    }
  }
}

export class S3<J extends BullMQ.S3Job> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.S3);
  }
  async run(job: Job<J>) {
    switch (job.data.type) {
      case BullMQ.JobType.S3_CopyArtifacts:
        return Executor.S3.save(job as Job<BullMQ.S3.CopyArtifacts>);
      case BullMQ.JobType.S3_CopyError:
        return Executor.S3.error(job as Job<BullMQ.S3.CopyErrors>);
    }
  }
}

export class Releases<J extends BullMQ.PublishJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Releases);
  }
  async run(job: Job<J>) {
    switch (job.data.type) {
      case BullMQ.JobType.Release_Product:
        return Executor.Release.product(job as Job<BullMQ.Release.Product>);
      case BullMQ.JobType.Release_Cancel:
        return Executor.Release.cancel(job as Job<BullMQ.Release.Cancel>);
    }
  }
}

export class Polling<J extends BullMQ.PollJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Polling);
  }
  async run(job: Job<J>) {
    switch (job.data.type) {
      case BullMQ.JobType.Poll_Build:
        return Executor.Polling.build(job as Job<BullMQ.Polling.Build>);
      case BullMQ.JobType.Poll_Release:
        return Executor.Polling.release(job as Job<BullMQ.Polling.Release>);
    }
  }
}
