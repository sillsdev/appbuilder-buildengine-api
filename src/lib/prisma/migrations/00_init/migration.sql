-- CreateTable
CREATE TABLE `build` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `job_id` INTEGER NOT NULL,
    `status` VARCHAR(255) NULL,
    `result` VARCHAR(255) NULL,
    `error` VARCHAR(2083) NULL,
    `created` DATETIME(0) NULL,
    `updated` DATETIME(0) NULL,
    `channel` VARCHAR(255) NULL,
    `version_code` INTEGER NULL,
    `artifact_url_base` VARCHAR(2083) NULL,
    `artifact_files` VARCHAR(4096) NULL,
    `build_guid` VARCHAR(255) NULL,
    `console_text_url` VARCHAR(255) NULL,
    `codebuild_url` VARCHAR(255) NULL,
    `targets` VARCHAR(255) NULL,
    `environment` TEXT NULL,

    INDEX `fk_build_job_id`(`job_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `client` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `access_token` VARCHAR(255) NOT NULL,
    `prefix` VARCHAR(4) NOT NULL,
    `created` DATETIME(0) NULL,
    `updated` DATETIME(0) NULL,

    INDEX `idx_accesS_token`(`access_token`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `email_queue` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `to` VARCHAR(255) NOT NULL,
    `cc` VARCHAR(255) NULL,
    `bcc` VARCHAR(255) NULL,
    `subject` VARCHAR(255) NOT NULL,
    `text_body` TEXT NULL,
    `html_body` TEXT NULL,
    `attempts_count` BOOLEAN NULL,
    `last_attempt` DATETIME(0) NULL,
    `created` DATETIME(0) NULL,
    `error` VARCHAR(255) NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `job` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `request_id` VARCHAR(255) NOT NULL,
    `git_url` VARCHAR(2083) NOT NULL,
    `app_id` VARCHAR(255) NOT NULL,
    `publisher_id` VARCHAR(255) NOT NULL,
    `created` DATETIME(0) NULL,
    `updated` DATETIME(0) NULL,
    `client_id` INTEGER NULL,
    `existing_version_code` INTEGER NULL DEFAULT 0,
    `jenkins_build_url` VARCHAR(1024) NULL,
    `jenkins_publish_url` VARCHAR(1024) NULL,

    INDEX `fk_job_client_id`(`client_id`),
    INDEX `idx_request_id`(`request_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `migration` (
    `version` VARCHAR(180) NOT NULL,
    `apply_time` INTEGER NULL,

    PRIMARY KEY (`version`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `operation_queue` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `operation` VARCHAR(255) NOT NULL,
    `operation_object_id` INTEGER NULL,
    `operation_parms` VARCHAR(2048) NULL,
    `attempt_count` INTEGER NOT NULL,
    `last_attempt` DATETIME(0) NULL,
    `try_after` DATETIME(0) NULL,
    `start_time` DATETIME(0) NULL,
    `last_error` VARCHAR(2048) NULL,
    `created` DATETIME(0) NULL,
    `updated` DATETIME(0) NULL,

    INDEX `idx_start_time`(`start_time`),
    INDEX `idx_try_after`(`try_after`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `project` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `status` VARCHAR(255) NULL,
    `result` VARCHAR(255) NULL,
    `error` VARCHAR(2083) NULL,
    `url` VARCHAR(1024) NULL,
    `user_id` VARCHAR(255) NULL,
    `group_id` VARCHAR(255) NULL,
    `app_id` VARCHAR(255) NULL,
    `project_name` VARCHAR(255) NULL,
    `language_code` VARCHAR(255) NULL,
    `publishing_key` VARCHAR(1024) NULL,
    `created` DATETIME(0) NULL,
    `updated` DATETIME(0) NULL,
    `client_id` INTEGER NULL,

    INDEX `fk_project_client_id`(`client_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `release` (
    `id` INTEGER NOT NULL AUTO_INCREMENT,
    `build_id` INTEGER NOT NULL,
    `status` VARCHAR(255) NULL,
    `created` DATETIME(0) NULL,
    `updated` DATETIME(0) NULL,
    `result` VARCHAR(255) NULL,
    `error` VARCHAR(2083) NULL,
    `channel` VARCHAR(255) NOT NULL,
    `title` VARCHAR(30) NULL,
    `defaultLanguage` VARCHAR(255) NULL,
    `promote_from` VARCHAR(255) NULL,
    `build_guid` VARCHAR(255) NULL,
    `console_text_url` VARCHAR(255) NULL,
    `codebuild_url` VARCHAR(255) NULL,
    `targets` VARCHAR(255) NULL,
    `environment` TEXT NULL,
    `artifact_url_base` VARCHAR(255) NULL,
    `artifact_files` VARCHAR(255) NULL,

    INDEX `fk_release_build_id`(`build_id`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `build` ADD CONSTRAINT `fk_build_job_id` FOREIGN KEY (`job_id`) REFERENCES `job`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `job` ADD CONSTRAINT `fk_job_client_id` FOREIGN KEY (`client_id`) REFERENCES `client`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `project` ADD CONSTRAINT `fk_project_client_id` FOREIGN KEY (`client_id`) REFERENCES `client`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

-- AddForeignKey
ALTER TABLE `release` ADD CONSTRAINT `fk_release_build_id` FOREIGN KEY (`build_id`) REFERENCES `build`(`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

