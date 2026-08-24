<script lang="ts">
  import IconContainer from '../icons/IconContainer.svelte';
  import { Icons, getStatusIcon } from '$lib/icons';
  import { Result } from '$lib/valibot';

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
      status === Result.Success
        ? 'badge-success'
        : status === Result.Failure
          ? 'badge-error'
          : status === Result.Aborted
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
