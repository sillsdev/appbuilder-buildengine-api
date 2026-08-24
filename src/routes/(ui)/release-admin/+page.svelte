<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import PaginationHeader from '$lib/components/PaginationHeader.svelte';
  import SearchBar from '$lib/components/SearchBar.svelte';
  import StatusBadge from '$lib/components/StatusBadge.svelte';
  import { Icons } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';

  $title = 'Releases';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let releases = $state(data.releases);
  let count = $state(data.count);

  const { form, enhance, submit } = superForm(data.form, {
    dataType: 'json',
    resetForm: false,
    onChange({ paths }) {
      if (!paths.includes('search')) {
        submit();
      }
    },
    onUpdate(event) {
      const data = event.result.data as FormResult<{
        query: { data: PageData['releases']; count: number };
      }>;
      if (event.form.valid && data.query) {
        releases = data.query.data;
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
        <SearchBar bind:value={$form.search} requestSubmit={submitSearch} class={mobileSizing} />
      </div>
    </div>
  </form>
  <PaginationHeader page={$form.page} {count} />
  <div class="flex flex-col gap-2">
    {#each releases as release}
      <div class="border rounded-md p-2 flex flex-col gap-1">
        <div class="flex flex-row">
          <h3 class="grow flex flex-row gap-2 items-start pl-1">
            <a class="link" href="/release-admin/view?id={release.id}">#{release.id}</a>
            <StatusBadge status={release.result || release.status} />
          </h3>
        </div>
        <div class="flex flex-row items-center gap-x-1 pl-1">
          <IconContainer icon={Icons.Product} width={16} />
          <a class="link mr-2" href="/job-admin/view?id={release.build.job_id}">
            #{release.build.job_id}
          </a>
          <IconContainer icon={Icons.Build} width={16} />
          <a class="link mr-2" href="/build-admin/view?id={release.build.id}">
            #{release.build.id}
          </a>
          {#if release.codebuild_url}
            <IconContainer icon={Icons.CodeBuild} width={16} />
            <a class="link mr-2" href={release.codebuild_url} target="_blank">
              CodeBuild <IconContainer icon={Icons.Open} width={16} />
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
