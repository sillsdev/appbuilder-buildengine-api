<script lang="ts">
  import '../app.css';
  import { dev } from '$app/environment';
  import favicon from '$lib/assets/favicon.ico';
  import { title } from '$lib/stores';

  let { children } = $props();
</script>

<svelte:head>
  <link rel="icon" href={favicon} />
  <title>{dev ? '[DEV] ' : ''}{$title || 'SIL AppBuilder Administration'}</title>
</svelte:head>

{@render children()}
