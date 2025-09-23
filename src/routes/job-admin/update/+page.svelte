<script lang="ts">
  import { superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import LabeledFormInput from '$lib/components/LabeledFormInput.svelte';
  import { title } from '$lib/stores';

  const id = $derived(page.url.searchParams.get('id')!);

  $effect(() => {
    $title = 'Update Job: ' + id;
  });

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  const { form, enhance } = superForm(data.form, {
    dataType: 'json'
  });
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/job-admin" class="link">Jobs</a></li>
  <li><a href="/job-admin/view?id={id}" class="link">{id}</a></li>
  <li>Update</li>
</Breadcrumbs>
<h1>{$title}</h1>

<form method="POST" use:enhance>
  <LabeledFormInput label="Request ID">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.requestId}
      required
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Git Url">
    <input class="input input-bordered validator" type="url" bind:value={$form.gitUrl} required />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="App ID">
    <input class="input input-bordered validator" type="text" bind:value={$form.appId} required />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Publisher ID">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.publisherId}
      required
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Client ID">
    <input
      class="input input-bordered validator"
      type="number"
      bind:value={$form.clientId}
      required
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Existing Version Code">
    <input
      class="input input-bordered validator"
      type="number"
      bind:value={$form.existingVersion}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Jenkins Build Url">
    <input class="input input-bordered validator" type="url" bind:value={$form.jenkinsBuildUrl} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Jenkins Publish Url">
    <input class="input input-bordered validator" type="url" bind:value={$form.jenkinsPublishUrl} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <input type="submit" class="btn btn-success" value="Update" />
</form>

<style>
  .input {
    width: 100%;
  }
</style>
