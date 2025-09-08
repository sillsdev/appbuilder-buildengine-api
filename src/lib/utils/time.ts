type DateType = Date | string | null;

export function getTimeDateString(date: DateType): string {
  if (typeof date === 'string') {
    date = new Date(date);
  }
  // using en-US as locale until we need to support other locales
  return `${date?.toLocaleDateString('en-US') ?? '-'} ${
    date
      ?.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
      })
      .replace(' ', '\xa0') ?? ''
  }`;
}