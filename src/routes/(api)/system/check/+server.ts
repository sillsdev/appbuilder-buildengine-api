import type { RequestHandler } from './$types';
import { prisma } from '$lib/server/prisma';

// GET system/check
export const GET: RequestHandler = async () => {
  const versions = await prisma.appVersion.findMany();

  return new Response(
    JSON.stringify({
      versions: Object.fromEntries(versions.map(({ appName, version }) => [appName, version])),
      created: versions[0].created,
      updated: versions[0].updated,
      imageHash: versions[0].imageHash,
      _links: {
        self: {
          href: `${process.env.ORIGIN || 'http://localhost:8443'}/system/check`
        }
      }
    })
  );
};
