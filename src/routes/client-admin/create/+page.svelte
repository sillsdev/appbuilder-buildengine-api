<script lang="ts">
  import { superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import LabeledFormInput from '$lib/components/LabeledFormInput.svelte';
  import { title } from '$lib/stores';

  $title = 'Create Client';

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
  <li><a href="/client-admin" class="link">Clients</a></li>
  <li>{$title}</li>
</Breadcrumbs>
<h1>{$title}</h1>

<form method="POST" use:enhance>
  <LabeledFormInput label="Access Token">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.accessToken}
      required
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Prefix">
    <input class="input input-bordered validator" type="text" bind:value={$form.prefix} required />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <input type="submit" class="btn btn-success" value="Create" />
</form>

<style>
  .input {
    width: 100%;
  }
</style>
