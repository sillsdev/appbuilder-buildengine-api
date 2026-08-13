<script lang="ts">
  interface Props {
    page: { page: number; size: number };
    count: number;
  }

  const { page, count }: Props = $props();
</script>

<p>
  Showing <b>
    {Math.min(page.page * page.size + 1, count)}-{Math.min((page.page + 1) * page.size, count)}
  </b>
  of
  <b>{count}</b>
  items
</p>
