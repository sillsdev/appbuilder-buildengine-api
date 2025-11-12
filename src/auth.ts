import { type SvelteKitAuthConfig } from '@auth/sveltekit';

const config: SvelteKitAuthConfig = {
  trustHost: true,
  providers: [{}],
  session: {
    maxAge: 60 * 60 * 24 // 24 hours
  }
};
