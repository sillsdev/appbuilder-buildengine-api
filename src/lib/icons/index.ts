export const Icons = {
  Delete: 'mdi:trash',
  Edit: 'mdi:pencil',
  Hamburger: 'mdi:hamburger-menu',
  Logout: 'mdi:logout',
  SortAsc: 'bx:sort-a-z',
  SortDesc: 'bx:sort-z-a',
  User: 'mdi:user',
  View: 'mdi:eye'
} as const;

export type IconType = (typeof Icons)[keyof typeof Icons];
