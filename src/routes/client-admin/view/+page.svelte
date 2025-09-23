<script lang="ts">
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import { title } from '$lib/stores';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'Client #' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/client-admin" class="link">Clients</a></li>
  <li>{$title}</li>
</Breadcrumbs>

<h1>{$title}</h1>

<table class="table table-zebra border">
  <tbody>
    <tr>
      <th>ID</th>
      <td>{data.client.id}</td>
    </tr>
    <tr>
      <th>Access Token</th>
      <td>{data.client.access_token}</td>
    </tr>
    <tr>
      <th>Prefix</th>
      <td>{data.client.prefix}</td>
    </tr>
    <tr>
      <th>Created</th>
      <td>{getTimeDateString(data.client.created)}</td>
    </tr>
    <tr>
      <th>Updated</th>
      <td>{getTimeDateString(data.client.updated)}</td>
    </tr>
  </tbody>
</table>
