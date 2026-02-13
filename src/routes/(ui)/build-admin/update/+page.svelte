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
    $title = 'Update Build: ' + id;
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
  <li><a href="/build-admin" class="link">Builds</a></li>
  <li><a href="/build-admin/view?id={id}" class="link">{id}</a></li>
  <li>Update</li>
</Breadcrumbs>
<h1>{$title}</h1>

<form method="POST" use:enhance>
  <LabeledFormInput label="Job ID">
    <input
      class="input input-bordered validator"
      type="number"
      bind:value={$form.job_id}
      required
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Status">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.status}
      maxlength={stringLimits.build.status}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Build GUID">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.build_guid}
      maxlength={stringLimits.build.build_guid}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Result">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.result}
      maxlength={stringLimits.build.result}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Error">
    <input
      class="input input-bordered validator"
      type="url"
      bind:value={$form.error}
      maxlength={stringLimits.build.error}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Artifact URL Base">
    <input
      class="input input-bordered validator"
      type="url"
      bind:value={$form.artifact_url_base}
      required
      maxlength={stringLimits.build.artifact_url_base}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Artifact Files">
    <textarea
      class="textarea input-bordered validator"
      bind:value={$form.artifact_files}
      maxlength={stringLimits.build.artifact_files}
    ></textarea>
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Channel">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.channel}
      maxlength={stringLimits.build.channel}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Version Code">
    <input class="input input-bordered validator" type="number" bind:value={$form.version_code} />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Targets">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.targets}
      maxlength={stringLimits.build.targets}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Environment">
    <textarea class="textarea input-bordered validator" bind:value={$form.environment}></textarea>
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
