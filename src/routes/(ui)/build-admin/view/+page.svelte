<script lang="ts">
  import { safeParse } from 'valibot';
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import StatusBadge from '$lib/components/StatusBadge.svelte';
  import Tooltip from '$lib/components/Tooltip.svelte';
  import { Icons } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { byString } from '$lib/utils/sorting';
  import { getRelativeTime, getTimeDateString } from '$lib/utils/time';
  import { JSON2Entries } from '$lib/valibot';

  $title = 'View Build: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  const dateCreated = $derived(getRelativeTime(data.build.created));
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/build-admin" class="link">Builds</a></li>
  <li>{data.build.id}</li>
</Breadcrumbs>

<div class="flex flex-row items-center">
  <h1 class="p-4 pl-0">
    Build {data.build.id}
  </h1>

  <StatusBadge status={data.build.result || data.build.status} />
</div>
Created <Tooltip tip={getTimeDateString(data.build.created)}>
  {$dateCreated}
</Tooltip>

<div class="border p-2 rounded-md bg-base-200 my-2">
  <div class="gridcont grid gap-x-6 gap-y-2 mb-2">
    <div class="flex place-content-between">
      <span>
        <IconContainer icon={Icons.Product} width={20} />
        Job ID:
      </span>
      <span>
        <a class="link" href="/job-admin/view?id={data.build.job_id}">
          #{data.build.job_id}
        </a>
      </span>
    </div>
    <div class="flex place-content-between">
      <span>
        <IconContainer icon={Icons.Targets} width={20} />
        Targets:
      </span>
      <span class="flex flex-row gap-1">
        {data.build.targets || '(none)'}
      </span>
    </div>
    <div class="flex place-content-between">
      <span>
        <IconContainer icon={Icons.Channel} width={20} />
        Channel:
      </span>
      <span>
        {data.build.channel || '(none)'}
      </span>
    </div>
    <div class="flex place-content-between">
      <span>
        <IconContainer icon={Icons.Version} width={20} />
        Version Code:
      </span>
      <span>
        {data.build.version_code ?? '(none)'}
      </span>
    </div>
  </div>
  {#if data.build.codebuild_url || data.build.build_guid}
    <div>
      <span>
        <IconContainer icon={Icons.CodeBuild} width={20} class="mr-1" />Code Build:
      </span>
      <span>
        {#if data.build.codebuild_url}
          <a class="link" href={data.build.codebuild_url}>{data.build.build_guid}</a>
        {:else}
          <span>{data.build.build_guid}</span>
        {/if}
      </span>
    </div>
  {/if}
  {#if data.build.error}
    {@const isURL = !!data.build.error?.match(/^https?:/)}
    <div>
      <span>Error:</span>
      {#if isURL}
        <a class="link" href={data.build.error}>
          {data.build.error}
        </a>
      {:else}
        <br />
        <textarea class="textarea w-full min-h-36" readonly>{data.build.error}</textarea>
      {/if}
    </div>
  {/if}
  {#if data.build.artifact_files}
    {@const artifacts = Object.entries(data.artifacts)
      .filter(([_, url]) => !!url)
      .sort(([a, _1], [b, _2]) => byString(a, b))}
    <div class="mt-1">
      <span><IconContainer icon={Icons.File} width={20} class="mr-1" />Artifacts:</span>
      <span class="inline-flex flex-row flex-wrap">
        {#each artifacts as [name, url], i}
          <a class="link ml-1" href={url}>{name}</a>
          {#if i + 1 < artifacts.length}
            ,
          {/if}
        {/each}
      </span>
    </div>
  {/if}
  {#if data.build.environment}
    {@const parsed = safeParse(JSON2Entries, data.build.environment)}
    {#if parsed.success && parsed.output?.length}
      <div class="mt-1">
        <span><IconContainer icon={Icons.Environment} width={20} class="mr-1" />Environment:</span>
        <table class="table table-zebra table-sm">
          <tbody>
            {#each parsed.output as [key, value]}
              <tr class="hidden md:table-row">
                <th>{key}:</th>
                <td>{value}</td>
              </tr>
              <tr class="md:hidden">
                <th>{key}:</th>
              </tr>
              <tr class="md:hidden">
                <td>{value}</td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
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
