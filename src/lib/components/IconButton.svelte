<script lang="ts" module>
  export interface IconButtonProps {
    label?: string;
    class?: ClassValue;
    form?: string;
    type?: HTMLButtonElement['type'];
    icon?: IconType;
    disabled?: boolean;
    onclick?: (
      e: MouseEvent & {
        currentTarget: EventTarget & HTMLButtonElement;
      }
    ) => void;
    children?: Snippet;
  }
</script>

<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { ClassValue } from 'svelte/elements';
  import IconContainer from '../icons/IconContainer.svelte';
  import { type IconType, Icons } from '$lib/icons';

  let {
    form,
    type = 'button',
    class: classes,
    icon = Icons.Save,
    disabled = false,
    label = 'Save',
    onclick,
    children
  }: IconButtonProps = $props();
</script>

<button {type} {form} class={['btn', classes]} {disabled} {onclick}>
  {#if children}
    {@render children()}
  {:else}
    <IconContainer {icon} width={20} />
    {label}
  {/if}
</button>
