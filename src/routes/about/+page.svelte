<script>
	import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
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
			Copyright 2011-{new Date().getFullYear()} <a href="http://sil.org" class="link">SIL Global</a>
		</h3>
		<h3 class="pl-0">Credits</h3>
		<p>
			Chris Hubbard&nbsp;(<a href="http://sil.org" class="link">SIL Global</a>) :&nbsp;Dev
			Lead, Programming
		</p>
		<p>
			David Moore&nbsp;(<a href="http://sil.org" class="link">SIL Global</a>)
			:&nbsp;Programming
		</p>
		<p>
			Rick MacLean&nbsp;(<a href="http://sil.org" class="link">SIL Global</a>) :&nbsp;Dev Ops
		</p>
		<p>
			Aidan Jones&nbsp;(<a href="http://sil.org" class="link">SIL Global</a>) :&nbsp;Programming
		</p>
	</div>
</div>
