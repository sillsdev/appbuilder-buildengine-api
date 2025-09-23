<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SortTable from '$lib/components/SortTable.svelte';
  import { title } from '$lib/stores';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'Clients';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let clients = $state(data.clients);

  const { form, enhance, submit } = superForm(data.form, {
    dataType: 'json',
    resetForm: false,
    onChange() {
      submit();
    },
    onUpdate(event) {
      const data = event.result.data as FormResult<{
        query: { data: PageData['clients']; count: number };
      }>;
      if (event.form.valid && data.query) {
        clients = data.query.data;
      }
    }
  });
</script>

<div class="w-full">
  <Breadcrumbs>
    <li><a href="/" class="link">Home</a></li>
    <li>{$title}</li>
  </Breadcrumbs>
  <h1>{$title}</h1>
  <a class="btn btn-success mb-2" href="/client-admin/create">Create Client</a>
  <p>
    Showing <b>
      {$form.page.page * $form.page.size + 1}-{Math.min(
        ($form.page.page + 1) * $form.page.size,
        data.count
      )}
    </b>
    of
    <b>{data.count}</b>
    items
  </p>
  <SortTable
    data={clients}
    columns={[
      {
        id: 'index',
        header: '#'
      },
      {
        id: 'id',
        header: 'Id',
        compare: () => 0
      },
      {
        id: 'token',
        header: 'Access Token'
      },
      {
        id: 'prefix',
        header: 'Prefix'
      },
      {
        id: 'created',
        header: 'Created'
      },
      {
        id: 'updated',
        header: 'Updated'
      },
      {
        id: 'menu',
        header: ''
      }
    ]}
    serverSide={true}
    onSort={(field, direction) => form.update((data) => ({ ...data, sort: { field, direction } }))}
  >
    {#snippet row(client, index)}
      <tr>
        <td>{index + 1}</td>
        <td>{client.id}</td>
        <td>{client.access_token}</td>
        <td>{client.prefix}</td>
        <td>{getTimeDateString(client.created)}</td>
        <td>{getTimeDateString(client.updated)}</td>
        <td></td>
      </tr>
    {/snippet}
  </SortTable>
  <form method="POST" action="?/page" use:enhance>
    <div class="space-between-4 flex w-full flex-row flex-wrap place-content-start gap-1 p-4">
      <Pagination bind:size={$form.page.size} total={data.count} bind:page={$form.page.page} />
    </div>
  </form>
</div>
