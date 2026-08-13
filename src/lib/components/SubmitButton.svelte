<script lang="ts">
  import IconButton, { type IconButtonProps } from './IconButton.svelte';
  import { Icons } from '$lib/icons';

  let {
    class: classes,
    icon = Icons.Save,
    disabled = false,
    label = 'Save',
    ...rest
  }: IconButtonProps = $props();
</script>

<IconButton type="submit" class={['btn-primary', classes]} {icon} {label} {disabled} {...rest} />
