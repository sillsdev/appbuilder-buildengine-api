<script lang="ts">
  import type { PageData } from './$types';
  import { enhance } from '$app/forms';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import { title } from '$lib/stores';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'View Client: ' + page.url.searchParams.get('id')!;

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/client-admin" class="link">Clients</a></li>
  <li>{data.client.id}</li>
</Breadcrumbs>

<h1>{$title}</h1>

<div class="flex flex-row space-x-2 mb-2">
  <a href="/client-admin/update?id={data.client.id}" class="btn btn-primary">Update</a>
  <form action="?/deleteClient" method="POST" use:enhance>
    <input type="hidden" name="id" value={data.client.id} />
    <button
      type="button"
      class="btn btn-error"
      onclick={(e) => {
        if (confirm('Are you sure you want to delete this item?')) {
          (e.currentTarget.parentElement as HTMLFormElement).requestSubmit();
        }
      }}
    >
      Delete
    </button>
  </form>
</div>

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
