<!--
    @component
    An input slot with an inline message (stylized)
    
-->
<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { ClassValue } from 'svelte/elements';

  interface Props {
    title?: { label?: string; class?: ClassValue };
    message?: { label?: string; class?: ClassValue };
    class?: ClassValue;
    children?: Snippet;
  }

  let { title, message, class: classes, children }: Props = $props();
</script>

<label class={['flex flex-row items-center gap-2', classes]}>
  <div class="fieldset-label flex-col items-start grow text-base-content">
    {#if title}
      <span class={['title', title.class]}>
        {title.label}
      </span>
    {/if}
    {#if message}
      <span class={['text-sm', message.class]}>
        {message.label}
      </span>
    {/if}
  </div>
  {@render children?.()}
</label>

<style>
  .title {
    font-weight: bold;
  }
</style>
