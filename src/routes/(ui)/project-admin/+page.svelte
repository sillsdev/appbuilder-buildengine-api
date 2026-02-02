<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import IconContainer from '$lib/components/IconContainer.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SortTable from '$lib/components/SortTable.svelte';
  import { title } from '$lib/stores';

  $title = 'Projects';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let projects = $state(data.projects);

  const { form, enhance, submit } = superForm(data.form, {
    dataType: 'json',
    resetForm: false,
    onChange() {
      submit();
    },
    onUpdate(event) {
      const data = event.result.data as FormResult<{
        query: { data: PageData['projects']; count: number };
      }>;
      if (event.form.valid && data.query) {
        projects = data.query.data;
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
    data={projects}
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
        id: 'status',
        header: 'Status',
        compare: () => 0
      },
      {
        id: 'url',
        header: 'Url',
        compare: () => 0
      },
      {
        id: 'user_id',
        header: 'User ID',
        compare: () => 0
      },
      {
        id: 'group_id',
        header: 'Group ID',
        compare: () => 0
      },
      {
        id: 'app_id',
        header: 'App ID',
        compare: () => 0
      },
      {
        id: 'project_name',
        header: 'Project Name',
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
    {#snippet row(project, index)}
      <tr>
        <td>{index + 1}</td>
        <td>{project.id}</td>
        <td>{project.status}</td>
        <td><a class="link" href={project.url}>{project.url}</a></td>
        <td>{project.user_id}</td>
        <td>{project.group_id}</td>
        <td>{project.app_id}</td>
        <td>{project.project_name}</td>
        <td class="flex flex-row flex-wrap p-1 space-x-2">
          <a href="/project-admin/view?id={project.id}">
            <IconContainer icon="mdi:eye" width={16} />
          </a>
          <a href="/project-admin/update?id={project.id}">
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
