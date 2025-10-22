import type { Job } from 'bullmq';
import type { BullMQ } from '../bullmq';

export async function create(job: Job<BullMQ.Project.Create>): Promise<unknown> {
  return;
}
