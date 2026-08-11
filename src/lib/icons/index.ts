import type { ApplicationType } from '$lib/valibot';

const appIcons = import.meta.glob('/src/lib/icons/app-builders/*.svg', {
  eager: true,
  import: 'default'
}) as Record<string, string>;

export function getAppIcon(type: ApplicationType) {
  return appIcons[`/src/lib/icons/app-builders/${type}.svg`] ?? '';
}

export function getStatusIcon(status: string) {
  switch (status.toLowerCase()) {
    case 'success':
      return { color: 'text-success', icon: 'icon-park-outline:success' };
    case 'failure':
      return { color: 'text-error', icon: 'material-symbols:error-outline-rounded' };
    case 'aborted':
      return { color: 'text-warning', icon: 'ix:cancelled' };
    case 'pending':
      return { icon: 'material-symbols:pending-outline' };
    default:
      return { icon: Icons.Unknown };
  }
}

export const Icons = {
  Bucket: 'logos:aws-s3',
  Build: 'material-symbols:build',
  Close: 'mdi:close',
  CodeBuild: 'logos:aws-codebuild',
  CodeCommit: 'logos:aws-codecommit',
  Dashboard: 'clarity:dashboard-line',
  Delete: 'mdi:trash',
  Edit: 'mdi:pencil',
  Hamburger: 'mdi:hamburger-menu',
  Invisible: 'mdi:eye-off-outline',
  Key: 'material-symbols:key',
  Logout: 'mdi:logout',
  Open: 'mdi:open-in-new',
  Product: 'system-uicons:box',
  Project: 'material-symbols:credit-card-outline',
  Publish: 'material-symbols:publish',
  SortAsc: 'bx:sort-a-z',
  SortDesc: 'bx:sort-z-a',
  Unknown: 'carbon:unknown',
  User: 'mdi:user',
  View: 'mdi:eye',
  Visible: 'mdi:eye'
} as const;

export type IconType =
  | (typeof Icons)[keyof typeof Icons]
  | ReturnType<typeof getAppIcon>
  | ReturnType<typeof getStatusIcon>['icon'];
