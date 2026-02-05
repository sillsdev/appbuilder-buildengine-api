import type { Prisma } from '@prisma/client';

// eslint-disable-next-line @typescript-eslint/no-namespace
export namespace Project {
  export type ProjectGroup = Prisma.projectGetPayload<{
    select: { group_id: true; client: { select: { prefix: true } } };
  }>;
  export function groupName(project: ProjectGroup) {
    return `CodeCommit-${project.client ? project.client.prefix + '-' : ''}-${project.group_id?.toUpperCase() ?? ''}`;
  }
}
