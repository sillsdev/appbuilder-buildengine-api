-- CreateTable
CREATE TABLE "public"."gradingResult" (
    "id" SERIAL NOT NULL,
    "project_id" INTEGER NOT NULL,
    "status" VARCHAR(255),
    "result" VARCHAR(255),
    "error" VARCHAR(2083),
    "publisher_id" VARCHAR(255) NOT NULL,
    "project_url" VARCHAR(1024) NOT NULL,
    "lambda_request_id" VARCHAR(255),
    "report_url_base" VARCHAR(2083),
    "report_files" VARCHAR(255),
    "created" TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP,
    "updated" TIMESTAMP(6),

    CONSTRAINT "gradingResult_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "idx_grading_result_project_id" ON "public"."gradingResult"("project_id");

-- AddForeignKey
ALTER TABLE "public"."gradingResult" ADD CONSTRAINT "fk_grading_result_project_id" FOREIGN KEY ("project_id") REFERENCES "public"."project"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;
