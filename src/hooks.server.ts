import { type Handle, error } from '@sveltejs/kit';
import { sequence } from '@sveltejs/kit/hooks';
import { building } from '$app/environment';
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
  event.locals.clientId = res.id;
  return resolve(event);
};

const handleAuthRoute: Handle = async ({ event, resolve }) => {
  event.locals.clientId = 0;
  if (event.route.id?.split('/')?.[1] !== '(auth)') {
    await tryVerifyCookie(event);
  }
  return resolve(event);
};

if (!building) {
  // Otherwise valkey will never connect and the server will always 503
  getQueues();
  // Likewise, initialize the Prisma connection heartbeat
  DatabaseConnected();

  // Graceful shutdown
  process.on('sveltekit:shutdown', async () => {
    await Promise.all(
      allWorkers.map((worker) => {
        worker.worker?.close();
      })
    );
  });
}

const heartbeat: Handle = async ({ event, resolve }) => {
  if (!DatabaseConnected()) {
    console.log(
      'Database connection error! Connected to Database:',
      DatabaseConnected(),
      'Connected to Valkey:',
      QueueConnected()
    );
    throw error(503, 'Database connection error');
  }
  return resolve(event);
};

export const handle: Handle = async ({ event, resolve }) => {
  if (event.url.pathname.startsWith('/.well-known/appspecific/')) {
    // Ignore these requests without logging them`
    return new Response('', { status: 404 });
  }

  return await sequence(
    heartbeat,
    (h) => {
      return event.route.id?.split('/')?.[1] === '(api)' ? handleAPIRoute(h) : handleAuthRoute(h);
    },
    bullboardHandle
  )({ event, resolve });
};
