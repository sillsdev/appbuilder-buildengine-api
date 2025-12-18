import type { Prisma } from '@prisma/client';
import { type RequestEvent, redirect } from '@sveltejs/kit';
import { jwtDecrypt } from 'jose';
import { createHash, randomUUID } from 'node:crypto';
import { getAuthConnection } from './bullmq/queues';
import { prisma } from './prisma';
import { env } from '$env/dynamic/private';
import { ErrorResponse } from '$lib/utils';

export async function tryVerifyCookie(event: RequestEvent) {
  const cookie = event.cookies.get('scriptoria.session-token');

  let token = null;
  try {
    if (cookie) {
      token = await jwtDecrypt(cookie, new TextEncoder().encode(env.AUTH0_SECRET));
    }
  } catch {
    /* empty */
  }

  if (!cookie || !token) {
    const verify = randomUUID();
    const requestId = randomUUID();

    await getAuthConnection().set(`${requestId}`, verify, 'EX', 300); // 5 minute (300 s) TTL

    const hash = createHash('sha256');
    hash.update(verify);
    const challenge = hash.digest('base64url').replace(/=+$/, '');

    const returnTo = event.url.pathname + event.url.search;

    throw redirect(
      302,
      `${env.SCRIPTORIA_URL}/api/auth/token?challenge=${challenge}&redirect_uri=${encodeURIComponent(`${env.ORIGIN}/exchange?returnTo=${encodeURIComponent(returnTo)}&requestId=${requestId}`)}&scope=admin`
    );
  }
}

export async function tryVerifyAPIToken(
  event: RequestEvent
): Promise<[true, Prisma.clientGetPayload<true>] | [false, Response]> {
  if (event.request.headers.get('Content-Type') !== 'application/json') {
    return [false, ErrorResponse(400, 'Missing Header Content-Type: application/json')];
  }
  const access_token = event.request.headers.get('Authorization')?.replace('Bearer ', '');
  if (!access_token) {
    return [false, ErrorResponse(401, 'Missing Header Authorization: Bearer <token>')];
  }
  const client = await prisma.client.findFirst({ where: { access_token } });
  if (!client) {
    return [false, ErrorResponse(403, 'Invalid Access Token')];
  }

  return [true, client];
}
