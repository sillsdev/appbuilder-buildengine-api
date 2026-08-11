<script lang="ts">
  import type { HTMLSelectAttributes } from 'svelte/elements';
  import Dropdown, { type DropdownClasses } from '$lib/components/Dropdown.svelte';
  import { getAppIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { byString } from '$lib/utils/sorting';
  import { applicationTypes } from '$lib/valibot';

  interface Props {
    class?: DropdownClasses;
    value: string | null;
    allowNull?: boolean;
    attr?: HTMLSelectAttributes;
  }

  let { class: classes = {}, value = $bindable(), allowNull = false, attr = {} }: Props = $props();

  const current = $derived(applicationTypes.find((type) => type === value));

  let open = $state(false);

  function onclick(val: string | null) {
    open = false;
    value = val;
  }
</script>

{#if attr.name}
  <input type="hidden" name={attr.name} {value} />
{/if}

<Dropdown
  class={{
    dropdown: ['w-full', classes.dropdown],
    label: ['w-full input cursor-auto', classes.label],
    content: ['overflow-y-auto w-auto', classes.content]
  }}
  bind:open
>
  {#snippet label()}
    <div class="flex flex-row items-center gap-1 w-full">
      {#if current}
        <IconContainer icon={getAppIcon(current)} width={24} />
        <span class="grow text-left">
          {current}
        </span>
      {:else}
        <span class="grow font-normal text-left min-w-48">All application types</span>
      {/if}
      <IconContainer icon="gridicons:dropdown" width={20} />
    </div>
  {/snippet}
  {#snippet content()}
    <ul class="menu menu-sm gap-1 p-2">
      {#if allowNull}
        <li class="w-full">
          <div
            class={['btn btn-ghost flex-nowrap justify-start pl-2 pr-1']}
            onclick={() => onclick(null)}
            onkeydown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onclick(null);
              }
            }}
            role="button"
            tabindex="0"
          >
            <span class="grow text-left">All application types</span>
          </div>
        </li>
      {/if}
      {#each applicationTypes.toSorted((a, b) => byString(a, b)) as type}
        <li class="w-full">
          <div
            class={[
              'btn flex-nowrap justify-start pl-2 pr-1',
              type === value ? 'btn-secondary' : 'btn-ghost'
            ]}
            onclick={() => onclick(type)}
            onkeydown={(e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onclick(type);
              }
            }}
            role="button"
            tabindex="0"
          >
            <IconContainer icon={getAppIcon(type)} width={24} />
            <span class="grow text-left">
              {type}
            </span>
          </div>
        </li>
      {/each}
    </ul>
  {/snippet}
</Dropdown>
