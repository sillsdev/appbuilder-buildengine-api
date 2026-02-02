<script lang="ts">
  import { onDestroy } from 'svelte';
  import { browser } from '$app/environment';
  import ScriptoriaIcon from '$lib/icons/ScriptoriaIcon.svelte';
  import type { PageData } from './$types';
  import { env } from '$env/dynamic/public';

  interface Props {
    data: PageData;
  }

  let {
    data
  }: Props = $props();

  let timeout: ReturnType<typeof setInterval> | null = null;
  if (!data.serviceAvailable && browser) {
    timeout = setInterval(() => {
      // Reload every 2 seconds to check if the service is back up
      if (data.serviceAvailable) {
        if (timeout !== null) clearInterval(timeout);
      } else {
        location.reload();
      }
    }, 2000);
  }

  onDestroy(() => {
    if (timeout !== null) {
      clearInterval(timeout);
      timeout = null;
    }
  });
</script>

<div class="card shadow-xl bg-white border p-4">
  <div class="w-full flex justify-center">
    <div class="w-10"></div>
    <ScriptoriaIcon size="128" />
  </div>
  <h1 class="text-center mx-4 py-0 pt-2 text-black">Welcome to BuildEngine</h1>
  <div class="flex flex-col justify-evenly space-y-2">
    <p class="text-black m-4 text-center w-80">
      BuildEngine serves as an interface between Scriptoria and the AWS resources it uses.
    </p>
    {#if data.serviceAvailable}
      <form method="POST" action="?/login" class="w-full">
        <button class="btn btn-primary w-full">Login with Scriptoria</button>
      </form>
    {:else}
      <p class="text-red-600 text-center">
        BuildEngine is currently unavailable
      </p>
    {/if}
  </div>
</div>

<div class="my-4 text-center text-white">
  <span>
    Like to use our service?
  </span>
  <a class="font-bold" href="{env.PUBLIC_SCRIPTORIA_URL}">
    Visit Scriptoria
  </a>
</div>
