import type { Job } from 'bullmq';
import type { BullMQ } from '../bullmq';

export async function product(job: Job<BullMQ.Build.Product>): Promise<unknown> {
  return;
}

export async function postProcess(job: Job<BullMQ.Build.PostProcess>): Promise<unknown> {
  return;
}
