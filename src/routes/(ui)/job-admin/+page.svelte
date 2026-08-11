<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import { env } from '$env/dynamic/public';
  import AppTypeSelector from '$lib/components/AppTypeSelector.svelte';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import SearchBar from '$lib/components/SearchBar.svelte';
  import { Icons, getAppIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import type { ApplicationType } from '$lib/valibot';

  $title = 'Jobs';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  let jobs = $state(data.jobs);
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
        query: { data: PageData['jobs']; count: number };
      }>;
      if (event.form.valid && data.query) {
        jobs = data.query.data;
        count = data.query.count;
      }
    }
  });

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
      if (event.key === 'Enter') submit();
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
        <SearchBar bind:value={$form.search} requestSubmit={submit} class={mobileSizing} />
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
    {#each jobs as job}
      <div class="border rounded-md p-2 flex flex-col gap-1">
        <div class="flex flex-row">
          <h3 class="grow flex flex-row gap-2 items-start">
            <a class="link" href="/job-admin/view?id={job.id}">#{job.id}</a>
            <img src={getAppIcon(job.app_id as ApplicationType)} width={20} alt={job.app_id} />
            <a
              class="link"
              href="{env.PUBLIC_SCRIPTORIA_URL}/products/{job.request_id}"
              target="_blank"
            >
              {job.request_id}<IconContainer icon={Icons.Open} width={16} />
            </a>
          </h3>
        </div>
        <div class="flex flex-row items-center gap-x-1">
          {#if job.client}
            <IconContainer icon={Icons.User} width={16} />
            <a class="link mr-2" href="/client-admin/view?id={job.client.id}">
              {job.client.prefix}
            </a>
          {/if}
          {#if job.git_url}
            {@const icon = job.git_url.startsWith('s3') ? Icons.Bucket : Icons.CodeCommit}
            {@const title = job.git_url.startsWith('s3') ? 'S3 Bucket' : 'CodeCommit Repo'}
            <IconContainer {icon} width={16} />
            <a class="link" href={job.git_url} target="_blank">
              {title}
              <IconContainer icon={Icons.Open} width={16} />
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
