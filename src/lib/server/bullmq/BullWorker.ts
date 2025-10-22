import type { Job } from 'bullmq';
import { Worker } from 'bullmq';
import * as Executor from '../job-executors';
import { getWorkerConfig } from './queues';
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

export class Builds<J extends BullMQ.BuildJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Builds);
  }
  async run(job: Job<J>) {
  }
}

export class S3<J extends BullMQ.S3Job> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.S3);
  }
  async run(job: Job<J>) {
  }
}

export class Projects<J extends BullMQ.ProjectJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Projects);
  }
  async run(job: Job<J>) {
  }
}

export class Publishing<J extends BullMQ.PublishJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Publishing);
  }
  async run(job: Job<J>) {
  }
}

export class Polling<J extends BullMQ.PollJob> extends BullWorker<J> {
  constructor() {
    super(BullMQ.QueueName.Polling);
  }
  async run(job: Job<J>) {
  }
}
