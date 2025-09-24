<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import { enhance } from '$app/forms';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import IconContainer from '$lib/components/IconContainer.svelte';
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

  const {
    form,
    enhance: pageEnhance,
    submit
  } = superForm(data.form, {
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
        id: 'access_token',
        header: 'Access Token',
        compare: () => 0
      },
      {
        id: 'prefix',
        header: 'Prefix',
        compare: () => 0
      },
      {
        id: 'created',
        header: 'Created',
        compare: () => 0
      },
      {
        id: 'updated',
        header: 'Updated',
        compare: () => 0
      },
      {
        id: 'menu',
        header: ''
      }
    ]}
    serverSide={true}
    startDesc={true}
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
        <td class="flex flex-row flex-wrap p-1 space-x-2">
          <a href="/client-admin/view?id={client.id}">
            <IconContainer icon="mdi:eye" width={16} />
          </a>
          <a href="/client-admin/update?id={client.id}">
            <IconContainer icon="mdi:pencil" width={16} />
          </a>
          <form action="?/deleteClient" method="POST" use:enhance>
            <input type="hidden" name="id" value={client.id} />
            <button
              type="button"
              class="cursor-pointer"
              onclick={(e) => {
                if (confirm('Are you sure you want to delete this item?')) {
                  (e.currentTarget.parentElement as HTMLFormElement).requestSubmit();
                  clients = clients.filter((c) => c.id !== client.id);
                }
              }}
            >
              <IconContainer icon="mdi:trash" width={16} />
            </button>
          </form>
        </td>
      </tr>
    {/snippet}
  </SortTable>
  <form method="POST" action="?/page" use:pageEnhance>
    <div class="space-between-4 flex w-full flex-row flex-wrap place-content-start gap-1 p-4">
      <Pagination bind:size={$form.page.size} total={data.count} bind:page={$form.page.page} />
    </div>
  </form>
</div>
