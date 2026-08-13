<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import AppTypeSelector from '$lib/components/AppTypeSelector.svelte';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import PaginationHeader from '$lib/components/PaginationHeader.svelte';
  import SearchBar from '$lib/components/SearchBar.svelte';
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
  let count = $state(data.count);

  const { form, enhance, submit } = superForm(data.form, {
    dataType: 'json',
    resetForm: false,
    onChange({ paths }) {
      if (paths.includes('appType')) {
        submitSearch();
      } else if (!paths.includes('search')) {
        submit();
      }
    },
    onUpdate(event) {
      const data = event.result.data as FormResult<{
        query: { data: PageData['projects']; count: number };
      }>;
      if (event.form.valid && data.query) {
        projects = data.query.data;
        count = data.query.count;
      }
    }
  });

  function submitSearch() {
    if ($form.page.page) {
      $form.page.page = 0;
    } else {
      submit();
    }
  }

  const mobileSizing = 'w-full md:w-auto';
</script>

<div class="w-full">
  <Breadcrumbs>
    <li><a href="/" class="link">Home</a></li>
    <li>{$title}</li>
  </Breadcrumbs>
  <!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
  <form
    method="POST"
    action="?/page"
    use:enhance
    onkeydown={(event) => {
      if (event.key === 'Enter') submitSearch();
    }}
  >
    <div
      class="flex flex-row flex-wrap md:flex-nowrap place-content-end items-center px-4 gap-1 {mobileSizing}"
    >
      <div class="inline-block grow {mobileSizing}">
        <h1 class="py-4 px-2">{$title}</h1>
      </div>
      <div
        class="flex flex-row flex-wrap md:flex-nowrap place-content-end items-center gap-1 {mobileSizing}"
      >
        <AppTypeSelector bind:value={$form.appType} allowNull class={{ dropdown: 'md:w-auto!' }} />
        <SearchBar bind:value={$form.search} requestSubmit={submitSearch} class={mobileSizing} />
      </div>
    </div>
  </form>
  <PaginationHeader page={$form.page} {count} />
  <div class="flex flex-col gap-2">
    {#each projects as project}
      <div class="border rounded-md p-2 flex flex-col gap-1">
        <div class="flex flex-row">
          <h3 class="grow flex flex-row gap-2 items-center pl-1">
            <a class="link" href="/project-admin/view?id={project.id}">#{project.id}</a>
            <span class="badge badge-secondary p-2">
              <IconContainer icon={Icons.Language} width={16} />{project.language_code}
            </span>
            <IconContainer icon={getAppIcon(project.app_id as ApplicationType)} width={20} />
            <i>{project.project_name}</i>
          </h3>
        </div>
        <div class="flex flex-row items-center gap-x-1 pl-1">
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
      <Pagination bind:size={$form.page.size} total={count} bind:page={$form.page.page} />
    </div>
  </form>
</div>
