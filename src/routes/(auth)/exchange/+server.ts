import { trace } from '@opentelemetry/api';
import { error } from '@sveltejs/kit';
import { EncryptJWT, jwtVerify } from 'jose';
import type { RequestHandler } from './$types';
import { env as secrets } from '$env/dynamic/private';
import { env } from '$env/dynamic/public';
import { returnTo } from '$lib/server/auth';
import { QueueConnected } from '$lib/server/bullmq';
import { getAuthConnection } from '$lib/server/bullmq/queues';

// GET system/check
export const GET: RequestHandler = async (event) => {
  if (QueueConnected()) {
    const requestId = event.url.searchParams.get('requestId');
    const code = event.url.searchParams.get('code');
    if (!requestId || !code) {
      throw error(400, 'Missing URL Search Params');
    }

    trace.getActiveSpan()?.setAttribute('request-id', requestId);

    const verify = await getAuthConnection().get(requestId);
    if (!verify) {
      throw error(400, 'Invalid or expired code');
    }

    try {
      //immediately invalidate
      await getAuthConnection().del(requestId);
    } catch {
      /* empty */
    }

    const res: { id_token?: string } = await fetch(
      `${env.PUBLIC_SCRIPTORIA_URL}/api/auth/exchange`,
      {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          code,
          verify
        })
      }
    ).then((r) => r.json());

    if (!res.id_token) {
      throw error(401, 'Authentication failed');
    }

    const key = new TextEncoder().encode(secrets.AUTH0_SECRET);

    const token = await jwtVerify(res.id_token, key);

    trace.getActiveSpan()?.setAttribute('user.email', token.payload.email as string);

    const encryptedToken = await new EncryptJWT(token.payload)
      .setProtectedHeader({ alg: 'dir', enc: 'A256CBC-HS512' })
      .encrypt(key);

    event.cookies.set('scriptoria.session-token', encryptedToken, { path: '/' });

    throw returnTo(event);
  } else {
    throw error(503, 'Service Unavailable');
  }
};
