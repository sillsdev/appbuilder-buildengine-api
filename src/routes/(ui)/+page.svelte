<script lang="ts">
  import type { PageData } from './$types';
  import { title } from '$lib/stores';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  $title = 'SIL AppBuilder Administration';

  const cards: {
    target: keyof PageData['aggregate'];
    title: string;
  }[] = [
    { target: 'project', title: 'Projects' },
    { target: 'job', title: 'Jobs' },
    { target: 'build', title: 'Builds' },
    { target: 'release', title: 'Releases' }
  ];
</script>

<div class="pb-2">
  <div class="py-12 text-center">
    <h1 class="pb-0 pl-0">App Publishing Service</h1>
    <h1 class="pb-0 pl-0">Administration</h1>
  </div>
  <div id="cards" class="flex flex-col flex-wrap lg:flex-row items-center gap-2">
    {#each cards as { target, title }}
      <div class="w-full p-2 pt-0 border rounded-md h-56 overflow-y-auto">
        <div class="top-0 sticky z-[5] bg-base-100 w-full">
          <h2 class="pl-0 w-full">
            <a class="link" href="/{target}-admin">
              {title}
            </a>
          </h2>
        </div>
        <table class="table table-xs">
          <thead>
            <tr>
              <th></th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            {#each data.aggregate[target] as entry}
              <tr>
                <td>
                  {('result' in entry ? entry.result : entry.app_id) || 'UNKNOWN'}
                </td>
                <td>
                  {entry._count}
                </td>
              </tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/each}
  </div>
</div>

<style>
  @media (width >= 48rem /* 768px */) {
    #cards > div {
      max-width: calc(50% - var(--spacing) * 2);
    }
  }
</style>
