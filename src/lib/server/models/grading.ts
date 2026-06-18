import type { Prisma } from '@prisma/client';
import { AWSVars } from '$lib/server/aws/vars';

// eslint-disable-next-line @typescript-eslint/no-namespace
export namespace Grading {
  export enum Status {
    Initialized = 'initialized',
    Active = 'active',
    Success = 'success',
    Failure = 'failure'
  }

  export type ResultRow = Prisma.gradingResultGetPayload<true>;

  export type ResultWithProject = Prisma.gradingResultGetPayload<{
    include: { project: true };
  }>;

  export type ResponseBody = Omit<ResultRow, 'lambda_request_id'> & {
    reports: {
      html?: string;
      json?: string;
    };
    _links: Record<string, { href: string }>;
  };

  export const reportFiles = ['report.html', 'report.json'] as const;

  export function reportPrefix(uuid: string) {
    return `reports/${uuid}`;
  }

  export function reportUrlBase(uuid: string, bucket: string) {
    return `https://${bucket}.s3.amazonaws.com/${reportPrefix(uuid)}/`;
  }

  export function reports(row: Pick<ResultRow, 'uuid'>) {
    const base = `https://${AWSVars.artifacts()}.s3.amazonaws.com/${reportPrefix(row.uuid)}/`;
    return {
      html: base + 'report.html',
      json: base + 'report.json'
    };
  }

  export function response(
    row: ResultRow,
    origin: string = process.env.ORIGIN || 'http://localhost:8443',
    extraLinks: Record<string, { href: string }> = {}
  ): ResponseBody {
    return {
      uuid: row.uuid,
      project_id: row.project_id,
      status: row.status,
      result: row.result,
      created: row.created,
      updated: row.updated,
      publisher_id: row.publisher_id,
      reports:
        row.status === Grading.Status.Success
          ? Grading.reports(row)
          : { html: undefined, json: undefined },
      _links: {
        self: {
          href: `${origin}/project/${row.project_id}/grading/${row.uuid}`
        },
        project: {
          href: `${origin}/project/${row.project_id}`
        },
        latest: {
          href: `${origin}/project/${row.project_id}/grading/latest`
        },
        ...extraLinks
      }
    };
  }
}
