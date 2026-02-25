import * as v from 'valibot';
import { stringLimits } from '$lib/valibot';

export const clientSchema = v.strictObject({
  access_token: v.pipe(
    v.string(),
    v.transform((s) => s.trim()),
    v.minLength(1),
    v.maxBytes(stringLimits.client.access_token)
  ),
  prefix: v.pipe(
    v.string(),
    v.transform((s) => s.trim()),
    v.minLength(1),
    v.maxBytes(stringLimits.client.prefix)
  )
});
