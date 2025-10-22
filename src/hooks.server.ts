import { type Handle, error } from '@sveltejs/kit';
import { sequence } from '@sveltejs/kit/hooks';
import { building } from '$app/environment';
import { QueueConnected, getQueues } from '$lib/server/bullmq';
import { bullboardHandle } from '$lib/server/bullmq/BullBoard';
import { allWorkers } from '$lib/server/bullmq/BullMQ';
import { DatabaseConnected, prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

const handleAPIRoute: Handle = async ({ event, resolve }) => {
  if (event.route.id?.split('/')[1] === '(api)') {
    if (event.request.headers.get('Content-Type') !== 'application/json') {
      return ErrorResponse(400, 'Missing Header Content-Type: application/json');
    }
    const access_token = event.request.headers.get('Authorization')?.replace('Bearer ', '');
    if (!access_token) {
      return ErrorResponse(401, 'Missing Header Authorization: Bearer <token>');
    }
    const client = await prisma.client.findFirst({ where: { access_token } });
    if (!client) {
      return ErrorResponse(403, 'Invalid Access Token');
    }
    event.locals.clientId = client.id;
  } else {
    event.locals.clientId = 0;
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

  return await sequence(heartbeat, handleAPIRoute, bullboardHandle)({ event, resolve });
};
