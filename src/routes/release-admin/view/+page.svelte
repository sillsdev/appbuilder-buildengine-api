<script lang="ts">
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import { title } from '$lib/stores';
  import { getArtifactUrl } from '$lib/models/artifacts';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'View Release: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/release-admin" class="link">Releases</a></li>
  <li>{data.release.id}</li>
</Breadcrumbs>

<h1>{$title}</h1>

<div class="flex flex-row space-x-2 mb-2">
  <a href="/release-admin/update?id={data.release.id}" class="btn btn-primary">Update</a>
</div>

<table class="table table-zebra border">
  <tbody>
    <tr>
      <th>ID</th>
      <td>{data.release.id}</td>
    </tr>
    <tr>
      <th>Build ID</th>
      <td>
        <a class="link" href="/build-admin/view?id={data.release.build_id}">
          {data.release.build_id}
        </a>
      </td>
    </tr>
    <tr>
      <th>Status</th>
      <td>{data.release.status}</td>
    </tr>
    <tr>
      <th>Created</th>
      <td>{getTimeDateString(data.release.created)}</td>
    </tr>
    <tr>
      <th>Updated</th>
      <td>{getTimeDateString(data.release.updated)}</td>
    </tr>
    <tr>
      <th>Result</th>
      <td>{data.release.result}</td>
    </tr>
    <tr>
      <th>Error</th>
      <td><a class="link" href={data.release.error}>{data.release.error}</a></td>
    </tr>
    <tr>
      <th>Artifacts</th>
      <td>
        <a class="link" href={data.release.console_text_url}>cloudWatch</a>
        ,&nbsp;
        <a
          class="link"
          href={getArtifactUrl(
            /\.log$/,
            data.release.artifact_url_base,
            data.release.artifact_files
          )}
        >
          consoleText
        </a>
        ,&nbsp;
        <a
          class="link"
          href={getArtifactUrl(
            /publish_url\.txt$/,
            data.release.artifact_url_base,
            data.release.artifact_files
          )}
        >
          publishUrl
        </a>
      </td>
    </tr>
    <tr>
      <th>Channel</th>
      <td>{data.release.channel}</td>
    </tr>
    <tr>
      <th>Title</th>
      <td>{data.release.title}</td>
    </tr>
    <tr>
      <th>Default Language</th>
      <td>{data.release.defaultLanguage}</td>
    </tr>
    <tr>
      <th>Build GUID</th>
      <td><a class="link" href={data.release.codebuild_url}>{data.release.build_guid}</a></td>
    </tr>
    <tr>
      <th>Promote From</th>
      <td>{data.release.promote_from}</td>
    </tr>
    <tr>
      <th>Targets</th>
      <td>{data.release.targets}</td>
    </tr>
    <tr>
      <th>Environment</th>
      <td>{data.release.environment}</td>
    </tr>
  </tbody>
</table>
