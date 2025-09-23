<!--
    @component
    An input slot for a form (stylized)
-->
<script lang="ts">
  import type { Snippet } from 'svelte';

  interface Props {
    label: string;
    classes?: string;
    children?: Snippet;
  }

  let { label, classes, children }: Props = $props();
</script>

<label class="flex flex-col w-full {classes}">
  <div class="label">
    <span class="fieldset-label">
      {label}
    </span>
  </div>
  {@render children?.()}
</label>
