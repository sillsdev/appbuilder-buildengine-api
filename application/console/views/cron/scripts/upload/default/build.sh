#!/usr/bin/env bash
set -e -o pipefail

build_apk() {
  echo "Build APK"
  cd "$PROJECT_DIR" || exit 1
  if [[ "${BUILD_MANAGE_VERSION_CODE}" != "0" ]]; then
    VERSION_CODE=$((VERSION_CODE + 1))
  fi
  SCRIPT_OPT=""
  echo "BUILD_SHARE_APP_LINK=${BUILD_SHARE_APP_LINK}"
  if [[ "${BUILD_SHARE_APP_LINK}" != "0" ]]; then
    SCRIPT_OPT="${SCRIPT_OPT} -ft share-app-link=true"
  fi
  echo "BUILD_NUMBER=${BUILD_NUMBER}"
  echo "VERSION_NAME=${VERSION_NAME}"
  echo "VERSION_CODE=${VERSION_CODE}"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  echo "SCRIPT_OPT=${SCRIPT_OPT}"
  KS="${SECRETS_DIR}/google_play_store/${PUBLISHER}/${PUBLISHER}.keystore"
  KSP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/ksp.txt")"
  KA="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/ka.txt")"
  KAP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/kap.txt")"

  cd "$PROJECT_DIR" || exit 1

  $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build -ks "$KS" -ksp "$KSP" -ka "$KA" -kap "$KAP" -fp apk.output="$OUTPUT_DIR" -vc "$VERSION_CODE" -vn "$VERSION_NAME" "${SCRIPT_OPT}" |& tee "${OUTPUT_DIR}"/console.log
  echo "ls -l ${OUTPUT_DIR}"
  ls -l "${OUTPUT_DIR}"
}

build_play_listing() {
  echo "Build play listing"
  echo "BUILD_NUMBER=${BUILD_NUMBER}"
  echo "VERSION_NAME=${VERSION_NAME}"
  echo "VERSION_CODE=${VERSION_CODE}"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  cd "$PROJECT_DIR" || exit 1
  awk -F '[<>]' '/<package>/{print $3}' build.appDef > "$OUTPUT_DIR"/package_name.txt
  echo $VERSION_CODE > "$OUTPUT_DIR"/version_code.txt
  echo "{ \"version\" : \"${VERSION_NAME} (${VERSION_CODE})\", \"versionName\" : \"${VERSION_NAME}\", \"versionCode\" : \"${VERSION_CODE}\" } " > "$OUTPUT_DIR"/version.json
  if [ -f "build_data/about/about.txt" ]; then
    cp build_data/about/about.txt "$OUTPUT_DIR"/
  fi
  PUBLISH_DIR="build_data/publish"
  PLAY_LISTING_DIR="${PUBLISH_DIR}/play-listing"
  LIST_DIR="${PLAY_LISTING_DIR}/"
  MANIFEST_FILE="manifest.txt"
  if [ -f $LIST_DIR$MANIFEST_FILE ]; then
    rm $LIST_DIR$MANIFEST_FILE
  fi
  FILE_LIST=$(find $PLAY_LISTING_DIR -type f -print)
  for f in $FILE_LIST
  do
    fn=${f#*"$PLAY_LISTING_DIR/"}
    echo "$fn" >> "$OUTPUT_DIR"/$MANIFEST_FILE
  done
  if [ -d "$PLAY_LISTING_DIR" ]; then
    cp -r "$PLAY_LISTING_DIR" "$OUTPUT_DIR"
    find "$OUTPUT_DIR" -name whats_new.txt | while read -r filename
    do
      DIR=$(dirname "${filename}")
      cp "$filename" "$OUTPUT_DIR"
      mkdir "${DIR}/changelogs"
      mv "$filename" "${DIR}/changelogs/${VERSION_CODE}.txt"
    done
  fi
}

build_gradle() {
  echo "Gradle $1"
  if [ -f "${PROJECT_DIR}/build.gradle" ]; then
    pushd "$PROJECT_DIR" || exit 1
    gradle "$1"
    popd || exit 1
  elif [ -f "${SCRIPT_DIR}/build.gradle" ]; then
    pushd "$SCRIPT_DIR" || exit 1
    gradle "$1"
    popd || exit 1
  fi
}

prepare_appbuilder_project() {
  # In the past, we have had problems with multiple .appDef files being checked in and confusing error.
  # Fail quickly in this situation
  cd "$PROJECT_DIR" || exit 1
  PROJ_COUNT=$(find . -name "*.appDef" | wc -l)
  if [[ "$PROJ_COUNT" -ne "1" ]]; then
    echo "ERROR: Wrong number of projects"
    exit 2
  fi

  PROJ_NAME=$(basename -- *.appDef .appDef)
  if [[ -f "${PROJ_NAME}.appDef" && -d "${PROJ_NAME}_data" ]]; then
    echo "Moving ${PROJ_NAME}.appDef and ${PROJ_NAME}_data"
    mv "${PROJ_NAME}.appDef" build.appDef
    mv "${PROJ_NAME}_data" build_data
  else
    echo "ERROR: Project appDef or project data not found"
    exit 3
  fi

  APPDEF_VERSION_NAME=$(grep "version code=" build.appDef|awk -F"\"" '{print $4}')
  echo "APPDEF_VERSION_NAME=${APPDEF_VERSION_NAME}"
  echo "BUILD_MANAGE_VERSION_NAME=${BUILD_MANAGE_VERSION_NAME}"
  if [[ "${BUILD_MANAGE_VERSION_NAME}" == "0" ]]; then
      VERSION_NAME=${APPDEF_VERSION_NAME}
  else
      VERSION_NAME=$(dpkg -s "${APP_BUILDER_SCRIPT_PATH}" | grep 'Version' | awk -F '[ +]' '{print $2}')
  fi

  APPDEF_VERSION_CODE=$(grep "version code=" build.appDef|awk -F"\"" '{print $2}')
  echo "APPDEF_VERSION_CODE=${APPDEF_VERSION_CODE}"
  echo "BUILD_MANAGE_VERSION_CODE=${BUILD_MANAGE_VERSION_CODE}"
  if [[ "${BUILD_MANAGE_VERSION_CODE}" == "0" ]]; then
    VERSION_CODE=$((APPDEF_VERSION_CODE))
  else
    if [[ "$APPDEF_VERSION_CODE" -gt "$VERSION_CODE" ]]; then VERSION_CODE=$((APPDEF_VERSION_CODE)); fi
  fi
}

env
prepare_appbuilder_project

echo "TARGETS: $TARGETS"
for target in $TARGETS
do
  case "$target" in
    "apk") build_apk ;;
    "play-listing") build_play_listing ;;
    *) build_gradle "$target" ;;
  esac
done
