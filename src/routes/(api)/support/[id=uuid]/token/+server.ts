import * as v from 'valibot';
import type { RequestHandler } from './$types';
import { STS } from '$lib/server/aws/sts';
import { AWSVars } from '$lib/server/aws/vars';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

const sts = new STS();

// POST /support/[id]/token
export const POST: RequestHandler = async ({ request, params, locals }) => {
  const body = v.safeParse(
    v.object({
      name: v.string(),
      ReadOnly: v.optional(v.boolean(), false)
    }),
    await request.json()
  );

  if (!body.success) return ErrorResponse(400, JSON.stringify(v.flatten(body.issues)));

  const bucket = AWSVars.support();
  if (!bucket) return ErrorResponse(500, 'Support bucket is not configured');

  const client = locals.clientId
    ? await prisma.client.findUnique({ where: { id: locals.clientId }, select: { prefix: true } })
    : null;
  const s3client = client?.prefix ? client.prefix + '/' : '';
  const url = `s3://${bucket}/${s3client}${params.id.toLowerCase()}`;

  try {
    return new Response(
      JSON.stringify({
        ...(await sts.getS3AccessToken(url, body.output.name, body.output.ReadOnly)),
        Url: url
      })
    );
  } catch (e) {
    return ErrorResponse(500, e instanceof Error ? e.message : String(e));
  }
};
