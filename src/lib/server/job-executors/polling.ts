import type { Job } from 'bullmq';
import type { BullMQ } from '../bullmq';

export async function build(job: Job<BullMQ.Polling.Build>): Promise<unknown> {
  return;
}

export async function publish(job: Job<BullMQ.Polling.Publish>): Promise<unknown> {
  return;
}
