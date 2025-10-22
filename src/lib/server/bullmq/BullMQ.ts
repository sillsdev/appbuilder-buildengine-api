import * as Workers from './BullWorker';
import { building } from '$app/environment';

export const allWorkers = building
  ? []
  : [
      new Workers.Builds(),
      new Workers.S3(),
      new Workers.Projects(),
      new Workers.Publishing(),
      new Workers.Polling()
    ];
