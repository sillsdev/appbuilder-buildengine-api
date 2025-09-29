export function ErrorResponse(status: number, message: string) {
  console.log('in error response');
  return new Response(JSON.stringify({ status, message }), { status });
}
