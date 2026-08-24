<script lang="ts">
  import IconContainer from '../icons/IconContainer.svelte';
  import IconButton, { type IconButtonProps } from './IconButton.svelte';
  import { Icons } from '$lib/icons';

  interface Props extends IconButtonProps {
    returnTo?: string;
    resetForm?: boolean;
  }

  let {
    class: classes,
    icon = Icons.Cancel,
    label = 'Cancel',
    returnTo: href,
    resetForm = false,
    ...rest
  }: Props = $props();
</script>

{#if !rest.children && href}
  <a class={['btn btn-secondary', classes]} {href}>
    <IconContainer {icon} width={20} />
    {label}
  </a>
{:else}
  <IconButton
    type={resetForm ? 'reset' : 'button'}
    class={['btn-secondary', classes]}
    {icon}
    {label}
    {...rest}
  />
{/if}
