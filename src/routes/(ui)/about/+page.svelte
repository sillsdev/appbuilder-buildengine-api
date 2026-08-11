<script lang="ts">
  import type { PageData } from './$types';
  import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
  import Tooltip from '$lib/components/Tooltip.svelte';
  import { getAppIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import { getRelativeTime, getTimeDateString } from '$lib/utils/time';
  import type { ApplicationType } from '$lib/valibot';

  $title = 'About';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  const updated = getRelativeTime(data.appVersions.at(0)?.updated ?? null);
</script>

<div class="w-full">
  <Breadcrumbs>
    <li><a href="/" class="link">Home</a></li>
    <li>App Publishing Service</li>
  </Breadcrumbs>
  <div class="prose">
    <img src="/sil_logo_glyph.png" width="139" height="75" alt="SIL Global Logo" />
    <h1 class="pl-0">App Publishing Service</h1>
    <h3 class="pl-0">
      Copyright 2011-{new Date().getFullYear()}
      <a href="https://global.sil.org" class="link">SIL Global</a>
    </h3>
    <h3 class="pl-0">Credits</h3>
    <p>
      Chris Hubbard&nbsp;(
      <a href="https://global.sil.org" class="link">SIL Global</a>
      ) :&nbsp;Dev Lead, Programming
    </p>
    <p>
      David Moore&nbsp;(
      <a href="https://global.sil.org" class="link">SIL Global</a>
      ) :&nbsp;Programming
    </p>
    <p>
      Aidan Jones&nbsp;(
      <a href="https://global.sil.org" class="link">SIL Global</a>
      ) :&nbsp;Programming
    </p>
    <h3 class="pl-0">AppBuilder Versions</h3>
    <p>
      <b>Hash:</b>
      {data.appVersions[0].imageHash}
    </p>
    <p>
      <b>Updated:</b>
      <Tooltip tip={getTimeDateString(data.appVersions.at(0)?.updated ?? null)}>{$updated}</Tooltip>
    </p>
    <div class="my-2 p-2 border w-fit rounded-md">
      <table>
        <tbody>
          {#each data.appVersions.toSorted((a, b) => a.appName.localeCompare(b.appName)) as version}
            <tr>
              <td>
                <IconContainer
                  icon={getAppIcon(version.appName as ApplicationType)}
                  width={20}
                  class="mr-2"
                />
              </td>
              <td><b>{version.appName}:</b></td>
              <td class="pl-1">{version.version}</td>
            </tr>
          {/each}
        </tbody>
      </table>
    </div>
  </div>
</div>
