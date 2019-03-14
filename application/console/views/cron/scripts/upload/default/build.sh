#!/bin/bash

build_apk() {
  echo "Build APK"
  cd $PROJECT_DIR
  PROJNAME=$(basename *.appDef .appDef)
  mv "${PROJNAME}.appDef" build.appDef
  mv "${PROJNAME}_data" build_data
  APPDEF_VERSION=$(grep "version code=" build.appDef|awk -F"\"" '{print $2}')
  echo "APPDEF_VERSION=${APPDEF_VERSION}"
  if [ "$APPDEF_VERSION" -gt "$VERSION_CODE" ]; then VERSION_CODE=$((APPDEF_VERSION)); fi
  VERSION_CODE=$((VERSION_CODE + 1))
  echo "BUILD_NUMBER=${BUILD_NUMBER}"
  echo "VERSION_CODE=${VERSION_CODE}"
  echo "APPDEF_VERSION=${APPDEF_VERSION}"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  KS="${SECRETS_DIR}/${PUBLISHER}.keystore"
  cd $PROJECT_DIR
  VERSION_NAME=$(dpkg -s scripture-app-builder | grep 'Version' | awk -F '[ +]' '{print $2}')
  $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build -ks $KS -ksp $KSP -ka $KA -kap $KAP -fp apk.output=$OUTPUT_DIR -vc $VERSION_CODE -vn $VERSION_NAME -ft share-app-link=true
}

build_play_listing() {
  echo "Build play listing"
  echo "BUILD_NUMBER=${BUILD_NUMBER}"
  echo "VERSION_CODE=${VERSION_CODE}"
  echo "APPDEF_VERSION=${APPDEF_VERSION}"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  cd $PROJECT_DIR
  PROJNAME=$(basename *.appDef .appDef)
  if [ -f "${PROJNAME}.appDef" ]; then
    echo "Moving ${PROJNAME}.appDef and ${PROJNAME}_data"
    mv "${PROJNAME}.appDef" build.appDef
    mv "${PROJNAME}_data" build_data
  fi
  echo $(awk -F '[<>]' '/package/{print $3}' build.appDef) > $OUTPUT_DIR/package_name.txt
  echo $VERSION_CODE > $OUTPUT_DIR/version_code.txt
  echo "{ \"version\" : \"${VERSION_NAME}.${VERSION_CODE}\", \"versionName\" : \"${VERSION_NAME}\", \"versionCode\" : \"${VERSION_CODE}\" } " > $OUTPUT_DIR/version.json
  if [ -f "build_data/about/about.txt" ]; then
    cp build_data/about/about.txt $OUTPUT_DIR/;
  fi
  PUBLISH_DIR="build_data/publish"
  PLAY_LISTING_DIR="${PUBLISH_DIR}/play-listing"
  LIST_DIR="${PLAY_LISTING_DIR}/"
  MANIFEST_FILE="manifest.txt"
  if [ -f $LIST_DIR$MANIFEST_FILE ];
    then rm $LIST_DIR$MANIFEST_FILE;
  fi
  FILE_LIST=$(find $PLAY_LISTING_DIR -type f -print)
  for f in $FILE_LIST; do fn=${f#*"$PLAY_LISTING_DIR/"};
    echo $fn >> $OUTPUT_DIR/$MANIFEST_FILE;
  done
  if [ -d "$PLAY_LISTING_DIR" ]; then
    cp -r "$PLAY_LISTING_DIR" $OUTPUT_DIR;
    find $OUTPUT_DIR -name whats_new.txt | while read filename; do DIR=$(dirname "${filename}");
      cp "$filename" $OUTPUT_DIR; mkdir "${DIR}/changelogs";
      mv "$filename" "${DIR}/changelogs/${VERSION_CODE}.txt";
    done;
  fi
}

build_gradle() {
  echo "Gradle $1"
  if [ -f "${PROJECT_DIR}/build.gradle" ]; then
    pushd $PROJECT_DIR
    gradle $1
    popd
  elif [ -f "${SCRIPT_DIR}/build.gradle" ]; then
    pushd $SCRIPT_DIR
    gradle $1
    popd
  fi
}

echo "TARGETS: $TARGETS"
env
for target in $TARGETS
do
  case "$target" in
    "apk") build_apk ;;
    "play-listing") build_play_listing ;;
    *) build_gradle $target ;;
  esac
done
