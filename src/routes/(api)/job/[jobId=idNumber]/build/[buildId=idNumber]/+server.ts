import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { BullMQ, getQueues } from '$lib/server/bullmq';
import { Build } from '$lib/server/models/build';
import { Release } from '$lib/server/models/release';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';
import { stringLimits } from '$lib/valibot';

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
      artifacts: Build.artifacts(build),
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
  targets: v.pipe(v.string(), v.maxBytes(stringLimits.release.targets)),
  channel: v.pipe(v.string(), v.picklist(['alpha', 'beta', 'production'])),
  environment: v.record(v.string(), v.string())
});

// PUT /job/[id]/build/[id]
export const PUT: RequestHandler = async ({ request, params }) => {
  const parsed = v.safeParse(releaseSchema, await request.json());
  if (!parsed.success) return ErrorResponse(400, JSON.stringify(v.flatten(parsed.issues)));
  const runningRelease = await prisma.release.findFirst({
    where: {
      build_id: Number(params.buildId),
      status: { in: [Release.Status.Initialized, Release.Status.Accepted, Release.Status.Active] }
    }
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
        select: { id: true, channel: true, status: true, result: true }
      }
    }
  });
  if (!job) return ErrorResponse(404, 'Job not found');
  const build = job.build.at(0);
  if (!build) return ErrorResponse(404, 'Build not found');

  if (build.status !== Build.Status.Completed)
    return ErrorResponse(409, `Build is incomplete. Current Status: ${build.status}`);

  if (build.result !== Build.Result.Success)
    return ErrorResponse(403, `Build was unsuccessful. Result: ${build.result}`);

  if (!Build.verifyChannel(parsed.output.channel as Build.Channel, build))
    return ErrorResponse(400, `Cannot promote from ${build.channel} to ${parsed.output.channel}`);

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

  await getQueues().Releases.add(
    `Start Release #${release.id} for Build ${build.id} of Job ${job.id}`,
    {
      type: BullMQ.JobType.Release_Product,
      releaseId: release.id
    }
  );

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

// DELETE /job/[id]/build/[id]
export const DELETE: RequestHandler = async ({ params }) => {
  const build = await prisma.build.findUnique({
    where: {
      id: Number(params.buildId)
    },
    select: {
      id: true,
      status: true,
      build_guid: true,
      job: {
        select: {
          id: true,
          app_id: true
        }
      },
      release: {
        select: {
          id: true,
          build_guid: true,
          status: true
        }
      }
    }
  });

  if (!build) return ErrorResponse(404, 'Build not found');

  await prisma.build.delete({ where: { id: build.id } });

  await prisma.$transaction([
    prisma.release.deleteMany({ where: { build: { id: build.id } } }),
    prisma.build.deleteMany({ where: { id: build.id } })
  ]);

  if (build.build_guid && build.status !== Build.Status.Completed) {
    await getQueues().Builds.add(`Cancel Build #${build.id}`, {
      type: BullMQ.JobType.Build_Cancel,
      guid: build.build_guid,
      build
    });
  }

  for (const release of build.release) {
    if (release.build_guid && release.status !== Release.Status.Completed) {
      await getQueues().Releases.add(`Cancel Release #${release.id}`, {
        type: BullMQ.JobType.Release_Cancel,
        guid: release.build_guid,
        release: { ...release, build }
      });
    }
  }

  return new Response(JSON.stringify({}));
};
