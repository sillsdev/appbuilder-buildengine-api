<script lang="ts">
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import CopyField from '$lib/components/CopyField.svelte';
  import StatusBadge from '$lib/components/StatusBadge.svelte';
  import Tooltip from '$lib/components/Tooltip.svelte';
  import { Icons, getAppIcon, getBucketIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { getRelativeTime, getTimeDateString } from '$lib/utils/time';
  import type { ApplicationType } from '$lib/valibot';

  $title = 'View Project: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  const dateCreated = $derived(getRelativeTime(data.project.created));
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/project-admin" class="link">Projects</a></li>
  <li>{data.project.id}</li>
</Breadcrumbs>

<div class="flex flex-row items-center">
  <h1 class="p-4 pl-0">{data.project.project_name}</h1>
  <StatusBadge status={data.project.result || data.project.status} />
</div>
Created <Tooltip tip={getTimeDateString(data.project.created)}>
  {$dateCreated}
</Tooltip>
<div class="flex flex-row space-x-2 my-2">
  <a href="/project-admin/update?id={data.project.id}" class="btn btn-secondary">
    <IconContainer icon={Icons.Edit} width={20} />Edit Project
  </a>
</div>

<div class="border p-2 rounded-md bg-base-200">
  <div class="gridcont grid gap-x-6 gap-y-2 mb-2">
    <div class="flex place-content-between">
      <span>
        <IconContainer icon={Icons.Language} width={20} />
        Language:
      </span>
      <span>
        {data.project.language_code}
      </span>
    </div>
    <div class="flex place-content-between">
      <span>App ID:</span>
      <span class="flex flex-row gap-1">
        <IconContainer icon={getAppIcon(data.project.app_id as ApplicationType)} width={24} />
        {data.project.app_id}
      </span>
    </div>
    {#if data.project.client}
      <div class="flex place-content-between">
        <span>
          <IconContainer icon={Icons.User} width={20} />
          Client:
        </span>
        <span>
          <a class="link" href="/client-admin/view?id={data.project.client.id}">
            {data.project.client.prefix}
          </a>
        </span>
      </div>
    {/if}
  </div>
  {#if data.project.url}
    {@const { icon, title } = getBucketIcon(data.project.url)}
    <div>
      <span>{title}:</span>
      <br />
      <div class="flex rounded-md text-nowrap bg-base-200 p-3 pt-2 mt-2">
        <IconContainer {icon} width={20} class="opacity-80 mr-2" />
        <p>
          {data.project.url?.substring(0, 5)}
        </p>
        <p class="shrink overflow-hidden text-ellipsis">
          {data.project.url.split('/').slice(2, -1).join('/')}
        </p>
        <p class="grow pr-2">
          /{data.project.url.split('/').pop()}
        </p>
        <CopyField value={data.project.url!} />
      </div>
    </div>
  {/if}
  {#if data.project.error}
    {@const isURL = !!data.project.error?.match(/^https?:/)}
    <div>
      <span>Error:</span>
      {#if isURL}
        <a class="link" href={data.project.error}>
          {data.project.error}
        </a>
      {:else}
        <br />
        <textarea class="textarea w-full min-h-36" readonly>{data.project.error}</textarea>
      {/if}
    </div>
  {/if}
</div>

<style>
  .gridcont {
    grid-template-columns: repeat(auto-fill, minmax(48%, 1fr));
  }
  .gridcont div span:first-child {
    font-family: Montserrat, sans-serif;
  }
  .gridcont div span:last-child {
    text-align: right;
  }
</style>
