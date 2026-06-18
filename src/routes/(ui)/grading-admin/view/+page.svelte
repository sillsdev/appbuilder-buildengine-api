<script lang="ts">
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import { title } from '$lib/stores';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'View Grading Report: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/grading-admin" class="link">Grading Reports</a></li>
  <li>{data.gradingResult.uuid}</li>
</Breadcrumbs>

<h1>{$title}</h1>

<div class="flex flex-row flex-wrap gap-2 mb-2">
  <a
    href="/grading-admin/create?project_id={data.gradingResult.project_id}"
    class="btn btn-primary"
  >
    New Report for Project
  </a>
  <a href="/project-admin/view?id={data.gradingResult.project_id}" class="btn btn-outline">
    Project
  </a>
</div>

<table class="table table-zebra border">
  <tbody>
    <tr>
      <th>ID</th>
      <td>{data.gradingResult.uuid}</td>
    </tr>
    <tr>
      <th>Project ID</th>
      <td>
        <a class="link" href="/project-admin/view?id={data.gradingResult.project_id}">
          {data.gradingResult.project_id}
        </a>
      </td>
    </tr>
    <tr>
      <th>Project Name</th>
      <td>{data.rawGradingResult.project.project_name}</td>
    </tr>
    <tr>
      <th>Publisher</th>
      <td>{data.gradingResult.publisher_id}</td>
    </tr>
    <tr>
      <th>Status</th>
      <td>{data.gradingResult.status}</td>
    </tr>
    <tr>
      <th>Result</th>
      <td>{data.gradingResult.result}</td>
    </tr>
    <tr>
      <th>Reports</th>
      <td>
        {#if data.gradingResult.reports.html}
          <a class="link" href={data.gradingResult.reports.html}>HTML</a>
          &nbsp;
        {/if}
        {#if data.gradingResult.reports.json}
          <a class="link" href={data.gradingResult.reports.json}>JSON</a>
        {/if}
      </td>
    </tr>
    <tr>
      <th>Lambda Request ID</th>
      <td>{data.rawGradingResult.lambda_request_id}</td>
    </tr>
    <tr>
      <th>Created</th>
      <td>{getTimeDateString(data.gradingResult.created)}</td>
    </tr>
    <tr>
      <th>Updated</th>
      <td>{getTimeDateString(data.gradingResult.updated)}</td>
    </tr>
  </tbody>
</table>
