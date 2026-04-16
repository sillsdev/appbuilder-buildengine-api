export const Icons = {
  Build: 'material-symbols:build',
  Dashboard: 'clarity:dashboard-line',
  Delete: 'mdi:trash',
  Edit: 'mdi:pencil',
  Hamburger: 'mdi:hamburger-menu',
  Logout: 'mdi:logout',
  Open: 'mdi:open-in-new',
  Product: 'system-uicons:box',
  Project: 'material-symbols:credit-card-outline',
  Publish: 'material-symbols:publish',
  SortAsc: 'bx:sort-a-z',
  SortDesc: 'bx:sort-z-a',
  User: 'mdi:user',
  View: 'mdi:eye'
} as const;

export type IconType = (typeof Icons)[keyof typeof Icons];
