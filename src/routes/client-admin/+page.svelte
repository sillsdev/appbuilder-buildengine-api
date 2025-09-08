<script lang="ts">
	import Breadcrumbs from '$lib/components/Breadcrumbs.svelte';
	import SortTable from '$lib/components/SortTable.svelte';
	import { getTimeDateString } from '$lib/utils/time';
	import type { PageData } from './$types';

    interface Props {
        data: PageData;
    }

    let { data }: Props = $props();
</script>

<div class="w-full">
	<Breadcrumbs>
		<li><a href="/" class="link">Home</a></li>
		<li>Clients</li>
	</Breadcrumbs>
    <h1>Clients</h1>

	<SortTable
		data={data.clients}
		columns={[{
            id: 'index',
            header: '#'
          },
          {
            id: 'id',
            header: 'Id',
            compare: () => 0
          },
          {
            id: 'token',
            header: 'Access Token'
          },
          {
            id: 'prefix',
            header: 'Prefix'
          },
          {
            id: 'created',
            header: 'Created'
          },
		  {
			id: 'updated',
			header: 'Updated'
		  },
		  {
			id: 'menu',
			header: ''
		  }
		]}
		serverSide={true}
		onSort={() => {}}
	>
		{#snippet row(client, index)}
			<tr>
				<td>{index + 1}</td>
				<td>{client.id}</td>
				<td>{client.access_token}</td>
				<td>{client.prefix}</td>
				<td>{getTimeDateString(client.created)}</td>
				<td>{getTimeDateString(client.updated)}</td>
				<td></td>
			</tr>
		{/snippet}
	</SortTable>
</div>
