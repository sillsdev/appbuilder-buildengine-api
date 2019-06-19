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
    
phases:
  install:
    commands:
  pre_build:
    commands:
      - OUTPUT_DIR="/${BUILD_NUMBER}"
      - SECRETS_S3="s3://${SECRETS_BUCKET}/jenkins/build/google_play_store/${PUBLISHER}"
      - mkdir "${SECRETS_DIR}"
      - mkdir "${OUTPUT_DIR}"
      - aws s3 sync "${SECRETS_S3}" "${SECRETS_DIR}"
      - export KSP=$(cat "${SECRETS_DIR}/ksp.txt")
      - export KA=$(cat "${SECRETS_DIR}/ka.txt")
      - export KAP=$(cat "${SECRETS_DIR}/kap.txt")
      - export GRADLE_OPTS="-Dorg.gradle.daemon=false"
  build:
    commands:
      - KS="${SECRETS_DIR}/${PUBLISHER}.keystore"
      - echo "BUILD_NUMBER=${BUILD_NUMBER}"
      - echo "VERSION_CODE=${VERSION_CODE}"
      - OUTPUT_DIR="/${BUILD_NUMBER}"
      - PROJNAME=$(basename *.appDef .appDef)
      - mv "${PROJNAME}.appDef" build.appDef
      - mv "${PROJNAME}_data" build_data
      - APPDEF_VERSION=$(grep "version code=" build.appDef|awk -F"\"" '{print $2}')
      - echo "APPDEF_VERSION=${APPDEF_VERSION}"
      - if [ "$APPDEF_VERSION" -ge "$VERSION_CODE" ]; then VERSION_CODE=$((APPDEF_VERSION+1)); fi
      - VERSION_NAME=$(dpkg -s scripture-app-builder | grep 'Version' | awk -F '[ +]' '{print $2}')
      - $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build -ks $KS -ksp $KSP -ka $KA -kap $KAP -fp apk.output=$OUTPUT_DIR -vc $VERSION_CODE -vn $VERSION_NAME -ft share-app-link=true
      - echo $(awk -F '[<>]' '/package/{print $3}' build.appDef) > $OUTPUT_DIR/package_name.txt
      - echo $VERSION_CODE > $OUTPUT_DIR/version_code.txt
      - "echo \"{ \\\"version\\\" : \\\"${VERSION_NAME}.${VERSION_CODE}\\\", \\\"versionName\\\" : \\\"${VERSION_NAME}\\\", \\\"versionCode\\\" : \\\"${VERSION_CODE}\\\" } \" > $OUTPUT_DIR/version.json"
      - if [ -f "build_data/about/about.txt" ]; then cp build_data/about/about.txt $OUTPUT_DIR/; fi
      - PUBLISH_DIR="build_data/publish"
      - PLAY_LISTING_DIR="${PUBLISH_DIR}/play-listing"
      - LIST_DIR="${PLAY_LISTING_DIR}/"
      - MANIFEST_FILE="manifest.txt"
      - if [ -f $LIST_DIR$MANIFEST_FILE ]; then rm $LIST_DIR$MANIFEST_FILE; fi;
      - FILE_LIST=$(find $PLAY_LISTING_DIR -type f -print)
      - for f in $FILE_LIST; do fn=${f#*"$PLAY_LISTING_DIR/"}; echo $fn >> $OUTPUT_DIR/$MANIFEST_FILE; done
      - if [ -d "$PLAY_LISTING_DIR" ]; then cp -r "$PLAY_LISTING_DIR" $OUTPUT_DIR; find $OUTPUT_DIR -name whats_new.txt | while read filename; do DIR=$(dirname "${filename}"); cp "$filename" $OUTPUT_DIR; mkdir "${DIR}/changelogs"; mv "$filename" "${DIR}/changelogs/${VERSION_CODE}.txt"; done; fi
      - mv build_data "${PROJNAME}_data"
      - mv build.appDef "${PROJNAME}.appDef"
      #- if [ "$CODEBUILD_BUILD_SUCCEEDING" -gt "0" ]; then git remote -v; git tag $VERSION_CODE; git push origin $VERSION_CODE; fi
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