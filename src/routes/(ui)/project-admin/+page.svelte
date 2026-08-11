<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import { Icons, getAppIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import type { ApplicationType } from '$lib/valibot';

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
  <div class="flex flex-col gap-2">
    {#each projects as project}
      <div class="border rounded-md p-2 flex flex-col gap-1">
        <div class="flex flex-row">
          <h3 class="grow flex flex-row gap-2 items-start">
            <a class="link" href="/project-admin/view?id={project.id}">#{project.id}</a>
            <img
              src={getAppIcon(project.app_id as ApplicationType)}
              width={20}
              alt={project.app_id}
            />
            <i>{project.project_name}</i>
          </h3>
        </div>
        <div class="flex flex-row items-center gap-x-1">
          {#if project.client}
            <IconContainer icon={Icons.User} width={16} />
            <a class="link mr-2" href="/client-admin/view?id={project.client.id}">
              {project.client.prefix}
            </a>
          {/if}
          {#if project.url}
            <IconContainer icon={Icons.Bucket} width={16} />
            <a class="link" href={project.url} target="_blank">
              S3 Bucket <IconContainer icon={Icons.Open} width={16} />
            </a>
          {/if}
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
