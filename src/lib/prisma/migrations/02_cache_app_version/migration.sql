-- CreateTable
CREATE TABLE "public"."appVersion" (
    "appName" TEXT NOT NULL,
    "version" TEXT NOT NULL,
    "imageHash" TEXT NOT NULL,
    "created" TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updated" TIMESTAMP(6),

    CONSTRAINT "appVersion_pkey" PRIMARY KEY ("appName")
);
