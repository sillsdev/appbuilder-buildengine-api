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
  if [[ "${BUILD_AUDIO_DOWNLOAD}" == "1" ]]; then
    if [[ "${AUDIO_DOWNLOAD_MISSING_ASSETS_KEY}" != "" ]]; then
      SCRIPT_OPT="${SCRIPT_OPT} -audio-download-missing-assets-key ${AUDIO_DOWNLOAD_MISSING_ASSETS_KEY}"
    fi
    if [[ "${AUDIO_DOWNLOAD_BITERATE}" != "" ]]; then
      SCRIPT_OPT="${SCRIPT_OPT} -audio-download-bitrate ${AUDIO_DOWNLOAD_BITERATE}"
    fi
  fi
  if [[ "${BUILD_AUDIO_UPDATE}" == "1" ]]; then
    if [[ "${AUDIO_UPDATE_SOURCE}" != "" ]]; then
      SCRIPT_OPT="${SCRIPT_OPT} -audio-update-source ${AUDIO_UPDATE_SOURCE}"
    fi
  fi

  echo "BUILD_NUMBER=${BUILD_NUMBER}"
  echo "VERSION_NAME=${VERSION_NAME}"
  echo "VERSION_CODE=${VERSION_CODE}"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  echo "SCRIPT_OPT=${SCRIPT_OPT}"

  KEYSTORE_PATH="$(xmllint --xpath "/app-definition/signing/keystore/text()" "${PROJECT_DIR}/build.appDef")"
  KEYSTORE_UNIX_PATH=${KEYSTORE_PATH//\\//}
  KEYSTORE=${KEYSTORE_UNIX_PATH##*/}
  KS="${SECRETS_DIR}/google_play_store/${PUBLISHER}/${KEYSTORE}"
  if [[ -f "${KS}" ]]; then
    KS_OPT="-ks ${KS}"
  else
    KS="${SECRETS_DIR}/google_play_store/${PUBLISHER}/${PUBLISHER}.keystore"
    KSP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/ksp.txt")"
    KA="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/ka.txt")"
    KAP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/kap.txt")"
    KS_OPT="-ks ${KS} -ksp ${KSP} -ka ${KA} -kap ${KAP}"
  fi

  cd "$PROJECT_DIR" || exit 1

  # shellcheck disable=SC2086
  $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build ${KS_OPT} -fp apk.output="$OUTPUT_DIR" -vc "$VERSION_CODE" -vn "$VERSION_NAME" ${SCRIPT_OPT} |& tee "${OUTPUT_DIR}"/console.log

  xmllint --xpath "/app-definition/package/text()" build.appDef > "$OUTPUT_DIR"/package_name.txt
  echo $VERSION_CODE > "$OUTPUT_DIR"/version_code.txt
  echo "{ \"version\" : \"${VERSION_NAME} (${VERSION_CODE})\", \"versionName\" : \"${VERSION_NAME}\", \"versionCode\" : \"${VERSION_CODE}\" } " > "$OUTPUT_DIR"/version.json

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
  PROJ_DIR=$(find . | grep -i "${PROJ_NAME}_data$" | head -n1)
  if [[ -f "${PROJ_NAME}.appDef" && -d "${PROJ_DIR}" ]]; then
    echo "Moving ${PROJ_NAME}.appDef and ${PROJ_DIR}"
    mv "${PROJ_NAME}.appDef" build.appDef
    mv "${PROJ_DIR}" build_data
  else
    echo "ERROR: Project appDef or project data not found"
    exit 3
  fi

  PUBLISH_PROPERTIES="build_data/publish/properties.json"
  if [[ -f "${PUBLISH_PROPERTIES}" ]]; then
      for s in $(jq -r "to_entries|map(\"\(.key)=\(.value|tostring)\")|.[]" "${PUBLISH_PROPERTIES}"); do
        # shellcheck disable=SC2086 disable=SC2163
        export $s
      done
  fi

  APPDEF_VERSION_NAME=$(xmllint --xpath "string(/app-definition/version/@name)" build.appDef)
  echo "APPDEF_VERSION_NAME=${APPDEF_VERSION_NAME}"
  echo "BUILD_MANAGE_VERSION_NAME=${BUILD_MANAGE_VERSION_NAME}"
  if [[ "${BUILD_MANAGE_VERSION_NAME}" == "0" ]]; then
      VERSION_NAME=${APPDEF_VERSION_NAME}
  else
      VERSION_NAME=$("${APP_BUILDER_SCRIPT_PATH}" -? | grep 'Version' | awk -F '[ +]' '{print $2}')
  fi

  APPDEF_VERSION_CODE=$(xmllint --xpath "string(/app-definition/version/@code)" build.appDef)
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
