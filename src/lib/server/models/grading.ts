import type { Prisma } from '@prisma/client';

// eslint-disable-next-line @typescript-eslint/no-namespace
export namespace Grading {
  export enum Status {
    Initialized = 'initialized',
    Active = 'active',
    Completed = 'completed'
  }

  export enum Result {
    Success = 'SUCCESS',
    Failure = 'FAILURE'
  }

  export type ResponseBody = Omit<
    Prisma.GradingResultMinAggregateOutputType,
    'publisher_id' | 'project_url' | 'lambda_request_id' | 'report_url_base' | 'report_files'
  > & {
    reports: {
      html?: string;
      json?: string;
    };
    _links: Record<string, { href: string }>;
  };

  export const reportFiles = ['report.html', 'report.json'] as const;

  export function reportPrefix(id: number) {
    return `reports/${id}`;
  }

  export function reportUrlBase(id: number, bucket: string) {
    return `https://${bucket}.s3.amazonaws.com/${reportPrefix(id)}/`;
  }

  export function reports(
    row: Pick<Prisma.GradingResultMinAggregateOutputType, 'report_url_base' | 'report_files'>
  ) {
    const base = row.report_url_base;
    const files = row.report_files?.split(',') ?? [];
    return {
      html: base && files.includes('report.html') ? base + 'report.html' : undefined,
      json: base && files.includes('report.json') ? base + 'report.json' : undefined
    };
  }

  export function response(
    row: Prisma.GradingResultMinAggregateOutputType,
    origin: string,
    extraLinks: Record<string, { href: string }> = {}
  ): ResponseBody {
    return {
      id: row.id,
      project_id: row.project_id,
      status: row.status,
      result: row.result,
      error: row.error,
      created: row.created,
      updated: row.updated,
      reports: reports(row),
      _links: {
        self: {
          href: `${origin}/project/${row.project_id}/grading/${row.id}`
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
