import * as v from 'valibot';
import { stringLimits } from '$lib/valibot';

export const clientSchema = v.strictObject({
  prefix: v.pipe(
    v.string(),
    v.transform((s) => s.trim()),
    v.minLength(1),
    v.maxBytes(stringLimits.client.prefix)
  ),
  development: v.boolean(),
  access_token: v.pipe(
    v.string(),
    v.transform((s) => s.trim()),
    v.minLength(1),
    v.maxBytes(stringLimits.client.access_token)
  ),
  description: v.nullable(v.string())
});
