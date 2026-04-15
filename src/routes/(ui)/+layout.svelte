<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { PageData } from './$types';
  import { page } from '$app/state';
  import IconContainer from '$lib/components/IconContainer.svelte';

  interface Props {
    children: Snippet;
    data: PageData;
  }

  let { children, data }: Props = $props();

  function isUrlActive(route: string) {
    return page.url.pathname.startsWith(route);
  }

  let drawerToggle: HTMLInputElement;
  function closeDrawer() {
    if (drawerToggle.checked) {
      drawerToggle.click();
    }
  }
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
            <li>
              <a
                class="rounded-none"
                class:active-menu-item={isUrlActive('/client-admin')}
                href="/client-admin"
                onclick={closeDrawer}
              >
                Clients
              </a>
            </li>
            <li>
              <a
                class="rounded-none"
                class:active-menu-item={isUrlActive('/project-admin')}
                href="/project-admin"
                onclick={closeDrawer}
              >
                Projects
              </a>
            </li>
            <li>
              <a
                class="rounded-none"
                class:active-menu-item={isUrlActive('/job-admin')}
                href="/job-admin"
                onclick={closeDrawer}
              >
                Jobs
              </a>
            </li>
            <li>
              <a
                class="rounded-none"
                class:active-menu-item={isUrlActive('/build-admin')}
                href="/build-admin"
                onclick={closeDrawer}
              >
                Builds
              </a>
            </li>
            <li>
              <a
                class="rounded-none"
                class:active-menu-item={isUrlActive('/release-admin')}
                href="/release-admin"
                onclick={closeDrawer}
              >
                Releases
              </a>
            </li>
            <li>
              <a
                class="rounded-none"
                class:active-menu-item={isUrlActive('/queue-admin')}
                href="/queue-admin"
                onclick={closeDrawer}
              >
                Queues
              </a>
            </li>
          </div>
        </ul>
      </div>
    </div>

    <div class="drawer-content grow items-start justify-start">
      <header class="bg-primary text-primary-content">
        <nav class="navbar">
          <div class="navbar-start">
            <label
              for="primary-content-drawer"
              class="btn btn-ghost btn-circle p-1 drawer-button lg:hidden text-primary-content hover:text-base-content"
            >
              <IconContainer icon="mdi:hamburger-menu" width={24} />
            </label>
            <a class="btn text-xl btn-ghost" href="/">SIL Global</a>
          </div>
          <div class="navbar-end flex-none">
            <ul class="menu menu-horizontal px-1">
              <li><a href="/" class:bg-secondary={isUrlActive('/')}>Home</a></li>
              <li><a href="/about" class:bg-secondary={isUrlActive('/about')}>About</a></li>
              <li>
                <details class="dropdown dropdown-end">
                  <summary class="btn btn-primary btn-sm btn-square no-animation">
                    <IconContainer icon="mdi:user" width={24} />
                  </summary>
                  <ul class="dropdown-content menu menu-sm bg-base-100 text-base-content min-w-40">
                    <li>
                      <div class="btn btn-ghost max-w-full overflow-hidden">
                        <IconContainer icon="mdi:user" width={16} />
                        <span class="select-all">
                          {data.userEmail}
                        </span>
                      </div>
                    </li>
                    <li>
                      <a class="btn btn-ghost" href="/signout">
                        Sign Out
                        <IconContainer icon="mdi:logout" width="18" />
                      </a>
                    </li>
                  </ul>
                </details>
              </li>
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

  summary {
    &:after {
      display: none;
    }
  }

  .active-menu-item {
    border-left: 5px solid var(--color-accent); /* Adjust the border color and width to your preferences */
    font-weight: bold;
  }
</style>
