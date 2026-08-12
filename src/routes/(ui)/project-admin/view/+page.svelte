<script lang="ts">
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import IconContainer from '$lib/components/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'View Project: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/project-admin" class="link">Projects</a></li>
  <li>{data.project.id}</li>
</Breadcrumbs>

<h1>{$title}</h1>

<div class="flex flex-row space-x-2 mb-2">
  <a href="/project-admin/update?id={data.project.id}" class="btn btn-primary">Update</a>
  <a href="/grading-admin/create?project_id={data.project.id}" class="btn btn-primary">
    New Grading Report
  </a>
  <a href="/grading-admin" class="btn btn-outline">Grading Reports</a>
</div>

<table class="table table-zebra border">
  <tbody>
    <tr>
      <th>ID</th>
      <td>{data.project.id}</td>
    </tr>
    <tr>
      <th>Status</th>
      <td>{data.project.status}</td>
    </tr>
    <tr>
      <th>Result</th>
      <td>{data.project.result}</td>
    </tr>
    <tr>
      <th>Error</th>
      <td>
        {#if data.project.error?.match(/^https?:/)}
          <a class="link" href={data.project.error}>
            {data.project.error}
          </a>
        {:else}
          {data.project.error}
        {/if}
      </td>
    </tr>
    <tr>
      <th>Url</th>
      <td>{data.project.url}</td>
    </tr>
    <tr>
      <th>User ID</th>
      <td>{data.project.user_id}</td>
    </tr>
    <tr>
      <th>Group ID</th>
      <td>{data.project.group_id}</td>
    </tr>
    <tr>
      <th>App ID</th>
      <td>{data.project.app_id}</td>
    </tr>
    <tr>
      <th>Project Name</th>
      <td>{data.project.project_name}</td>
    </tr>
    <tr>
      <th>Language Code</th>
      <td>{data.project.language_code}</td>
    </tr>
    <tr>
      <th>Client ID</th>
      <td>
        <a class="link" href="/client-admin/view?id={data.project.client_id}">
          {data.project.client_id}
        </a>
      </td>
    </tr>
    <tr>
      <th>Publishing Key</th>
      <td>{data.project.publishing_key}</td>
    </tr>
    <tr>
      <th>Created</th>
      <td>{getTimeDateString(data.project.created)}</td>
    </tr>
    <tr>
      <th>Updated</th>
      <td>{getTimeDateString(data.project.updated)}</td>
    </tr>
  </tbody>
</table>

<h2>Recent Grading Reports</h2>

<table class="table table-zebra border">
  <thead>
    <tr>
      <th>ID</th>
      <th>Status</th>
      <th>Result</th>
      <th>Publisher</th>
      <th>Created</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    {#each data.project.gradingResult as gradingResult}
      <tr>
        <td>{gradingResult.uuid}</td>
        <td>{gradingResult.status}</td>
        <td>{gradingResult.result}</td>
        <td>{gradingResult.publisher_id}</td>
        <td>{getTimeDateString(gradingResult.created)}</td>
        <td class="flex flex-row flex-wrap p-1 space-x-2">
          {#if gradingResult.reports.html}
            <a href={gradingResult.reports.html} title="HTML report">
              <IconContainer icon="mdi:file-document-outline" width={16} />
            </a>
          {/if}
          {#if gradingResult.reports.json}
            <a href={gradingResult.reports.json} title="JSON report">
              <IconContainer icon="mdi:code-json" width={16} />
            </a>
          {/if}
          <a href="/grading-admin/view?id={gradingResult.uuid}" title="View">
            <IconContainer icon="mdi:eye" width={16} />
          </a>
        </td>
      </tr>
    {:else}
      <tr>
        <td colspan="6">No grading reports</td>
      </tr>
    {/each}
  </tbody>
</table>
