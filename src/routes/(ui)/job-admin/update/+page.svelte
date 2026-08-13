<script lang="ts">
  import { superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import AppTypeSelector from '$lib/components/AppTypeSelector.svelte';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import CancelButton from '$lib/components/CancelButton.svelte';
  import LabeledFormInput from '$lib/components/LabeledFormInput.svelte';
  import SelectWithIcon from '$lib/components/SelectWithIcon.svelte';
  import SubmitButton from '$lib/components/SubmitButton.svelte';
  import { Icons, getBucketIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { stringLimits } from '$lib/valibot';

  const id = $derived(page.url.searchParams.get('id')!);

  $effect(() => {
    $title = 'Edit Job';
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
  <LabeledFormInput
    label="Request ID"
    input={{ maxlength: stringLimits.job.request_id, icon: Icons.Product }}
    bind:value={$form.request_id}
  />
  <LabeledFormInput
    label={getBucketIcon($form.git_url).title}
    input={{
      maxlength: stringLimits.job.git_url,
      type: 'url',
      icon: getBucketIcon($form.git_url).icon
    }}
    bind:value={$form.git_url}
  />
  <div class="input-fields">
    <LabeledFormInput label="App ID" class="md:w-1/2">
      <AppTypeSelector bind:value={$form.app_id} />
      <span class="validator-hint">&nbsp;</span>
    </LabeledFormInput>
    <LabeledFormInput label="Client" class="md:w-1/2">
      <SelectWithIcon
        bind:value={$form.client_id}
        icon={Icons.User}
        items={data.clients.map((c) => ({ id: c.id, name: c.prefix }))}
        class="validator w-full"
        attr={{ name: 'client_id' }}
      >
        {#snippet extra()}
          <option value={null}><IconContainer icon={Icons.User} width={20} />Default Client</option>
        {/snippet}
      </SelectWithIcon>
      <span class="validator-hint">&nbsp;</span>
    </LabeledFormInput>
    <LabeledFormInput
      label="Publisher ID"
      class="md:w-1/2"
      input={{ maxlength: stringLimits.job.publisher_id, icon: Icons.Store }}
      bind:value={$form.publisher_id}
    />
    <LabeledFormInput
      label="Existing Version Code"
      class="md:w-1/2"
      input={{ type: 'number', icon: Icons.Version }}
      bind:value={$form.existing_version_code}
    />
  </div>
  <div class="my-4">
    <CancelButton returnTo="/job-admin/view?id={id}" />
    <SubmitButton />
  </div>
</form>

<style>
  .input-fields {
    display: flex;
    flex-direction: column;
    width: 100%;
  }

  @media (width >= 48rem /* 768px */) {
    .input-fields {
      flex-wrap: wrap;
      flex-direction: row;
    }
    .input-fields :global(label):nth-child(odd) {
      padding-right: calc(var(--spacing) * 1);
    }
    .input-fields :global(label):nth-child(even) {
      padding-left: calc(var(--spacing) * 1);
    }
    .input-fields :global(label):nth-child(odd):last-child {
      padding-right: 0px;
      width: 100%;
    }
  }
</style>
