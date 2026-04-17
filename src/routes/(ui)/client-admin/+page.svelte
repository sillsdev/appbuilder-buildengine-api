<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SecureDisplay from '$lib/components/SecureDisplay.svelte';
  import Tooltip from '$lib/components/Tooltip.svelte';
  import { Icons } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { getRelativeTime, getTimeDateString } from '$lib/utils/time';

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

  const created = $derived(getRelativeTime(clients.map((c) => c.created)));
  const updated = $derived(getRelativeTime(clients.map((c) => c.updated)));
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
  <div class="flex flex-col gap-2">
    {#each clients as client, i}
      <div class="border rounded-md p-2 flex flex-col gap-1">
        <div class="flex flex-row">
          <h3 class="grow">
            <a class="link" href="/client-admin/view?id={client.id}">#{client.id}</a>
            <i class="pl-2">{client.prefix}</i>
          </h3>
          <div class="flex flex-col">
            <Tooltip class="indent-0" tip={getTimeDateString(client.created)}>
              Created: {$created[i]}
            </Tooltip>
            <Tooltip class="indent-0" tip={getTimeDateString(client.updated)}>
              Updated: {$updated[i]}
            </Tooltip>
          </div>
        </div>
        <div class="flex flex-row items-center gap-x-1 w-full">
          <IconContainer icon={Icons.Key} width={16} />
          <SecureDisplay value={client.access_token} />
        </div>
        <div class="flex flex-row flex-wrap gap-1 w-full">
          <div class="flex flex-row items-center gap-x-1">
            <IconContainer icon={Icons.Project} width={16} />
            {client._count.project}
          </div>
          <div class="flex flex-row items-center gap-x-1">
            <IconContainer icon={Icons.Product} width={16} />
            {client._count.job}
          </div>
        </div>
      </div>
    {/each}
  </div>
  <form method="POST" action="?/page" use:enhance>
    <div class="space-between-4 flex w-full flex-row flex-wrap place-content-start gap-1 p-4">
      <Pagination bind:size={$form.page.size} total={data.count} bind:page={$form.page.page} />
    </div>
  </form>
</div>
