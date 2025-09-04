<script lang="ts">
	import '../app.css';
	import favicon from '$lib/assets/favicon.ico';

	let { children } = $props();
</script>

<svelte:head>
	<link rel="icon" href={favicon} />
	<title>SIL AppBuilder Administration</title>
</svelte:head>

{@render children?.()}
