<script lang="ts">
  import { superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import LabeledFormInput from '$lib/components/LabeledFormInput.svelte';
  import { title } from '$lib/stores';
  import { stringLimits } from '$lib/valibot';

  $title = 'Create Grading Report';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  const { form, enhance, message } = superForm(data.form, {
    dataType: 'json'
  });
</script>

<Breadcrumbs>
  <li><a href="/" class="link">Home</a></li>
  <li><a href="/grading-admin" class="link">Grading Reports</a></li>
  <li>{$title}</li>
</Breadcrumbs>
<h1>{$title}</h1>

{#if $message}
  <div class="alert alert-error mb-2">{$message}</div>
{/if}

<form method="POST" use:enhance>
  <LabeledFormInput label="Project ID">
    <input
      class="input input-bordered validator"
      type="number"
      min="0"
      bind:value={$form.project_id}
      required
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Publisher">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.publisher_id}
      required
      maxlength={stringLimits.grading.publisher_id}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <input type="submit" class="btn btn-success" value="Create" />
</form>

<style>
  .input {
    width: 100%;
  }
</style>
