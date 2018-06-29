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
    
phases:
  install:
    commands:
  pre_build:
    commands:
      - SECRETS_S3="s3://${SECRETS_BUCKET}/jenkins/publish/google_play_store/${PUBLISHER}"
      - mkdir "${SECRETS_DIR}"
      - mkdir "${ARTIFACTS_DIR}"
      - /root/.local/bin/aws s3 sync "${SECRETS_S3}" "${SECRETS_DIR}"
      - /root/.local/bin/aws s3 sync "${ARTIFACTS_S3_DIR}" "${ARTIFACTS_DIR}"
      - ls -l "${ARTIFACTS_DIR}"
  build:
    commands:
      - PAJ="${SECRETS_DIR}/playstore_api.json"
      - cd $ARTIFACTS_DIR
      - set +x
      - if [ -z "$PROMOTE_FROM" ]; then	fastlane supply -j $PAJ -b *.apk -p $(cat package_name.txt) --track $CHANNEL -m play-listing; else fastlane supply -j $PAJ -b *.apk -p $(cat package_name.txt) --track $PROMOTE_FROM --track_promote_to $CHANNEL -m play-listing; fi
  #post_build:
    #commands:

#artifacts:
#  files:
    # - location
  #discard-paths: yes
  #base-directory: location
#cache:
  #paths:
    # - paths