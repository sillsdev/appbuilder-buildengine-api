<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import IconContainer from '$lib/components/IconContainer.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SortTable from '$lib/components/SortTable.svelte';
  import { title } from '$lib/stores';

  $title = 'Releases';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let releases = $state(data.releases);

  const { form, enhance, submit } = superForm(data.form, {
    dataType: 'json',
    resetForm: false,
    onChange() {
      submit();
    },
    onUpdate(event) {
      const data = event.result.data as FormResult<{
        query: { data: PageData['releases']; count: number };
      }>;
      if (event.form.valid && data.query) {
        releases = data.query.data;
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
    data={releases}
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
        id: 'job_id',
        header: 'Job ID',
        compare: () => 0
      },
      {
        id: 'build_id',
        header: 'Build ID',
        compare: () => 0
      },
      {
        id: 'status',
        header: 'Status',
        compare: () => 0
      },
      {
        id: 'result',
        header: 'Result',
        compare: () => 0
      },
      {
        id: 'build_guid',
        header: 'Build GUID',
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
    {#snippet row(release, index)}
      <tr>
        <td>{index + 1}</td>
        <td>{release.id}</td>
        <td>
          <a class="link" href="/job-admin/view?id={release.build.job_id}">
            {release.build.job_id}
          </a>
        </td>
        <td>
          <a class="link" href="/build-admin/view?id={release.build_id}">{release.build_id}</a>
        </td>
        <td>{release.status}</td>
        <td>{release.result}</td>
        <td><a class="link" href={release.codebuild_url}>{release.build_guid}</a></td>
        <td class="flex flex-row flex-wrap p-1 space-x-2">
          <a href="/release-admin/view?id={release.id}">
            <IconContainer icon="mdi:eye" width={16} />
          </a>
          <a href="/release-admin/update?id={release.id}">
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
