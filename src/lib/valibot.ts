import * as v from 'valibot';
import { type Logger, defaultLogger } from './utils';

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
  search: v.string(),
  page: paginateSchema,
  sort: v.nullable(
    v.object({
      field: v.string(),
      direction: v.picklist(['asc', 'desc'])
    })
  )
});

export const stringLimits = {
  build: {
    status: 255,
    result: 255,
    error: 2083,
    channel: 255,
    artifact_url_base: 2083,
    artifact_files: 4096,
    build_guid: 255,
    console_text_url: 255,
    codebuild_url: 255,
    targets: 255
  },
  client: {
    access_token: 255,
    prefix: 4
  },
  job: {
    request_id: 255,
    git_url: 2083,
    app_id: 255,
    publisher_id: 255,
    jenkins_build_url: 1024,
    jenkins_publish_url: 1024
  },
  project: {
    status: 255,
    result: 255,
    error: 2083,
    url: 1024,
    user_id: 255,
    group_id: 255,
    app_id: 255,
    project_name: 255,
    language_code: 255,
    publishing_key: 1024
  },
  release: {
    status: 255,
    result: 255,
    error: 2083,
    channel: 255,
    title: 30,
    defaultLanguage: 255,
    promote_from: 255,
    build_guid: 255,
    console_text_url: 255,
    codebuild_url: 255,
    targets: 255,
    artifact_url_base: 255,
    artifact_files: 255
  }
} as const;

export function trimStrings<T extends Record<string, unknown>>(
  obj: T,
  scope: keyof typeof stringLimits,
  log: Logger = defaultLogger
) {
  for (const [key, limit] of Object.entries(stringLimits[scope])) {
    const raw = obj[key];
    if (raw) {
      let val = (raw as string).trim().substring(0, limit);
      while (new Blob([val]).size > limit) {
        val = val.substring(0, val.length - 1);
      }
      if (raw !== val) {
        log(`trimStrings ${scope}: "${raw}" => "${val}"`);
        //@ts-expect-error this should be fine...
        obj[key] = val;
      }
    }
  }
  return obj;
}

export const applicationTypes = [
  'scriptureappbuilder',
  'dictionaryappbuilder',
  'readingappbuilder',
  'keyboardappbuilder'
] as const;
export type ApplicationType = (typeof applicationTypes)[number];

export const Status = {
  Initialized: 'initialized',
  Accepted: 'accepted',
  Active: 'active',
  Expired: 'expired',
  PostProcessing: 'postprocessing',
  Completed: 'completed'
} as const;
export type Status = (typeof Status)[keyof typeof Status];

export const Result = {
  Success: 'SUCCESS',
  Failure: 'FAILURE',
  Aborted: 'ABORTED'
} as const;
export type Result = (typeof Result)[keyof typeof Result];
