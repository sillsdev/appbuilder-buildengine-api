<script lang="ts">
	import '../app.css';
	import favicon from '$lib/assets/favicon.ico';
	import { page } from '$app/state';

	let { children } = $props();

	function isUrlActive(route: string) {
		return page.url.pathname === route;
	}
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
	<title>SIL AppBuilder Administration</title>
</svelte:head>

<header class="bg-primary text-primary-content">
	<nav class="navbar">
		<div class="flex-1">
			<a class="btn text-xl btn-ghost" href="/">SIL International</a>
		</div>
		<div class="flex-none">
			<ul class="menu menu-horizontal px-1">
				<li><a href="/" class:bg-secondary={isUrlActive("/")}>Home</a></li>
				<li><a href="/about" class:bg-secondary={isUrlActive("/about")}>About</a></li>
				<li><a href="/contact" class:bg-secondary={isUrlActive("/contact")}>Contact</a></li>
			</ul>
		</div>
	</nav>
</header>
<main class="mx-auto w-3xl max-w-full bg-base-100 px-10 text-base-content md:w-5xl grow">
	{@render children?.()}
</main>
<footer class="flex flex-col sm:flex-row bg-base-200 text-base-content w-full sm:place-items-center">
	<div class="grow">© SIL International {new Date().getFullYear()}</div>
	<div>
		Powered by&nbsp;<a href="https://kit.svelte.dev" rel="external" class="link">SvelteKit</a>
	</div>
</footer>

<style>
	footer {
		border-top: 1px solid #ddd;
		padding: 20px;
	}
</style>
