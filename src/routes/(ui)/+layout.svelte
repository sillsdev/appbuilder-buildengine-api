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
    return page.url.pathname === route;
  }
</script>

<header class="bg-primary text-primary-content">
  <nav class="navbar">
    <div class="flex-1">
      <a class="btn text-xl btn-ghost" href="/">SIL Global</a>
    </div>
    <div class="flex-none">
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
</style>
