import { InvokeCommand, LambdaClient } from '@aws-sdk/client-lambda';
import { SpanStatusCode, trace } from '@opentelemetry/api';
import { AWSVars } from './vars';

const tracer = trace.getTracer('Lambda');

export class Lambda {
  private client;

  public constructor() {
    this.client = new LambdaClient({ region: AWSVars.artifactsRegion() });
  }

  public async invokeJson<TPayload extends Record<string, unknown>>(
    functionName: string,
    payload: TPayload
  ) {
    return tracer.startActiveSpan('Lambda - Invoke', async (span) => {
      span.setAttributes({
        'lambda.function-name': functionName,
        'lambda.payload': JSON.stringify(payload)
      });
      try {
        const startTime = Date.now();
        const result = await this.client.send(
          new InvokeCommand({
            FunctionName: functionName,
            InvocationType: 'RequestResponse',
            Payload: Buffer.from(JSON.stringify(payload))
          })
        );
        span.setAttributes({
          'lambda.status-code': result.StatusCode,
          'lambda.executed-version': result.ExecutedVersion ?? '',
          'lambda.function-error': result.FunctionError ?? '',
          'lambda.executionTimeMs': Date.now() - startTime
        });

        const body = result.Payload ? Buffer.from(result.Payload).toString('utf8') : '';
        const parsed = body ? this.parsePayload(body) : null;
        return {
          requestId: result.$metadata.requestId ?? null,
          payload: parsed
        };
      } catch (e) {
        span.recordException(e as Error);
        span.setStatus({
          code: SpanStatusCode.ERROR,
          message: (e as Error).message
        });
        throw e;
      } finally {
        span.end();
      }
    });
  }

  private parsePayload(body: string) {
    try {
      return JSON.parse(body) as Record<string, unknown>;
    } catch {
      throw new Error(`Lambda returned invalid JSON: ${body}`);
    }
  }
}
