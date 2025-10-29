-- CreateTable
CREATE TABLE "public"."build" (
    "id" SERIAL NOT NULL,
    "job_id" INTEGER NOT NULL,
    "status" VARCHAR(255),
    "result" VARCHAR(255),
    "error" VARCHAR(2083),
    "created" TIMESTAMP(6),
    "updated" TIMESTAMP(6),
    "channel" VARCHAR(255),
    "version_code" INTEGER,
    "artifact_url_base" VARCHAR(2083),
    "artifact_files" VARCHAR(4096),
    "build_guid" VARCHAR(255),
    "console_text_url" VARCHAR(255),
    "codebuild_url" VARCHAR(255),
    "targets" VARCHAR(255),
    "environment" TEXT,

    CONSTRAINT "build_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."client" (
    "id" SERIAL NOT NULL,
    "access_token" VARCHAR(255) NOT NULL,
    "prefix" VARCHAR(4) NOT NULL,
    "created" TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP,
    "updated" TIMESTAMP(6),

    CONSTRAINT "client_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."job" (
    "id" SERIAL NOT NULL,
    "request_id" VARCHAR(255) NOT NULL,
    "git_url" VARCHAR(2083) NOT NULL,
    "app_id" VARCHAR(255) NOT NULL,
    "publisher_id" VARCHAR(255) NOT NULL,
    "created" TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP,
    "updated" TIMESTAMP(6),
    "client_id" INTEGER,
    "existing_version_code" INTEGER DEFAULT 0,
    "jenkins_build_url" VARCHAR(1024),
    "jenkins_publish_url" VARCHAR(1024),

    CONSTRAINT "job_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."project" (
    "id" SERIAL NOT NULL,
    "status" VARCHAR(255),
    "result" VARCHAR(255),
    "error" VARCHAR(2083),
    "url" VARCHAR(1024),
    "user_id" VARCHAR(255),
    "group_id" VARCHAR(255),
    "app_id" VARCHAR(255),
    "project_name" VARCHAR(255),
    "language_code" VARCHAR(255),
    "publishing_key" VARCHAR(1024),
    "created" TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP,
    "updated" TIMESTAMP(6),
    "client_id" INTEGER,

    CONSTRAINT "project_pkey" PRIMARY KEY ("id")
);

-- CreateTable
CREATE TABLE "public"."release" (
    "id" SERIAL NOT NULL,
    "build_id" INTEGER NOT NULL,
    "status" VARCHAR(255),
    "created" TIMESTAMP(6) DEFAULT CURRENT_TIMESTAMP,
    "updated" TIMESTAMP(6),
    "result" VARCHAR(255),
    "error" VARCHAR(2083),
    "channel" VARCHAR(255) NOT NULL,
    "title" VARCHAR(30),
    "defaultLanguage" VARCHAR(255),
    "promote_from" VARCHAR(255),
    "build_guid" VARCHAR(255),
    "console_text_url" VARCHAR(255),
    "codebuild_url" VARCHAR(255),
    "targets" VARCHAR(255),
    "environment" TEXT,
    "artifact_url_base" VARCHAR(255),
    "artifact_files" VARCHAR(255),

    CONSTRAINT "release_pkey" PRIMARY KEY ("id")
);

-- CreateIndex
CREATE INDEX "idx_build_job_id" ON "public"."build"("job_id");

-- CreateIndex
CREATE INDEX "idx_accesS_token" ON "public"."client"("access_token");

-- CreateIndex
CREATE INDEX "idx_job_client_id" ON "public"."job"("client_id");

-- CreateIndex
CREATE INDEX "idx_request_id" ON "public"."job"("request_id");

-- CreateIndex
CREATE INDEX "idx_project_client_id" ON "public"."project"("client_id");

-- CreateIndex
CREATE INDEX "idx_release_build_id" ON "public"."release"("build_id");

-- AddForeignKey
ALTER TABLE "public"."build" ADD CONSTRAINT "fk_build_job_id" FOREIGN KEY ("job_id") REFERENCES "public"."job"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "public"."job" ADD CONSTRAINT "fk_job_client_id" FOREIGN KEY ("client_id") REFERENCES "public"."client"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "public"."project" ADD CONSTRAINT "fk_project_client_id" FOREIGN KEY ("client_id") REFERENCES "public"."client"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE "public"."release" ADD CONSTRAINT "fk_release_build_id" FOREIGN KEY ("build_id") REFERENCES "public"."build"("id") ON DELETE NO ACTION ON UPDATE NO ACTION;
