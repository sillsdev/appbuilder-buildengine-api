import type { Prisma } from '@prisma/client';
import type { Job } from 'bullmq';
import { Lambda } from '../aws/lambda';
import { S3 } from '../aws/s3';
import { AWSVars } from '../aws/vars';
import type { BullMQ } from '../bullmq';
import { prisma } from '../prisma';
import { Grading } from '$lib/models/grading';
import { trimStrings } from '$lib/valibot';

export async function generate(job: Job<BullMQ.Grading.Generate>): Promise<unknown> {
  try {
    const grading = await findGradingResult(job.data.gradingResultId);
    if (!grading) {
      throw new Error(`Grading result ${job.data.gradingResultId} not found`);
    }

    const functionName = AWSVars.gradingLambdaFunctionName();
    if (!functionName) {
      throw new Error('BUILD_ENGINE_GRADING_LAMBDA_FUNCTION_NAME is not configured');
    }

    await updateGrading(grading.id, { status: Grading.Status.Active });
    job.updateProgress(10);

    const bucket = AWSVars.artifacts();
    const prefix = Grading.reportPrefix(grading.id);
    const htmlKey = `${prefix}/report.html`;
    const jsonKey = `${prefix}/report.json`;

    const lambda = new Lambda();
    const lambdaResult = await lambda.invokeJson(functionName, {
      reportId: grading.id,
      project: {
        id: grading.project.id,
        appId: grading.project.app_id,
        name: grading.project.project_name,
        languageCode: grading.project.language_code,
        s3Url: grading.project_url
      },
      // Used for secrets
      publisherId: grading.publisher_id
    });
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

    const updated = await updateGrading(grading.id, {
      status: Grading.Status.Completed,
      result: Grading.Result.Success,
      lambda_request_id: lambdaResult.requestId,
      report_url_base: Grading.reportUrlBase(grading.id, bucket),
      report_files: Grading.reportFiles.join(',')
    });
    job.updateProgress(100);
    return updated;
  } catch (e) {
    job.log(`${e}`);
    await updateGrading(job.data.gradingResultId, {
      status: Grading.Status.Completed,
      result: Grading.Result.Failure,
      error: String(e)
    });
    throw e;
  }
}

async function findGradingResult(id: number) {
  return await prisma.gradingResult.findUnique({
    where: { id },
    include: {
      project: true
    }
  });
}

async function updateGrading(id: number, data: Prisma.gradingResultUpdateInput) {
  const trimmed = trimStrings(data, 'grading');
  return await prisma.gradingResult.update({
    where: { id },
    data: trimmed
  });
}
