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
    $title = 'Update Client: ' + id;
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
  <li><a href="/client-admin" class="link">Clients</a></li>
  <li><a href="/client-admin/view?id={id}" class="link">{id}</a></li>
  <li>Update</li>
</Breadcrumbs>
<h1>{$title}</h1>

<form method="POST" use:enhance>
  <LabeledFormInput label="Access Token">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.access_token}
      required
      maxlength={stringLimits.client.access_token}
    />
    <span class="validator-hint">&nbsp;</span>
  </LabeledFormInput>
  <LabeledFormInput label="Prefix">
    <input
      class="input input-bordered validator"
      type="text"
      bind:value={$form.prefix}
      required
      maxlength={stringLimits.client.prefix}
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
