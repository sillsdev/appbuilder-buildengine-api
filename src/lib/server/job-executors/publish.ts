import type { Job } from 'bullmq';
import type { BullMQ } from '../bullmq';

export async function product(job: Job<BullMQ.Publish.Product>): Promise<unknown> {
  return;
}

export async function postProcess(job: Job<BullMQ.Publish.PostProcess>): Promise<unknown> {
  return;
}
