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
    "SECRETS_BUCKET": "sil-prd-aps-secrets"
    "CHANNEL" : "alpha"
    "PROMOTE_FROM" : ""
    "ARTIFACTS_S3_DIR" : "s3://dem-aps-artifacts/dem/jobs/build_scriptureappbuilder_1/3"
    "SECRETS_DIR" : "/secrets"
    "ARTIFACTS_DIR" : "/artifacts"
    "SCRIPT_DIR"  : "/script"
    "SCRIPT_S3" : "s3://s3url/default"
    "TARGETS" : ""
    "RELEASE_NUMBER" : "0"

phases:
  install:
    commands:
  pre_build:
    commands:
      - OUTPUT_DIR="/${RELEASE_NUMBER}"
      - SECRETS_S3="s3://${SECRETS_BUCKET}/jenkins/publish/google_play_store/${PUBLISHER}"
      - mkdir "${SECRETS_DIR}"
      - mkdir "${ARTIFACTS_DIR}"
      - mkdir "${SCRIPT_DIR}"
      - mkdir "${OUTPUT_DIR}"
      - echo "${SCRIPT_S3}"
      - /root/.local/bin/aws s3 sync "${SECRETS_S3}" "${SECRETS_DIR}"
      - /root/.local/bin/aws s3 sync "${ARTIFACTS_S3_DIR}" "${ARTIFACTS_DIR}"
      - /root/.local/bin/aws s3 sync "${SCRIPT_S3}" "${SCRIPT_DIR}"
      - ls -l "${ARTIFACTS_DIR}"
  build:
    commands:
      - TARGETS="${TARGETS}" bash ${SCRIPT_DIR}/publish.sh
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