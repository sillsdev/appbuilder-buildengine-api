<script lang="ts">
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import { title } from '$lib/stores';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'View Job: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/job-admin" class="link">Jobs</a></li>
  <li>{data.job.id}</li>
</Breadcrumbs>

<h1>{$title}</h1>

<div class="flex flex-row space-x-2 mb-2">
  <a href="/job-admin/update?id={data.job.id}" class="btn btn-primary">Update</a>
</div>

<table class="table table-zebra border">
  <tbody>
    <tr>
      <th>ID</th>
      <td>{data.job.id}</td>
    </tr>
    <tr>
      <th>Request ID</th>
      <td>{data.job.request_id}</td>
    </tr>
    <tr>
      <th>Git Url</th>
      <td><a class="link" href={data.job.git_url}>{data.job.git_url}</a></td>
    </tr>
    <tr>
      <th>App ID</th>
      <td>{data.job.app_id}</td>
    </tr>
    <tr>
      <th>Publisher ID</th>
      <td>{data.job.publisher_id}</td>
    </tr>
    <tr>
      <th>Client ID</th>
      <td>
        <a class="link" href="/client-admin/view?id={data.job.client_id}">{data.job.client_id}</a>
      </td>
    </tr>
    <tr>
      <th>Existing Version Code</th>
      <td>{data.job.existing_version_code}</td>
    </tr>
    <tr>
      <th>Jenkins Build Url</th>
      <td><a class="link" href={data.job.jenkins_build_url}>{data.job.jenkins_build_url}</a></td>
    </tr>
    <tr>
      <th>Jenkins Publish Url</th>
      <td>
        <a class="link" href={data.job.jenkins_publish_url}>{data.job.jenkins_publish_url}</a>
      </td>
    </tr>
    <tr>
      <th>Created</th>
      <td>{getTimeDateString(data.job.created)}</td>
    </tr>
    <tr>
      <th>Updated</th>
      <td>{getTimeDateString(data.job.updated)}</td>
    </tr>
  </tbody>
</table>
