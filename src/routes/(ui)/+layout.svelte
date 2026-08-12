<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import Dropdown from '$lib/components/Dropdown.svelte';
  import { type IconType, Icons } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';

  interface Props {
    children: Snippet;
    data: PageData;
  }

  let { children, data }: Props = $props();

  function isUrlActive(route: string, exact = false) {
    return exact ? page.url.pathname === route : page.url.pathname.startsWith(route);
  }

  let drawerToggle: HTMLInputElement;
  function closeDrawer() {
    if (drawerToggle.checked) {
      drawerToggle.click();
    }
  }

  const links: { target: keyof PageData['count']; icon: IconType; title: string }[] = [
    { target: 'client', icon: Icons.User, title: 'Clients' },
    { target: 'project', icon: Icons.Project, title: 'Projects' },
    { target: 'job', icon: Icons.Product, title: 'Jobs' },
    { target: 'build', icon: Icons.Build, title: 'Builds' },
    { target: 'release', icon: Icons.Publish, title: 'Releases' }
  ];
</script>

<div class="flex flex-col h-full">
  <div class="flex grow overflow-auto drawer lg:drawer-open">
    <input
      id="primary-content-drawer"
      type="checkbox"
      class="drawer-toggle"
      bind:this={drawerToggle}
    />

    <div class="h-full drawer-side shrink-0 z-10">
      <label for="primary-content-drawer" class="drawer-overlay"></label>
      <div
        class="dark:border-gray-600 h-full mt-0 overflow-hidden w-full lg:w-72 lg:border-r min-[480px]:w-1/2 min-[720px]:w-1/3"
      >
        <ul class="menu menu-lg p-0 w-full bg-base-100 text-base-content h-full">
          <div class="min-h-full overflow-y-auto">
            <li class="dark:border-gray-600 border-y top-0 sticky z-10 bg-base-200 h-16 lg:hidden">
              <div class="flex flex-row flex-nowrap h-full pr-1">
                <b class="grow">SIL Global</b>
                <button
                  class="btn btn-ghost h-full lg:hidden"
                  type="button"
                  onclick={() => closeDrawer()}
                  title="close"
                >
                  <IconContainer icon={Icons.Close} width={16} class="opacity-80" />
                </button>
              </div>
            </li>
            {#each links as { target, icon, title }}
              <li>
                <a
                  class="rounded-none flex flex-row"
                  class:active-menu-item={isUrlActive(`/${target}-admin`)}
                  href="/{target}-admin"
                  onclick={closeDrawer}
                >
                  <IconContainer {icon} width={24} />
                  <span class="grow">{title}</span>
                  <i>{data.count[target]}</i>
                </a>
              </li>
            {/each}
            <li>
              <a
                class="rounded-none"
                class:active-menu-item={isUrlActive('/queue-admin')}
                href="/queue-admin"
                onclick={closeDrawer}
                target="_blank"
              >
                <IconContainer icon={Icons.Dashboard} width={24} />
                Queues
                <IconContainer icon={Icons.Open} width={18} />
              </a>
            </li>
          </div>
        </ul>
      </div>
    </div>

    <div class="drawer-content grow items-start justify-start flex flex-col">
      <header class="bg-primary text-primary-content w-full">
        <nav class="navbar">
          <div class="navbar-start w-2/5">
            <label
              for="primary-content-drawer"
              class="btn btn-ghost btn-circle p-1 drawer-button lg:hidden text-primary-content hover:text-base-content"
            >
              <IconContainer icon={Icons.Hamburger} width={24} />
            </label>
            <a class="btn text-xl btn-ghost px-2" href="/">SIL Global</a>
          </div>
          <div class="navbar-end flex-none w-3/5">
            <ul class="menu menu-horizontal px-1 items-center gap-x-1">
              <li><a href="/" class:bg-secondary={isUrlActive('/', true)}>Home</a></li>
              <li><a href="/about" class:bg-secondary={isUrlActive('/about')}>About</a></li>
              <Dropdown
                class={{
                  dropdown: 'dropdown-end',
                  label: 'btn-circle hover:bg-base-content/10'
                }}
              >
                {#snippet label()}
                  <IconContainer icon={Icons.User} width={24} />
                {/snippet}
                {#snippet content()}
                  <li>
                    <div class="btn btn-ghost max-w-full overflow-hidden">
                      <IconContainer icon={Icons.User} width={16} />
                      <span class="select-all">
                        {data.userEmail}
                      </span>
                    </div>
                  </li>
                  <li>
                    <a class="btn btn-ghost" href="/signout">
                      Sign Out
                      <IconContainer icon={Icons.Logout} width={18} />
                    </a>
                  </li>
                {/snippet}
              </Dropdown>
            </ul>
          </div>
        </nav>
      </header>
      <main class="w-full overflow-x-auto grow bg-base-100">
        <div class="mx-auto w-3xl md:w-5xl max-w-full px-10 text-base-content">
          {@render children?.()}
        </div>
      </main>
      <footer
        class="flex flex-col sm:flex-row bg-base-200 text-base-content w-full sm:place-items-center"
      >
        <div class="grow">© SIL Global {new Date().getFullYear()}</div>
        <div>
          Powered by&nbsp;
          <a href="https://kit.svelte.dev" rel="external" class="link">SvelteKit</a>
        </div>
      </footer>
    </div>
  </div>
</div>

<style>
  footer {
    border-top: 1px solid #ddd;
    padding: 20px;
  }

  .active-menu-item {
    border-left: 5px solid var(--color-accent); /* Adjust the border color and width to your preferences */
    font-weight: bold;
  }
</style>
