<script lang="ts">
  import { safeParse } from 'valibot';
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import LinkToScriptoria from '$lib/components/LinkToScriptoria.svelte';
  import StatusBadge from '$lib/components/StatusBadge.svelte';
  import Tooltip from '$lib/components/Tooltip.svelte';
  import { Icons } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { byString } from '$lib/utils/sorting';
  import { getRelativeTime, getTimeDateString } from '$lib/utils/time';
  import { JSON2Entries } from '$lib/valibot';

  $title = 'View Release: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  const dateCreated = $derived(getRelativeTime(data.release.created));
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/release-admin" class="link">Releases</a></li>
  <li>{data.release.id}</li>
</Breadcrumbs>

<div class="flex flex-row items-center">
  <h1 class="p-4 pl-0">
    Release {data.release.id}
  </h1>

  <StatusBadge status={data.release.result || data.release.status} />
</div>
<div class="flex gap-1">
  Created <Tooltip tip={getTimeDateString(data.release.created)}>
    {$dateCreated}
  </Tooltip>
  <LinkToScriptoria
    client={data.release.build.job.client}
    bucket={data.release.build.job.git_url}
    scope="release"
    id={data.release.id}
    class="ml-4"
  />
</div>

<div class="border p-2 rounded-md bg-base-200 my-2">
  <div class="gridcont grid gap-x-6 gap-y-2 mb-2">
    <div>
      <span>
        <IconContainer icon={Icons.Build} width={20} />
        Build ID:
      </span>
      <span>
        <a class="link" href="/build-admin/view?id={data.release.build_id}">
          #{data.release.build_id}
        </a>
      </span>
    </div>
    <div>
      <span>
        <IconContainer icon={Icons.Targets} width={20} />
        Targets:
      </span>
      <span>
        {data.release.targets || '(none)'}
      </span>
    </div>
    <div>
      <span>
        <IconContainer icon={Icons.Channel} width={20} />
        Channel:
      </span>
      <span>
        {data.release.channel || '(none)'}
      </span>
    </div>
    <div>
      <span>
        <IconContainer icon={Icons.Language} width={20} />
        Default Language:
      </span>
      <span>
        {data.release.defaultLanguage ?? '(none)'}
      </span>
    </div>
  </div>
  {#if data.release.codebuild_url || data.release.build_guid}
    <div>
      <span>
        <IconContainer icon={Icons.CodeBuild} width={20} class="mr-1" />Code Build:
      </span>
      <span>
        {#if data.release.codebuild_url}
          <a class="link" href={data.release.codebuild_url}>{data.release.build_guid}</a>
        {:else}
          <span>{data.release.build_guid}</span>
        {/if}
      </span>
    </div>
  {/if}
  {#if data.release.error}
    {@const isURL = !!data.release.error?.match(/^https?:/)}
    <div>
      <span>Error:</span>
      {#if isURL}
        <a class="link" href={data.release.error}>
          {data.release.error}
        </a>
      {:else}
        <br />
        <textarea class="textarea w-full min-h-36" readonly>{data.release.error}</textarea>
      {/if}
    </div>
  {/if}
  {#if data.release.artifact_files}
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
  {#if data.release.environment}
    {@const parsed = safeParse(JSON2Entries, data.release.environment)}
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
