<script lang="ts">
  import { superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import LabeledFormInput from '$lib/components/LabeledFormInput.svelte';
  import { title } from '$lib/stores';

  const id = $derived(page.url.searchParams.get('id')!);

  $effect(() => {
    $title = 'Update Release: ' + id;
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
  <li><a href="/release-admin" class="link">Releases</a></li>
  <li><a href="/release-admin/view?id={id}" class="link">{id}</a></li>
  <li>Update</li>
</Breadcrumbs>
<h1>{$title}</h1>

<form method="POST" use:enhance>
  <LabeledFormInput label="Build ID">
    <input
      class="input input-bordered validator"
      type="number"
      bind:value={$form.build_id}
      required
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Status">
    <input class="input input-bordered validator" type="text" bind:value={$form.status} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Result">
    <input class="input input-bordered validator" type="text" bind:value={$form.result} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Error">
    <input class="input input-bordered validator" type="url" bind:value={$form.error} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Channel">
    <input class="input input-bordered validator" type="text" bind:value={$form.channel} required />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Title">
    <input class="input input-bordered validator" type="text" bind:value={$form.title} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Default Language">
    <input class="input input-bordered validator" type="text" bind:value={$form.defaultLanguage} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Build GUID">
    <input class="input input-bordered validator" type="text" bind:value={$form.build_guid} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Promote From">
    <input class="input input-bordered validator" type="text" bind:value={$form.promote_from} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Targets">
    <input class="input input-bordered validator" type="text" bind:value={$form.targets} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Environment">
    <textarea class="textarea input-bordered validator" bind:value={$form.environment}></textarea>
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Artifact URL Base">
    <input
      class="input input-bordered validator"
      type="url"
      bind:value={$form.artifact_url_base}
      required
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Artifact Files">
    <textarea
      class="textarea input-bordered validator"
      bind:value={$form.artifact_files}
    ></textarea>
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <input type="submit" class="btn btn-success" value="Update" />
</form>

<style>
  .input,
  .textarea {
    width: 100%;
  }
</style>
