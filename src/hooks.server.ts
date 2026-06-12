import { SpanStatusCode, trace } from '@opentelemetry/api';
import { type Handle, type HandleServerError, error } from '@sveltejs/kit';
import { sequence } from '@sveltejs/kit/hooks';
import { building } from '$app/environment';
import OTEL from '$lib/otel';
import { tryVerifyAPIToken, tryVerifyCookie } from '$lib/server/auth';
import { QueueConnected, closeAllConnections, getQueues } from '$lib/server/bullmq';
import { bullboardHandle } from '$lib/server/bullmq/BullBoard';
import { allWorkers } from '$lib/server/bullmq/BullMQ';
import { DatabaseConnected, closeDatabaseConnection } from '$lib/server/prisma';

const handleAPIRoute: Handle = async ({ event, resolve }) => {
  if (event.route.id === '/(api)/health') return resolve(event);
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

  // Graceful shutdown handler
  const shutdown = async (signal: string) => {
    OTEL.instance.logger.info(`Received ${signal}, shutting down gracefully...`);
    try {
      // Close all workers first
      await Promise.all(allWorkers.map((worker) => worker.worker?.close()));
      OTEL.instance.logger.info('All workers closed');

      // Close all queue and Redis connections
      await closeAllConnections();
      OTEL.instance.logger.info('All connections closed');

      // Close database connection
      await closeDatabaseConnection();
      OTEL.instance.logger.info('Database connection closed');

      process.exit(0);
    } catch (error) {
      OTEL.instance.logger.error('Error during shutdown', {
        error: error instanceof Error ? error.message : String(error)
      });
      process.exit(1);
    }
  };

  // Register shutdown handlers
  process.on('sveltekit:shutdown', () => shutdown('sveltekit:shutdown'));
  process.on('SIGTERM', () => shutdown('SIGTERM'));
  process.on('SIGINT', () => shutdown('SIGINT'));

  // Handle uncaught errors
  process.on('uncaughtException', async (error) => {
    OTEL.instance.logger.error('Uncaught exception', {
      error: error.message,
      stack: error.stack
    });
    console.error('Uncaught exception:', error);
    await shutdown('uncaughtException');
  });

  process.on('unhandledRejection', async (reason) => {
    OTEL.instance.logger.error('Unhandled rejection', {
      reason: reason instanceof Error ? reason.message : String(reason)
    });
    console.error('Unhandled rejection:', reason);
    await shutdown('unhandledRejection');
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

const authSequence: Handle = async ({ event, resolve }) =>
  event.route.id?.split('/')?.[1] === '(api)'
    ? handleAPIRoute({ event, resolve })
    : handleAuthRoute({ event, resolve });

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
        async ({ event, resolve }) => {
          return tracer.startActiveSpan('Authentication', async (span) => {
            // Call the auth sequence
            let spanEnded = false;
            try {
              const ret = await authSequence({
                event,
                resolve: (...args) => {
                  if (!spanEnded) {
                    span.end();
                    spanEnded = true;
                  }
                  return resolve(...args);
                }
              });
              return ret;
            } finally {
              if (!spanEnded) {
                span.end();
                spanEnded = true;
              }
            }
          });
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
