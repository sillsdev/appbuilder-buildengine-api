import { type Handle } from '@sveltejs/kit';
import { prisma } from '$lib/server/prisma';
import { ErrorResponse } from '$lib/utils';

export const handle: Handle = async ({ event, resolve }) => {
  if (event.route.id?.split('/')[1] === '(api)') {
    if (event.request.headers.get('Content-Type') !== 'application/json') {
      return ErrorResponse(400, 'Missing Header Content-Type: application/json');
    }
    const access_token = event.request.headers.get('Authorization')?.replace('Bearer ', '');
    if (!access_token) {
      return ErrorResponse(401, 'Missing Header Authorization: Bearer <token>');
    }
  }
  return resolve(event);
};
