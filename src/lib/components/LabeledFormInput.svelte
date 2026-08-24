<!--
    @component
    An input slot for a form (stylized)
-->
<script lang="ts" module>
  import type { Snippet } from 'svelte';
  import type { ClassValue, HTMLInputAttributes } from 'svelte/elements';
  import type { IconType } from '$lib/icons';
  export interface Props {
    label: string;
    class?: ClassValue;
    children?: Snippet;
    input?: HTMLInputAttributes & {
      class?: ClassValue;
      err?: string;
      icon?: IconType;
      iconClass?: ClassValue;
      after?: Snippet;
    };
    validate?: boolean;
  }
</script>

<script lang="ts" generics="T">
  import IconContainer from '$lib/icons/IconContainer.svelte';

  interface InstanceProps extends Props {
    value?: T;
  }

  let {
    label,
    class: classes,
    children,
    input,
    value = $bindable(),
    validate = true
  }: InstanceProps = $props();
</script>

<label class={['flex flex-col w-full', classes]}>
  <div class="label">
    <span class="fieldset-label">
      {label}
    </span>
  </div>
  {#if input}
    <div class={['input w-full', validate && 'validator']}>
      {#if input.icon}
        <IconContainer
          icon={input.icon}
          width={20}
          class={['cursor-pointer opacity-80', input.iconClass]}
        />
      {/if}
      <input type="text" {...input} bind:value />
      {@render input.after?.()}
    </div>
    {#if validate}
      <span class="validator-hint">
        {#if input.err}{input.err}{:else}&nbsp;{/if}
      </span>
    {/if}
  {:else}
    {@render children?.()}
  {/if}
</label>
