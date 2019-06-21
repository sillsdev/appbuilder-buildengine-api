<?php
    /* @var $buildJobName string */
    /* @var $publishJobName string */
    /* @var $gitUrl string */
    /* @var $publisherName string */
?>
version: 0.2

env:
  variables:
    "PUBLISHER" : "wycliffeusa"
    "BUILD_NUMBER" : "0"
    "VERSION_CODE" : "42"
    "APP_BUILDER_SCRIPT_PATH" : "scripture-app-builder"
    "SECRETS_BUCKET": "sil-prd-aps-secrets"
    "SECRETS_DIR" : "/secrets"
    "PROJECT_DIR" : "/project"
    "SCRIPT_DIR"  : "/script"
    "PROJECT_S3" : "s3://s3url"
    "SCRIPT_S3" : "s3://s3url/default"
    "TARGETS" : ""
    
phases:
  install:
    commands:
  pre_build:
    commands:
      - OUTPUT_DIR="/${BUILD_NUMBER}"
      - SECRETS_S3="s3://${SECRETS_BUCKET}/jenkins/build"
      - mkdir "${SECRETS_DIR}"
      - mkdir "${OUTPUT_DIR}"
      - mkdir "${PROJECT_DIR}"
      - mkdir "${SCRIPT_DIR}"
      - echo "PROJECT_S3=${PROJECT_S3}"
      - aws s3 sync "${PROJECT_S3}" "${PROJECT_DIR}"
      - aws s3 sync "${SECRETS_S3}" "${SECRETS_DIR}"
      - aws s3 sync "${SCRIPT_S3}" "${SCRIPT_DIR}"
      - export GRADLE_OPTS="-Dorg.gradle.daemon=false"
  build:
    commands:
      - TARGETS="${TARGETS}" bash ${SCRIPT_DIR}/build.sh 
  #post_build:
    #commands:

artifacts:
  files:
    - $OUTPUT_DIR/**/*
    # - location
  #discard-paths: yes
  #base-directory: location
#cache:
  #paths:
    # - paths