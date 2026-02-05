import { SpanStatusCode, trace } from '@opentelemetry/api';
import { type Handle, type HandleServerError, error } from '@sveltejs/kit';
import { sequence } from '@sveltejs/kit/hooks';
import { building } from '$app/environment';
import OTEL from '$lib/otel';
import { tryVerifyAPIToken, tryVerifyCookie } from '$lib/server/auth';
import { QueueConnected, getQueues } from '$lib/server/bullmq';
import { bullboardHandle } from '$lib/server/bullmq/BullBoard';
import { allWorkers } from '$lib/server/bullmq/BullMQ';
import { DatabaseConnected } from '$lib/server/prisma';

const handleAPIRoute: Handle = async ({ event, resolve }) => {
  const [success, res] = await tryVerifyAPIToken(event);
  if (!success) {
    return res;
  }
  event.locals.clientId = res?.id ?? null;
  return resolve(event);
};

const handleAuthRoute: Handle = async ({ event, resolve }) => {
  event.locals.clientId = null;
  if (event.route.id?.split('/')?.[1] !== '(auth)') {
    await tryVerifyCookie(event);
  }
  return resolve(event);
};

if (!building) {
  // Start OTEL collector
  OTEL.instance.start();
  // Otherwise valkey will never connect and the server will always 503
  getQueues();
  // Likewise, initialize the Prisma connection heartbeat
  DatabaseConnected();

  // Graceful shutdown
  process.on('sveltekit:shutdown', async () => {
    OTEL.instance.logger.info('Shutting down gracefully...');
    await Promise.all(
      allWorkers.map((worker) => {
        worker.worker?.close();
      })
    );
  });
}

const heartbeat: Handle = async ({ event, resolve }) => {
  // don't check db when loading login or root
  if (!(event.route.id === '/(auth)/login' || event.route.id === '/(ui)')) {
    if (!DatabaseConnected()) {
      console.log(
        'Database connection error! Connected to Database:',
        DatabaseConnected(),
        'Connected to Valkey:',
        QueueConnected()
      );
      throw error(503, 'Database connection error');
    }
  }
  return resolve(event);
};

const tracer = trace.getTracer('IncomingRequest');

export const handle: Handle = async ({ event, resolve }) => {
  if (event.url.pathname.startsWith('/.well-known/appspecific/')) {
    // Ignore these requests without logging them`
    return new Response('', { status: 404 });
  }

  return tracer.startActiveSpan(`${event.request.method} ${event.url.pathname}`, async (span) => {
    let clientIp;
    try {
      clientIp = event.getClientAddress();
    } catch (e) {
      span.recordException(e as Error);
      clientIp = 'unknown';
    }
    span.setAttributes({
      'http.method': event.request.method,
      'http.url': event.url.href,
      'http.route': event.url.pathname ?? '',
      'http.user_agent': event.request.headers.get('user-agent') ?? '',
      'http.client_ip': clientIp,
      'http.x-forwarded-for': event.request.headers.get('x-forwarded-for') ?? '',
      'svelte.route_id': event.route.id ?? ''
    });
    try {
      const response = await sequence(
        heartbeat,
        // Handle auth hooks in a separate OTEL span
        (h) => {
          return event.route.id?.split('/')?.[1] === '(api)'
            ? handleAPIRoute(h)
            : handleAuthRoute(h);
        },
        bullboardHandle
      )({ event, resolve });
      span.setAttributes({
        'http.status_code': response.status
      });
      return response;
    } finally {
      span.end();
    }
  });
};

export const handleError: HandleServerError = ({ error, event, status }) => {
  // Log the error with OTEL
  OTEL.instance.logger.error('Error in handleError', {
    error: error instanceof Error ? error.message : String(error),
    route: event.route.id,
    method: event.request.method,
    url: event.url.href
  });
  trace.getActiveSpan()?.recordException(error as Error);
  trace.getActiveSpan()?.setStatus({
    code: SpanStatusCode.ERROR, // Error
    message: error instanceof Error ? error.message : String(error)
  });

  if (status === 404) {
    // Don't log 404 errors, they are common and not actionable
    return {
      message: 'Not found',
      status: 404
    };
  }

  console.error('Error occurred:', error);

  return {
    message: 'An unexpected error occurred. Please try again later.',
    status: 500 // Internal Server Error
  };
};
