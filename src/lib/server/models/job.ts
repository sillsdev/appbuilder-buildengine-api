import type { ApplicationType } from '$lib/valibot';

// eslint-disable-next-line @typescript-eslint/no-namespace
export namespace Job {
  export const AppType = {
    ScriptureApp: 'scriptureappbuilder',
    ReadingApp: 'readingappbuilder',
    DictionaryApp: 'dictionaryappbuilder',
    KeyboardApp: 'keyboardappbuilder'
  } satisfies Record<string, ApplicationType>;
}
