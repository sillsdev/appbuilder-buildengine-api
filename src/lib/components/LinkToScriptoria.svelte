<script lang="ts">
  import type { Prisma } from '@prisma/client';
  import type { ClassValue } from 'svelte/elements';
  import { env } from '$env/dynamic/public';
  import { Icons } from '$lib/icons';
  import IconContainer from '$lib/icons/IconContainer.svelte';

  interface Props {
    client: Prisma.clientGetPayload<{ select: { development: true } }> | null;
    bucket: Prisma.projectGetPayload<{ select: { url: true } }>['url'];
    scope: 'project' | 'job' | 'build' | 'release';
    id: number;
    class?: ClassValue;
  }

  let { client, bucket, scope, id, class: classes }: Props = $props();
</script>

<a
  class={['link', classes]}
  target="_blank"
  href="{client?.development
    ? 'http://localhost:6173'
    : env.PUBLIC_SCRIPTORIA_URL}/link/{scope}/{id}{bucket &&
    `?bucket=${encodeURIComponent(bucket)}`}"
>
  View in Scriptoria <IconContainer icon={Icons.Open} width={16} />
</a>
