import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';
import { artifacts, releaseArtifacts } from '$lib/models/artifacts';

// GET /job/[id]/build/[id]
export const GET: RequestHandler = async ({ params }) => {
  const job = await prisma.job.findUnique({
    where: { id: Number(params.jobId) },
    select: {
      id: true,
      build: {
        where: {
          id: Number(params.buildId)
        },
        select: {
          id: true,
          job_id: true,
          status: true,
          result: true,
          error: true,
          targets: true,
          created: true,
          updated: true,
          artifact_url_base: true,
          console_text_url: true,
          artifact_files: true
        }
      }
    }
  });
  if (!job) return ErrorResponse(404, 'Job not found');
  const build = job.build.at(0);
  if (!build) return ErrorResponse(404, 'Build not found');
  return new Response(
    JSON.stringify({
      ...build,
      artifacts: artifacts(build),
      artifact_url_base: undefined,
      console_text_url: undefined,
      artifact_files: undefined,
      _links: {
        self: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/job/${build.job_id}/build/${build.id}`
        },
        job: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/job/${build.job_id}`
        }
      }
    })
  );
};

const releaseSchema = v.strictObject({
  targets: v.string(),
  channel: v.pipe(v.string(), v.picklist(['alpha', 'beta', 'production'])),
  environment: v.record(v.string(), v.string())
});

// PUT /job/[id]/build/[id]
export const PUT: RequestHandler = async ({ request, params }) => {
  const parsed = v.safeParse(releaseSchema, await request.json());
  if (!parsed.success) return ErrorResponse(400, JSON.stringify(v.flatten(parsed.issues)));
  const runningRelease = await prisma.release.findFirst({
    where: { build_id: Number(params.buildId), status: { in: ['accepted', 'active'] } }
  });
  if (runningRelease) {
    return ErrorResponse(500, 'Release already in progress');
  }
  const job = await prisma.job.findUnique({
    where: { id: Number(params.jobId) },
    select: {
      id: true,
      build: {
        where: { id: Number(params.buildId) },
        select: { id: true, version_code: true }
      }
    }
  });
  if (!job) return ErrorResponse(404, 'Job not found');
  const build = job.build.at(0);
  if (!build) return ErrorResponse(404, 'Build not found');

  // TODO verify channel
  const release = await prisma.release.create({
    data: {
      ...parsed.output,
      build_id: build.id,
      status: 'initialized',
      environment: JSON.stringify(parsed.output.environment)
    },
    select: {
      id: true,
      build_id: true,
      status: true,
      result: true,
      error: true,
      title: true,
      defaultLanguage: true,
      channel: true,
      created: true,
      updated: true,
      artifact_url_base: true,
      console_text_url: true,
      artifact_files: true
    }
  });

  return new Response(
    JSON.stringify({
      ...release,
      artifacts: releaseArtifacts(release),
      artifact_url_base: undefined,
      console_text_url: undefined,
      artifact_files: undefined,
      _links: {}
    })
  );
};

// DELETE /job/[id]/build/[id]
export const DELETE: RequestHandler = async () => {
  return ErrorResponse(405, 'DELETE /job/[id]/build/[id] is not supported at this time', {
    Allow: 'GET'
  });
};
