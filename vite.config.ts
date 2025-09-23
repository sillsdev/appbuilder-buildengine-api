import { sveltekit } from '@sveltejs/kit/vite';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';

export default defineConfig({
  server: {
    port: 8443
  },
  preview: {
    port: 8443
  },
  plugins: [tailwindcss(), sveltekit()]
});
