import type { Job } from 'bullmq';
import { Worker } from 'bullmq';
import * as Executor from '../job-executors';
import { getQueues, getWorkerConfig } from './queues';
import * as BullMQ from './types';
import { building } from '$app/environment';

export abstract class BullWorker<T extends BullMQ.Job> {
  public worker?: Worker;
  constructor(public queue: BullMQ.QueueName) {
    if (!building)
      // Leaving out the bind here is the type of issue that TS unfortunately cannot catch
      this.worker = new Worker<T>(queue, this.runInternal.bind(this), getWorkerConfig());
  }
  private async runInternal(job: Job<T>) {
    try {
      return await this.run(job);
    } catch (error) {
      console.error(error);
    }
  }
  abstract run(job: Job<T>): Promise<unknown>;
}

export class SystemStartup<J extends BullMQ.SystemJob> extends BullWorker<J> {
  private jobsLeft = 0;
  constructor() {
    super(BullMQ.QueueName.System_Startup);
    const startupJobs = [
      [
        'Create CodeBuild Project (Startup)',
        {
          type: BullMQ.JobType.System_CreateCodeBuildProject
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
      case BullMQ.JobType.Build_PostProcess:
        return Executor.Build.postProcess(job as Job<BullMQ.Build.PostProcess>);
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

export class Projects<J extends BullMQ.ProjectJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Projects);
  }
  async run(job: Job<J>) {
    switch (job.data.type) {
      case BullMQ.JobType.Project_Create:
        return Executor.Project.create(job as Job<BullMQ.Project.Create>);
    }
  }
}

export class Publishing<J extends BullMQ.PublishJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Publishing);
  }
  async run(job: Job<J>) {
    switch (job.data.type) {
      case BullMQ.JobType.Publish_Product:
        return Executor.Publish.product(job as Job<BullMQ.Publish.Product>);
      case BullMQ.JobType.Publish_PostProcess:
        return Executor.Publish.postProcess(job as Job<BullMQ.Publish.PostProcess>);
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
      case BullMQ.JobType.Poll_Publish:
        return Executor.Polling.publish(job as Job<BullMQ.Polling.Publish>);
    }
  }
}
