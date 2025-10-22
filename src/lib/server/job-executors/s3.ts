import type { Job } from 'bullmq';
import type { BullMQ } from '../bullmq';

export async function save(job: Job<BullMQ.S3.CopyArtifacts>): Promise<unknown> {
  return;
}

export async function error(job: Job<BullMQ.S3.CopyErrors>): Promise<unknown> {
  return;
}
