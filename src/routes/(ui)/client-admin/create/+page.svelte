<script lang="ts">
  import { superForm } from 'sveltekit-superforms';
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import CancelButton from '$lib/components/CancelButton.svelte';
  import InputWithMessage from '$lib/components/InputWithMessage.svelte';
  import LabeledFormInput from '$lib/components/LabeledFormInput.svelte';
  import PasswordInput from '$lib/components/PasswordInput.svelte';
  import SubmitButton from '$lib/components/SubmitButton.svelte';
  import { Icons } from '$lib/icons';
  import { title } from '$lib/stores';
  import { stringLimits } from '$lib/valibot';

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
  <div class="input-fields">
    <LabeledFormInput
      label="Prefix"
      class="md:w-24!"
      input={{
        maxlength: stringLimits.client.prefix,
        icon: Icons.Folder,
        required: true,
        size: 4
      }}
      bind:value={$form.prefix}
    />

    <div class="complement-width">
      <PasswordInput
        label="Access Token"
        input={{
          maxlength: stringLimits.client.access_token,
          icon: Icons.Key,
          required: true
        }}
        bind:value={$form.access_token}
      />
    </div>
  </div>
  <InputWithMessage
    title={{ label: 'Development' }}
    message={{ label: 'This client is used for local development.' }}
    class="mb-4"
  >
    <input type="checkbox" class="checkbox checkbox-warning" bind:checked={$form.development} />
  </InputWithMessage>
  <LabeledFormInput label="Description">
    <textarea class="textarea w-full min-h-36" bind:value={$form.description}></textarea>
  </LabeledFormInput>
  <div class="my-4">
    <CancelButton returnTo="/client-admin" />
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

    .complement-width {
      width: calc(100% - var(--spacing) * 24) /* 6rem = 96px */;
    }
  }
</style>
