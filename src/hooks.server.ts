import { type Handle } from '@sveltejs/kit';
import { prisma } from '$lib/server/prisma';

export const handle: Handle = async ({ event, resolve }) => {
  if (event.route.id?.split('/')[1] === '(api)') {
    if (event.request.headers.get('Content-Type') !== 'application/json') {
      return new Response(
        JSON.stringify({
          code: 400,
          error: 'Missing Header Content-Type: application/json'
        }),
        { status: 400 }
      );
    }
    const access_token = event.request.headers.get('Authorization')?.replace('Bearer ', '');
    if (!(access_token && (await prisma.client.findFirst({ where: { access_token } })))) {
      return new Response(
        JSON.stringify({
          code: 401,
          error: 'Missing Header Authorization: Bearer <token>'
        }),
        { status: 401 }
      );
    }
  }
  return resolve(event);
};
