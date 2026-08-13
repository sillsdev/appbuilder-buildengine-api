<script lang="ts">
  import IconContainer from '../icons/IconContainer.svelte';
  import { Icons, getStatusIcon } from '$lib/icons';

  interface Props {
    status: string | null;
  }
  const { status }: Props = $props();
</script>

{#if status}
  {@const { icon } = getStatusIcon(status)}
  <b
    class={[
      'badge',
      status === 'SUCCESS'
        ? 'badge-success'
        : status === 'FAILURE'
          ? 'badge-error'
          : status === 'ABORTED'
            ? 'badge-warning'
            : 'badge-neutral'
    ]}
  >
    {#if icon !== Icons.Unknown}
      <IconContainer {icon} width={20} />
    {/if}
    {status}
  </b>
{/if}
