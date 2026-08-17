<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import PaginationHeader from '$lib/components/PaginationHeader.svelte';
  import SearchBar from '$lib/components/SearchBar.svelte';
  import SecureDisplay from '$lib/components/SecureDisplay.svelte';
  import Tooltip from '$lib/components/Tooltip.svelte';
  import { Icons } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { getRelativeTime, getTimeDateString } from '$lib/utils/time';

  $title = 'Clients';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let clients = $state(data.clients);
  let count = $state(data.count);

  const { form, enhance, submit } = superForm(data.form, {
    dataType: 'json',
    resetForm: false,
    onChange({ paths }) {
      if (!paths.includes('search')) {
        submit();
      }
    },
    onUpdate(event) {
      const data = event.result.data as FormResult<{
        query: { data: PageData['clients']; count: number };
      }>;
      if (event.form.valid && data.query) {
        clients = data.query.data;
        count = data.query.count;
      }
    }
  });

  function submitSearch() {
    if ($form.page.page) {
      $form.page.page = 0;
    } else {
      submit();
    }
  }

  const mobileSizing = 'w-full md:w-auto';

  const created = $derived(getRelativeTime(clients.map((c) => c.created)));
</script>

<div class="w-full">
  <Breadcrumbs>
    <li><a href="/" class="link">Home</a></li>
    <li>{$title}</li>
  </Breadcrumbs>
  <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
  <form
    method="POST"
    action="?/page"
    use:enhance
    onkeydown={(event) => {
      if (event.key === 'Enter') submitSearch();
    }}
  >
    <div
      class="flex flex-row flex-wrap md:flex-nowrap place-content-end items-center px-4 gap-1 {mobileSizing}"
    >
      <div class="inline-flex items-center grow {mobileSizing}">
        <h1 class="py-4 pl-2">{$title}</h1>
        <a class="btn btn-secondary" href="/client-admin/create">
          <IconContainer icon={Icons.AddUser} width={24} />
          Create Client
        </a>
      </div>
      <div
        class="flex flex-row flex-wrap md:flex-nowrap place-content-end items-center gap-1 {mobileSizing}"
      >
        <SearchBar bind:value={$form.search} requestSubmit={submitSearch} class={mobileSizing} />
      </div>
    </div>
  </form>
  <PaginationHeader page={$form.page} {count} />
  <div class="flex flex-col gap-2">
    {#each clients as client, i}
      <div class="border rounded-md p-2 flex flex-col gap-1">
        <div class="flex flex-row">
          <h3 class="grow flex gap-x-3 pl-1">
            <a class="link" href="/client-admin/view?id={client.id}">#{client.id}</a>
            <i>{client.prefix}</i>
            {#if client.development}
              <span class="badge badge-secondary px-2">
                <IconContainer icon={Icons.Environment} width={16} />
                development
              </span>
            {/if}
          </h3>
          <div class="flex flex-col">
            <Tooltip class="indent-0" tip={getTimeDateString(client.created)}>
              Created: {$created[i]}
            </Tooltip>
          </div>
        </div>
        <div class="flex flex-col pl-1 gap-1">
          {#if client.description}
            <p>{client.description}</p>
          {/if}
          <div class="flex flex-row items-center gap-x-1 w-full">
            <IconContainer icon={Icons.Key} width={16} />
            <SecureDisplay value={client.access_token} />
          </div>
          <div class="flex flex-row flex-wrap gap-1 w-full">
            <div class="flex flex-row items-center gap-x-1">
              <IconContainer icon={Icons.Project} width={16} />
              {client._count.project}
            </div>
            <div class="flex flex-row items-center gap-x-1">
              <IconContainer icon={Icons.Product} width={16} />
              {client._count.job}
            </div>
          </div>
        </div>
      </div>
    {/each}
  </div>
  <form method="POST" action="?/page" use:enhance>
    <div class="space-between-4 flex w-full flex-row flex-wrap place-content-start gap-1 p-4">
      <Pagination bind:size={$form.page.size} total={count} bind:page={$form.page.page} />
    </div>
  </form>
</div>
