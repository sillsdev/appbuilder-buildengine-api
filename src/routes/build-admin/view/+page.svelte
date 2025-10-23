<script lang="ts">
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import { Build } from '$lib/models/build';
  import { title } from '$lib/stores';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'View Build: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/build-admin" class="link">Builds</a></li>
  <li>{data.build.id}</li>
</Breadcrumbs>

<h1>{$title}</h1>

<div class="flex flex-row space-x-2 mb-2">
  <a href="/build-admin/update?id={data.build.id}" class="btn btn-primary">Update</a>
</div>

<table class="table table-zebra border">
  <tbody>
    <tr>
      <th>ID</th>
      <td>{data.build.id}</td>
    </tr>
    <tr>
      <th>Job ID</th>
      <td>
        <a class="link" href="/job-admin/view?id={data.build.job_id}">{data.build.job_id}</a>
      </td>
    </tr>
    <tr>
      <th>Status</th>
      <td>{data.build.status}</td>
    </tr>
    <tr>
      <th>Build GUID</th>
      <td><a class="link" href={data.build.codebuild_url}>{data.build.build_guid}</a></td>
    </tr>
    <tr>
      <th>Result</th>
      <td>{data.build.result}</td>
    </tr>
    <tr>
      <th>Error</th>
      <td><a class="link" href={data.build.error}>{data.build.error}</a></td>
    </tr>
    <tr>
      <th>Artifacts</th>
      <td>
        {#if data.build.artifact_files}
          {#each Object.entries(Build.artifacts(data.build))
            .filter(([_, url]) => !!url)
            .sort(([a, _1], [b, _2]) => a.localeCompare(b, 'en-US')) as [name, url]}
            <a class="link" href={url}>{name}</a>
            ,&nbsp;
          {/each}
        {/if}
      </td>
    </tr>
    <tr>
      <th>Created</th>
      <td>{getTimeDateString(data.build.created)}</td>
    </tr>
    <tr>
      <th>Updated</th>
      <td>{getTimeDateString(data.build.updated)}</td>
    </tr>
    <tr>
      <th>Channel</th>
      <td>{data.build.channel}</td>
    </tr>
    <tr>
      <th>Version Code</th>
      <td>{data.build.version_code}</td>
    </tr>
    <tr>
      <th>Targets</th>
      <td>{data.build.targets}</td>
    </tr>
    <tr>
      <th>Environment</th>
      <td>{data.build.environment}</td>
    </tr>
  </tbody>
</table>
