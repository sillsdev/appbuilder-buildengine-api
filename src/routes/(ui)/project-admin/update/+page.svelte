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
  import { Icons, getBucketIcon, getStatusIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { stringLimits } from '$lib/valibot';

  const id = $derived(page.url.searchParams.get('id')!);

  $effect(() => {
    $title = 'Edit Project';
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
  <div class="input-fields">
    <LabeledFormInput
      label="Status"
      class="md:w-1/2"
      input={{
        maxlength: stringLimits.project.status,
        icon: getStatusIcon($form.status).icon,
        iconClass: [getStatusIcon($form.status).color]
      }}
      bind:value={$form.status}
    />
    <LabeledFormInput
      label="Result"
      class="md:w-1/2"
      input={{
        maxlength: stringLimits.project.result,
        icon: getStatusIcon($form.result).icon,
        iconClass: [getStatusIcon($form.result).color]
      }}
      bind:value={$form.result}
    />
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
      label="Project Name"
      class="md:w-1/2"
      input={{ maxlength: stringLimits.project.project_name, icon: Icons.Edit }}
      bind:value={$form.project_name}
    />
    <LabeledFormInput
      label="Language Code"
      class="md:w-1/2"
      input={{ maxlength: stringLimits.project.language_code, icon: Icons.Language }}
      bind:value={$form.language_code}
    />
  </div>
  <LabeledFormInput
    label={getBucketIcon($form.url).title}
    input={{
      maxlength: stringLimits.project.url,
      type: 'url',
      icon: getBucketIcon($form.url).icon
    }}
    bind:value={$form.url}
  />
  <LabeledFormInput label="Error">
    <textarea
      class="textarea w-full min-h-36"
      bind:value={$form.error}
      maxlength={stringLimits.project.error}
    ></textarea>
  </LabeledFormInput>
  <div class="my-4">
    <CancelButton returnTo="/project-admin/view?id={id}" />
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
