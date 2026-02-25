export function ErrorResponse(status: number, message: string, headers?: HeadersInit) {
  return new Response(JSON.stringify({ status, message }), { status, headers });
}

export type Logger = (msg: string) => void;
export const defaultLogger: Logger = (msg) => console.log(msg);
