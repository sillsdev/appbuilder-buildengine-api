interface NamedEntity {
  name: string | null | undefined;
}

export function byName(
  a: NamedEntity | null | undefined,
  b: NamedEntity | null | undefined
): number {
  return byString(a?.name, b?.name);
}

export function byString(a: string | null | undefined, b: string | null | undefined): number {
  return a?.localeCompare(b ?? '', 'en-US') ?? 0;
}

export function byNumber(a: number | bigint | null, b: number | bigint | null): number {
  return a === b ? 0 : (a ?? 0) > (b ?? 0) ? 1 : -1;
}
