<script lang="ts">
  import { superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import LabeledFormInput from '$lib/components/LabeledFormInput.svelte';
  import { title } from '$lib/stores';
  import { stringLimits } from '$lib/valibot';

  const id = $derived(page.url.searchParams.get('id')!);

  $effect(() => {
    $title = 'Update Project: ' + id;
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
  <li><a href="/project-admin" class="link">Projects</a></li>
  <li><a href="/project-admin/view?id={id}" class="link">{id}</a></li>
  <li>Update</li>
</Breadcrumbs>
<h1>{$title}</h1>

<form method="POST" use:enhance>
  <LabeledFormInput label="Status">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.status}
      maxlength={stringLimits.project.status}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Result">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.result}
      maxlength={stringLimits.project.result}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Error">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.error}
      maxlength={stringLimits.project.error}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Url">
    <input
      class="input input-bordered validator"
      type="url"
      bind:value={$form.url}
      maxlength={stringLimits.project.url}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="User ID">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.user_id}
      maxlength={stringLimits.project.user_id}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Group ID">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.group_id}
      maxlength={stringLimits.project.group_id}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="App ID">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.app_id}
      maxlength={stringLimits.project.app_id}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Client ID">
    <input class="input input-bordered validator" type="number" bind:value={$form.client_id} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Project Name">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.project_name}
      maxlength={stringLimits.project.project_name}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Language Code">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.language_code}
      maxlength={stringLimits.project.language_code}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Publishing Key">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.publishing_key}
      maxlength={stringLimits.project.publishing_key}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <input type="submit" class="btn btn-success" value="Update" />
</form>

<style>
  .input {
    width: 100%;
  }
</style>
