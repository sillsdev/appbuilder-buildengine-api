<script lang="ts">
  import type { PageData } from './$types';
  import { type IconType, Icons, getAppIcon, getStatusIcon } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';
  import { title } from '$lib/stores';
  import type { ApplicationType } from '$lib/valibot';

  interface Props {
    data: PageData;
  }

  let { data }: Props = $props();

  $title = 'SIL AppBuilder Administration';

  const cards: {
    target: keyof PageData['aggregate'];
    title: string;
    icon: IconType;
  }[] = [
    { target: 'project', title: 'Projects', icon: Icons.Project },
    { target: 'job', title: 'Jobs', icon: Icons.Product },
    { target: 'build', title: 'Builds', icon: Icons.Build },
    { target: 'release', title: 'Releases', icon: Icons.Publish }
  ];
</script>

<div class="pb-2">
  <div class="py-12 text-center">
    <h1 class="pb-0 pl-0">App Publishing Service</h1>
    <h1 class="pb-0 pl-0">Administration</h1>
  </div>
  <div id="cards" class="flex flex-col flex-wrap md:flex-row items-center gap-2">
    {#each cards as { target, title, icon }}
      {@const getIcon = target === 'project' || target === 'job' ? getAppIcon : getStatusIcon}
      <div class="w-full p-2 pt-0 border rounded-md h-56 overflow-y-auto">
        <div class="top-0 sticky z-[5] bg-base-100 w-full">
          <h2 class="pl-0 w-full">
            <IconContainer {icon} width={24} />
            <a class="link" href="/{target}-admin">
              {title}
            </a>
          </h2>
        </div>
        <table class="table table-xs">
          <tbody>
            {#each data.aggregate[target] as entry}
              {@const result =
                ('result' in entry ? (entry.result ?? 'PENDING') : entry.app_id) || 'UNKNOWN'}
              {@const icon = getIcon(result as ApplicationType)}
              <tr>
                <td>
                  <IconContainer
                    icon={typeof icon === 'string' ? icon : icon.icon}
                    width={24}
                    class={[typeof icon !== 'string' && icon.color]}
                  />
                  {result}
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
