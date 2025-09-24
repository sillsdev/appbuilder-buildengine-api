import * as v from 'valibot';

export const clientSchema = v.strictObject({
  access_token: v.pipe(
    v.string(),
    v.transform((s) => s.trim()),
    v.minLength(1)
  ),
  prefix: v.pipe(
    v.string(),
    v.transform((s) => s.trim()),
    v.minLength(1)
  )
});
