import { type ApplicationType, Result, Status } from '$lib/valibot';

const appIcons = import.meta.glob('/src/lib/icons/app-builders/*.svg', {
  eager: true,
  import: 'default'
}) as Record<string, string>;

export function getAppIcon(type: ApplicationType) {
  return appIcons[`/src/lib/icons/app-builders/${type}.svg`] ?? '';
}

export function getBucketIcon(url: string | null) {
  if (url?.startsWith('s3')) return { title: 'S3 Bucket', icon: Icons.Bucket };
  else if (url?.startsWith('ssh')) return { title: 'CodeCommit', icon: Icons.CodeCommit };
  else return { title: 'URL', icon: Icons.URL };
}

export function getStatusIcon(status: string | null) {
  switch (status) {
    case Result.Success:
    case Status.Completed:
      return { color: 'text-success', icon: 'icon-park-outline:success' };
    case Result.Failure:
      return { color: 'text-error', icon: 'material-symbols:error-outline-rounded' };
    case Result.Aborted:
      return { color: 'text-warning', icon: 'ix:cancelled' };
    case 'PENDING':
      return { icon: 'material-symbols:pending-outline' };
    case Status.Initialized:
      return { icon: 'vaadin:start-cog' };
    case Status.Accepted:
      return { icon: Icons.Checkmark };
    case Status.Active:
      return { color: 'text-error', icon: 'nrk:radio-active' };
    case Status.Expired:
      return { icon: 'pajamas:expire' };
    case Status.PostProcessing:
      return { color: 'text-warning', icon: 'uis:process' };
    default:
      return { icon: Icons.Unknown };
  }
}

export const Icons = {
  Bucket: 'logos:aws-s3',
  Build: 'material-symbols:build',
  Cancel: 'icon-park-outline:return',
  Checkmark: 'mdi:check',
  Close: 'mdi:close',
  CodeBuild: 'logos:aws-codebuild',
  CodeCommit: 'logos:aws-codecommit',
  Copy: 'mdi:content-copy',
  Dashboard: 'clarity:dashboard-line',
  Delete: 'mdi:trash',
  Edit: 'mdi:pencil',
  Hamburger: 'mdi:hamburger-menu',
  Invisible: 'mdi:eye-off-outline',
  Key: 'material-symbols:key',
  Language: 'mdi:language',
  Logout: 'mdi:logout',
  Open: 'mdi:open-in-new',
  Product: 'system-uicons:box',
  Project: 'material-symbols:credit-card-outline',
  Publish: 'material-symbols:publish',
  Save: 'material-symbols:save-outline',
  Search: 'mdi:search',
  SortAsc: 'bx:sort-a-z',
  SortDesc: 'bx:sort-z-a',
  Store: 'ic:twotone-store',
  Unknown: 'carbon:unknown',
  URL: 'solar:link-bold',
  User: 'mdi:user',
  Version: 'mdi:tag',
  View: 'mdi:eye',
  Visible: 'mdi:eye'
} as const;

export type IconType =
  | (typeof Icons)[keyof typeof Icons]
  | ReturnType<typeof getAppIcon>
  | ReturnType<typeof getBucketIcon | typeof getStatusIcon>['icon'];
