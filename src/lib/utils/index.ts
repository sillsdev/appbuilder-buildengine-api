export function ErrorResponse(status: number, message: string, headers?: HeadersInit) {
  return new Response(JSON.stringify({ status, message }), { status, headers });
}
