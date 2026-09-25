import { safeParse } from 'valibot';
import { stringIdSchema } from '$lib/valibot';

export function match(param: string) {
  const parsed = safeParse(stringIdSchema, param);
  return parsed.success;
}
