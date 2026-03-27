import type { RequestHandler } from './$types';
import { BullMQ, getQueues } from '$lib/server/bullmq';
import { Release } from '$lib/server/models/release';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

// GET /job/[id]/build/[id]/release/[id]
export const GET: RequestHandler = async ({ params }) => {
  const job = await prisma.job.findUnique({
    where: { id: Number(params.jobId) },
    select: {
      id: true,
      build: {
        where: { id: Number(params.buildId) },
        select: {
          id: true,
          release: {
            where: { id: Number(params.releaseId) },
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
          }
        }
      }
    }
  });
  if (!job) return ErrorResponse(404, 'Job not found');
  const build = job.build.at(0);
  if (!build) return ErrorResponse(404, 'Build not found');
  const release = build.release.at(0);
  if (!release) return ErrorResponse(404, 'Release not found');

  return new Response(
    JSON.stringify({
      ...release,
      artifacts: Release.artifacts(release),
      artifact_url_base: undefined,
      console_text_url: undefined,
      artifact_files: undefined,
      _links: {}
    })
  );
};

// DELETE /job/[id]/build/[id]/release/[id]
export const DELETE: RequestHandler = async ({ params }) => {
  const release = await prisma.release.findUnique({
    where: {
      id: Number(params.releaseId)
    },
    select: {
      id: true,
      status: true,
      build_guid: true,
      build: {
        select: {
          job: {
            select: {
              id: true,
              app_id: true
            }
          }
        }
      }
    }
  });

  if (!release) return ErrorResponse(404, 'Release not found');

  await prisma.release.delete({ where: { id: release.id } });

  if (release.build_guid && release.status !== Release.Status.Completed) {
    await getQueues().Releases.add(`Cancel Release #${release.id}`, {
      type: BullMQ.JobType.Release_Cancel,
      guid: release.build_guid,
      release
    });
  }

  return new Response(JSON.stringify({}));
};
