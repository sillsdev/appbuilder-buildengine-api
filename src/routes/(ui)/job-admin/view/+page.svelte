<script lang="ts">
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import { env } from '$env/dynamic/public';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import CopyField from '$lib/components/CopyField.svelte';
  import LinkToScriptoria from '$lib/components/LinkToScriptoria.svelte';
  import Tooltip from '$lib/components/Tooltip.svelte';
  import { Icons, getAppIcon, getBucketIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { getRelativeTime, getTimeDateString } from '$lib/utils/time';
  import type { ApplicationType } from '$lib/valibot';

  $title = 'View Job: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  const dateCreated = $derived(getRelativeTime(data.job.created));
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/job-admin" class="link">Jobs</a></li>
  <li>{data.job.id}</li>
</Breadcrumbs>

<div class="flex flex-row items-center">
  <a class="link" href="{env.PUBLIC_SCRIPTORIA_URL}/products/{data.job.request_id}" target="_blank">
    <h1 class="p-4 pl-0">
      {data.job.request_id}<IconContainer icon={Icons.Open} width={24} />
    </h1>
  </a>
</div>
<div class="flex gap-1">
  Created <Tooltip tip={getTimeDateString(data.job.created)}>
    {$dateCreated}
  </Tooltip>
  <LinkToScriptoria
    client={data.job.client}
    bucket={data.job.git_url}
    scope="job"
    id={data.job.id}
    class="ml-4"
  />
</div>

<div class="border p-2 rounded-md bg-base-200 my-2">
  <div class="gridcont grid gap-x-6 gap-y-2 mb-2">
    <div>
      <span>
        <IconContainer icon={Icons.Store} width={20} />
        Publisher ID:
      </span>
      <span>
        {data.job.publisher_id}
      </span>
    </div>
    <div>
      <span>App ID:</span>
      <span class="flex flex-row gap-1">
        <IconContainer icon={getAppIcon(data.job.app_id as ApplicationType)} width={24} />
        {data.job.app_id}
      </span>
    </div>
    <div>
      <span>
        <IconContainer icon={Icons.User} width={20} />
        Client:
      </span>
      <span>
        {#if data.job.client}
          <a class="link" href="/client-admin/view?id={data.job.client.id}">
            {data.job.client.prefix}
          </a>
        {:else}
          <span>(Default)</span>
        {/if}
      </span>
    </div>
    <div>
      <span>
        <IconContainer icon={Icons.Version} width={20} />
        Existing Version Code:
      </span>
      <span>
        {data.job.existing_version_code}
      </span>
    </div>
  </div>
  {#if data.job.git_url}
    {@const { icon, title } = getBucketIcon(data.job.git_url)}
    <div>
      <span>{title}:</span>
      <br />
      <div class="flex rounded-md text-nowrap bg-base-200 p-3 pt-2 mt-2">
        <IconContainer {icon} width={20} class="opacity-80 mr-2" />
        <p>
          {data.job.git_url?.substring(0, 5)}
        </p>
        <p class="shrink overflow-hidden text-ellipsis">
          {data.job.git_url.split('/').slice(2, -1).join('/')}
        </p>
        <p class="grow pr-2">
          /{data.job.git_url.split('/').pop()}
        </p>
        <CopyField value={data.job.git_url!} />
      </div>
    </div>
  {/if}
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
