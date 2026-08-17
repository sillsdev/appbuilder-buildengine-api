-- AlterTable
ALTER TABLE "public"."client" ADD COLUMN     "description" TEXT,
ADD COLUMN     "development" BOOLEAN NOT NULL DEFAULT false;
