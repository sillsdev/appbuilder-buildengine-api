-- CreateTable
CREATE TABLE "public"."gradingResult" (
    "uuid" UUID NOT NULL,
    "project_id" INTEGER NOT NULL,
    "status" VARCHAR(255),
    "result" VARCHAR(2000),
    "publisher_id" VARCHAR(255) NOT NULL,
    "lambda_request_id" VARCHAR(255),
    "created" TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP,
    "updated" TIMESTAMP(6),

    CONSTRAINT "gradingResult_pkey" PRIMARY KEY ("uuid")
);

-- CreateIndex
CREATE INDEX "idx_grading_result_project_id" ON "public"."gradingResult"("project_id");

-- AddForeignKey
ALTER TABLE "public"."gradingResult" ADD CONSTRAINT "fk_grading_result_project_id" FOREIGN KEY ("project_id") REFERENCES "public"."project"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;
