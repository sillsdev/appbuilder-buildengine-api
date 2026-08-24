<script lang="ts">
  import type { PageData } from './$types';
  import { enhance } from '$app/forms';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import SecureDisplay from '$lib/components/SecureDisplay.svelte';
  import Tooltip from '$lib/components/Tooltip.svelte';
  import { Icons } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { getRelativeTime, getTimeDateString } from '$lib/utils/time';

  $title = 'View Client: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  const dateCreated = $derived(getRelativeTime(data.client.created));
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/client-admin" class="link">Clients</a></li>
  <li>{data.client.id}</li>
</Breadcrumbs>

<div class="flex flex-row items-center">
  <h1 class="p-4 pl-0">
    Client {data.client.id}
  </h1>
</div>
Created <Tooltip tip={getTimeDateString(data.client.created)}>
  {$dateCreated}
</Tooltip>

<div class="flex flex-row space-x-2 my-2">
  <a href="/client-admin/update?id={data.client.id}" class="btn btn-secondary">
    <IconContainer icon={Icons.Edit} width={20} />Edit Client
  </a>
  <form action="?/deleteClient" method="POST" use:enhance>
    <input type="hidden" name="id" value={data.client.id} />
    <button
      type="button"
      class="btn btn-error"
      disabled={!!(data.client._count.job || data.client._count.project)}
      onclick={(e) => {
        if (confirm('Are you sure you want to delete this item?')) {
          (e.currentTarget.parentElement as HTMLFormElement).requestSubmit();
        }
      }}
    >
      <IconContainer icon={Icons.Delete} width={20} />
      Delete Client
    </button>
  </form>
</div>

<div class="border p-2 rounded-md bg-base-200 my-2">
  <div class="gridcont grid gap-x-6 gap-y-2 mb-2">
    <div>
      <span>
        <IconContainer icon={Icons.Folder} width={20} />
        Prefix:
      </span>
      <span>
        {data.client.prefix}
      </span>
    </div>
    <div>
      <span>
        <IconContainer icon={Icons.Environment} width={20} />
        Development:
      </span>
      <span>
        {data.client.development}
      </span>
    </div>
    <div>
      <span>
        <IconContainer icon={Icons.Project} width={20} />
        Projects:
      </span>
      <span>
        {data.client._count.project}
      </span>
    </div>
    <div>
      <span>
        <IconContainer icon={Icons.Product} width={20} />
        Jobs:
      </span>
      <span>
        {data.client._count.job}
      </span>
    </div>
  </div>
  <div class="flex items-center gap-1 mb-1">
    <span class="inline-flex items-center gap-1">
      <IconContainer icon={Icons.Key} width={20} />
      Access Token:
    </span>
    <SecureDisplay value={data.client.access_token} />
  </div>
  <div>
    <span class="inline-flex items-center gap-1">
      <IconContainer icon={Icons.Info} width={20} />
      Description:
    </span>
    <p class="ml-6">{data.client.description}</p>
  </div>
</div>

<style>
  .gridcont {
    grid-template-columns: repeat(auto-fill, minmax(48%, 1fr));
  }
  .gridcont div {
    display: flex;
    place-content: space-between;
  }
  .gridcont div span:first-child {
    font-family: Montserrat, sans-serif;
    display: flex;
    gap: calc(var(--spacing) * 1) /* 0.25rem = 4px */;
  }
  .gridcont div span:last-child {
    text-align: right;
  }
</style>
