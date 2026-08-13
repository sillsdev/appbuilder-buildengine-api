<!--
    @component
    An input slot for a form (stylized)
-->
<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { ClassValue } from 'svelte/elements';

  interface Props {
    label: string;
    class?: ClassValue;
    children?: Snippet;
  }

  let { label, class: classes, children }: Props = $props();
</script>

<label class={['flex flex-col w-full', classes]}>
  <div class="label">
    <span class="fieldset-label">
      {label}
    </span>
  </div>
  {@render children?.()}
</label>
