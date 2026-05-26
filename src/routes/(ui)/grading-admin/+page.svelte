<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import IconContainer from '$lib/components/IconContainer.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SortTable from '$lib/components/SortTable.svelte';
  import { Grading } from '$lib/models/grading';
  import { title } from '$lib/stores';
  import { getTimeDateString } from '$lib/utils/time';

  $title = 'Grading Reports';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let gradingResults = $state(data.gradingResults);

  const { form, enhance, submit } = superForm(data.form, {
    dataType: 'json',
    resetForm: false,
    onChange() {
      submit();
    },
    onUpdate(event) {
      const data = event.result.data as FormResult<{
        query: { data: PageData['gradingResults']; count: number };
      }>;
      if (event.form.valid && data.query) {
        gradingResults = data.query.data;
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
  <a href="/grading-admin/create" class="btn btn-success">Create Report</a>
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
    data={gradingResults}
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
        id: 'project_id',
        header: 'Project ID',
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
        id: 'publisher_id',
        header: 'Publisher',
        compare: () => 0
      },
      {
        id: 'created',
        header: 'Created',
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
    {#snippet row(gradingResult, index)}
      {@const reports = Grading.reports(gradingResult)}
      <tr>
        <td>{index + 1}</td>
        <td>{gradingResult.id}</td>
        <td>
          <a class="link" href="/project-admin/view?id={gradingResult.project_id}">
            {gradingResult.project_id}
          </a>
        </td>
        <td>{gradingResult.status}</td>
        <td>{gradingResult.result}</td>
        <td>{gradingResult.publisher_id}</td>
        <td>{getTimeDateString(gradingResult.created)}</td>
        <td class="flex flex-row flex-wrap p-1 space-x-2">
          {#if reports.html}
            <a href={reports.html} title="HTML report">
              <IconContainer icon="mdi:file-document-outline" width={16} />
            </a>
          {/if}
          {#if reports.json}
            <a href={reports.json} title="JSON report">
              <IconContainer icon="mdi:code-json" width={16} />
            </a>
          {/if}
          <a href="/grading-admin/view?id={gradingResult.id}" title="View">
            <IconContainer icon="mdi:eye" width={16} />
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
