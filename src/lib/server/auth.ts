import { trace } from '@opentelemetry/api';
import type { Prisma } from '@prisma/client';
import { type RequestEvent, error, redirect } from '@sveltejs/kit';
import { jwtDecrypt } from 'jose';
import { createHash, randomUUID } from 'node:crypto';
import { getAuthConnection } from './bullmq/queues';
import { prisma } from './prisma';
import { env as secrets } from '$env/dynamic/private';
import { env } from '$env/dynamic/public';
import { ErrorResponse } from '$lib/utils';

export async function tryVerifyCookie(event: RequestEvent, gotoLoginPage = true) {
  const cookie = event.cookies.get('scriptoria.session-token');

  let token = null;
  try {
    if (cookie) {
      token = await jwtDecrypt(cookie, new TextEncoder().encode(secrets.AUTH0_SECRET));

      event.locals.userEmail = token.payload.email as string;

      trace.getActiveSpan()?.setAttribute('user.email', event.locals.userEmail);
    }
  } catch {
    /* empty */
  }

  if (token && token.payload.scope !== 'admin') {
    invalidateLogin(event, false);
    throw error(401, 'Bad Login');
  }

  if (!cookie || !token) {
    if (gotoLoginPage) {
      const returnTo = event.url.pathname + event.url.search;
      throw redirect(302, `/login?returnTo=${encodeURIComponent(returnTo)}`);
    } else {
      throw await initiateScriptoriaLogin(event);
    }
  }
}

async function initiateScriptoriaLogin(event: RequestEvent) {
  const verify = randomUUID();
  const requestId = randomUUID();

  trace.getActiveSpan()?.setAttributes({
    'scriptoria-endpoint': env.PUBLIC_SCRIPTORIA_URL,
    'request-id': requestId
  });

  await getAuthConnection().set(`${requestId}`, verify, 'EX', 300); // 5 minute (300 s) TTL

  const hash = createHash('sha256');
  hash.update(verify);
  const challenge = hash.digest('base64url').replace(/=+$/, '');

  const returnTo = event.url.searchParams.get('returnTo');

  throw redirect(
    302,
    `${env.PUBLIC_SCRIPTORIA_URL}/api/auth/token?` +
      `challenge=${challenge}&` +
      `redirect_uri=${encodeURIComponent(
        `${secrets.ORIGIN}/exchange?` +
          (returnTo ? `returnTo=${returnTo}` : '') +
          `&requestId=${requestId}`
      )}&` +
      `scope=admin`
  );
}

export function returnTo(event: RequestEvent) {
  let redirectUrl = decodeURIComponent(event.url.searchParams.get('returnTo') ?? '');
  while (redirectUrl?.startsWith('/login')) {
    redirectUrl = decodeURIComponent(new URL(redirectUrl).searchParams.get('returnTo') ?? '');
  }
  throw redirect(
    302,
    redirectUrl && redirectUrl.startsWith('/') && !redirectUrl.startsWith('//') ? redirectUrl : '/'
  );
}

export function invalidateLogin(event: RequestEvent, redirectToLogin = true) {
  event.cookies.set('scriptoria.session-token', '', { path: '/' });
  if (redirectToLogin) {
    throw redirect(302, '/login');
  }
}

export async function tryVerifyAPIToken(
  event: RequestEvent
): Promise<[true, Prisma.clientGetPayload<true> | null] | [false, Response]> {
  if (event.request.headers.get('Content-Type') !== 'application/json') {
    return [false, ErrorResponse(400, 'Missing Header Content-Type: application/json')];
  }
  const access_token = event.request.headers.get('Authorization')?.replace('Bearer ', '');
  if (!access_token) {
    return [false, ErrorResponse(401, 'Missing Header Authorization: Bearer <token>')];
  }
  const client = await prisma.client.findFirst({ where: { access_token } });
  if (!client) {
    if (access_token === secrets.API_ACCESS_TOKEN) {
      return [true, null];
    }
    return [false, ErrorResponse(403, 'Invalid Access Token')];
  }

  trace.getActiveSpan()?.setAttributes({ 'client.id': client.id, 'client.prefix': client.prefix });

  return [true, client];
}
