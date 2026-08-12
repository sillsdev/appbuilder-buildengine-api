import type { Prisma } from '@prisma/client';
import type { Job } from 'bullmq';
import { Lambda } from '../aws/lambda';
import { S3 } from '../aws/s3';
import { AWSVars } from '../aws/vars';
import type { BullMQ } from '../bullmq';
import { Grading } from '../models/grading';
import { prisma } from '../prisma';
import { trimStrings } from '$lib/valibot';

export async function generate(job: Job<BullMQ.Grading.Generate>): Promise<unknown> {
  try {
    const grading = await findGradingResult(job.data.gradingResultUUID);
    if (!grading) {
      throw new Error(`Grading result ${job.data.gradingResultUUID} not found`);
    }

    const functionName = AWSVars.gradingLambdaFunctionName();
    if (!functionName) {
      throw new Error('BUILD_ENGINE_GRADING_LAMBDA_FUNCTION_NAME is not configured');
    }

    await updateGrading(grading.uuid, { status: Grading.Status.Active });
    job.updateProgress(10);

    const prefix = Grading.reportPrefix(grading.uuid);
    const htmlKey = `${prefix}/report.html`;
    const jsonKey = `${prefix}/report.json`;

    const lambda = new Lambda();
    const payload = {
      reportId: grading.uuid,
      project: {
        id: grading.project.id,
        appId: grading.project.app_id,
        name: grading.project.project_name,
        languageCode: grading.project.language_code,
        s3Url: grading.project.url
      },
      // Used for secrets
      publisherId: grading.publisher_id,
      reportLanguage: 'en'
    };
    job.log(`Invoking grading lambda with payload: ${JSON.stringify(payload)}`);
    const lambdaResult = await lambda.invokeJson(functionName, payload);
    await updateGrading(grading.uuid, {
      lambda_request_id: lambdaResult.requestId,
      result: JSON.stringify(lambdaResult.payload)
    });
    job.log(`Lambda result: ${JSON.stringify(lambdaResult)}`);
    if (lambdaResult.payload && typeof lambdaResult.payload === 'object') {
      if (lambdaResult.payload.FunctionError) {
        throw new Error(`Lambda function error: ${JSON.stringify(lambdaResult.payload)}`.trim());
      }
    }
    job.updateProgress(75);

    const s3 = new S3();
    const [htmlExists, jsonExists] = await Promise.all([
      s3.objectExists(htmlKey),
      s3.objectExists(jsonKey)
    ]);
    if (!htmlExists || !jsonExists) {
      throw new Error(
        `Grading Lambda did not create expected report objects: ${[
          htmlExists ? null : htmlKey,
          jsonExists ? null : jsonKey
        ]
          .filter(Boolean)
          .join(', ')}`
      );
    }

    const updated = await updateGrading(grading.uuid, {
      status: Grading.Status.Success,
      result: JSON.stringify(lambdaResult.payload)
    });
    job.updateProgress(100);
    return updated;
  } catch (e) {
    job.log(`${e}`);
    await updateGrading(job.data.gradingResultUUID, {
      status: Grading.Status.Failure,
      result: String(e)
    });
    throw e;
  }
}

async function findGradingResult(uuid: string) {
  return await prisma.gradingResult.findUnique({
    where: { uuid },
    include: {
      project: true
    }
  });
}

async function updateGrading(uuid: string, data: Prisma.gradingResultUpdateInput) {
  const trimmed = trimStrings(data, 'grading');
  return await prisma.gradingResult.update({
    where: { uuid },
    data: trimmed
  });
}
