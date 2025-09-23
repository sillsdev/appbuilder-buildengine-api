<script lang="ts">
  import { type Snippet } from 'svelte';

  interface Props {
    children?: Snippet;
  }

  let { children }: Props = $props();
</script>

<div class="my-2 breadcrumbs w-full bg-base-200 px-2 text-sm">
  <ul>
    {@render children?.()}
  </ul>
</div>
