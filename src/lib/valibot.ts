import * as v from 'valibot';

export const idSchema = v.pipe(v.number(), v.minValue(0), v.integer());

export const paramNumber = v.pipe(
  v.string(),
  v.transform((s) => parseInt(s))
);

/** mostly for product IDs */
export const stringIdSchema = v.pipe(v.string(), v.uuid());

export const paginateSchema = v.object({
  page: idSchema,
  size: idSchema
});

export const tableSchema = v.object({
  page: paginateSchema,
  sort: v.nullable(
    v.object({
      field: v.string(),
      direction: v.picklist(['asc', 'desc'])
    })
  )
});
