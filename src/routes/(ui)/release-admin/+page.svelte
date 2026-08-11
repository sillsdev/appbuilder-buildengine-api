<script lang="ts">
  import { type FormResult, superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Pagination from '$lib/components/Pagination.svelte';
  import { Icons, getStatusIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
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
  <div class="flex flex-col gap-2">
    {#each releases as release}
      {@const status = release.result || release.status}
      <div class="border rounded-md p-2 flex flex-col gap-1">
        <div class="flex flex-row">
          <h3 class="grow flex flex-row gap-2 items-start">
            <a class="link" href="/release-admin/view?id={release.id}">#{release.id}</a>
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
      <Pagination bind:size={$form.page.size} total={data.count} bind:page={$form.page.page} />
    </div>
  </form>
</div>
