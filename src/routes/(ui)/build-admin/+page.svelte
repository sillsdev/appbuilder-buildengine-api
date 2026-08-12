<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SearchBar from '$lib/components/SearchBar.svelte';
  import { Icons, getStatusIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';

  $title = 'Builds';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let builds = $state(data.builds);
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
        query: { data: PageData['builds']; count: number };
      }>;
      if (event.form.valid && data.query) {
        builds = data.query.data;
        count = data.query.count;
      }
    }
  });

  function submitSearch() {
    $form.page.page = 0;
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
  <p>
    Showing <b>
      {$form.page.page * $form.page.size + 1}-{Math.min(
        ($form.page.page + 1) * $form.page.size,
        count
      )}
    </b>
    of
    <b>{count}</b>
    items
  </p>
  <div class="flex flex-col gap-2">
    {#each builds as build}
      {@const status = build.result || build.status}
      <div class="border rounded-md p-2 flex flex-col gap-1">
        <div class="flex flex-row">
          <h3 class="grow flex flex-row gap-2 items-start">
            <a class="link" href="/build-admin/view?id={build.id}">#{build.id}</a>
            {#if status}
              {@const { icon } = getStatusIcon(status)}
              <b
                class={[
                  'badge',
                  status === 'SUCCESS'
                    ? 'badge-success'
                    : status === 'FAILURE'
                      ? 'badge-error'
                      : status === 'ABORTED'
                        ? 'badge-warning'
                        : 'badge-neutral'
                ]}
              >
                {#if icon !== Icons.Unknown}
                  <IconContainer {icon} width={20} />
                {/if}
                {status}
              </b>
            {/if}
          </h3>
        </div>
        <div class="flex flex-row items-center gap-x-1">
          <IconContainer icon={Icons.Product} width={16} />
          <a class="link mr-2" href="/job-admin/view?id={build.job_id}">
            #{build.job_id}
          </a>
          {#if build.codebuild_url}
            <IconContainer icon={Icons.CodeBuild} width={16} />
            <a class="link mr-2" href={build.codebuild_url} target="_blank">
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
