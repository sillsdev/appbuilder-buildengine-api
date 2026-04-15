<script lang="ts">
  import Icon from '@iconify/svelte';
  import type { IconType } from '.';

  interface Props {
    icon: IconType;
    width: number | string;
    tooltip?: string;
    class?: string;
  }

  let { icon, width, tooltip = '', class: classes = '' }: Props = $props();
</script>

<span
  class="inline-block align-middle {classes}"
  style="width: {width}px; height: {width}px"
  title={tooltip}
>
  <Icon {icon} {width} class="" height={width} />
</span>
