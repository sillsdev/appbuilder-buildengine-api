<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import IconContainer from '$lib/components/IconContainer.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SortTable from '$lib/components/SortTable.svelte';
  import { title } from '$lib/stores';

  $title = 'Jobs';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let jobs = $state(data.jobs);

  const { form, enhance, submit } = superForm(data.form, {
    dataType: 'json',
    resetForm: false,
    onChange() {
      submit();
    },
    onUpdate(event) {
      const data = event.result.data as FormResult<{
        query: { data: PageData['jobs']; count: number };
      }>;
      if (event.form.valid && data.query) {
        jobs = data.query.data;
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
    data={jobs}
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
        id: 'requestId',
        header: 'Request Id'
      },
      {
        id: 'gitUrl',
        header: 'Git Url'
      },
      {
        id: 'appId',
        header: 'App ID'
      },
      {
        id: 'publisherId',
        header: 'Publisher ID'
      },
      {
        id: 'clientId',
        header: 'Client ID'
      },
      {
        id: 'versionCode',
        header: 'Existing Version Code'
      },
      {
        id: 'menu',
        header: ''
      }
    ]}
    serverSide={true}
    onSort={(field, direction) => form.update((data) => ({ ...data, sort: { field, direction } }))}
  >
    {#snippet row(job, index)}
      <tr>
        <td>{index + 1}</td>
        <td>{job.id}</td>
        <td>{job.request_id}</td>
        <td><a class="link" href={job.git_url}>{job.git_url}</a></td>
        <td>{job.app_id}</td>
        <td>{job.publisher_id}</td>
        <td><a class="link" href="/client-admin/view?id={job.client_id}">{job.client_id}</a></td>
        <td>{job.existing_version_code}</td>
        <td class="flex flex-row flex-wrap p-1 space-x-2">
          <a href="/job-admin/view?id={job.id}">
            <IconContainer icon="mdi:eye" width={16} />
          </a>
          <a href="/job-admin/update?id={job.id}">
            <IconContainer icon="mdi:pencil" width={16} />
          </a>
        </td>
      </tr>
    {/snippet}
  </SortTable>
  <form method="POST" action="?/page" use:enhance>
    <div class="space-between-4 flex w-full flex-row flex-wrap place-content-start gap-1 p-4">
      <Pagination bind:size={$form.page.size} total={data.count} bind:page={$form.page.page} />
    </div>
  </form>
</div>
