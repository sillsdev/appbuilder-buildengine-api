#!/usr/bin/env bash
set -e -o pipefail
LOG_FILE="${OUTPUT_DIR}"/console.log
exec > >(tee "${LOG_FILE}") 2>&1

export PATH="$HOME/.rbenv/bin:$PATH"
eval "$(rbenv init -)"

check_audio_sources() {
  if [[ "${BUILD_AUDIO_UPDATE}" == "1" ]]; then
    if [[ "${AUDIO_UPDATE_SOURCE}" != "" ]]; then
      ADD_AUDIO_UPDATE_SOURCE=1
      SOURCES="$(xmllint --xpath "/app-definition/audio-sources/audio-source/name" "${PROJECT_DIR}/build.appDef")"
      IFS='=' read -ra UPDATE_SOURCES <<< "$AUDIO_UPDATE_SOURCE"
      for i in "${UPDATE_SOURCES[@]}"; do
        if [[ "$SOURCES" != *"<name>${i}</name>"* ]]; then
          ADD_AUDIO_UPDATE_SOURCE=0
        fi
      done
    fi
  fi
}

replace_audio_sources() {
  SRC_UPDATE_SOURCE="${UPDATE_SOURCES[0]}"
  DST_UPDATE_SOURCE="${UPDATE_SOURCES[1]}"
  xmlstarlet ed -u "/app-definition/audio-sources/audio-source/name[text() = '${SRC_UPDATE_SOURCE}']" -v "SCRIPTORIA_SRC_SOURCE" "${PROJECT_DIR}/build.appDef" > "${PROJECT_DIR}/tmp.appDef"
  xmlstarlet ed -u "/app-definition/audio-sources/audio-source/name[text() = '${DST_UPDATE_SOURCE}']" -v "SCRIPTORIA_DST_SOURCE" "${PROJECT_DIR}/tmp.appDef" > "${PROJECT_DIR}/build.appDef"
  rm "${PROJECT_DIR}/tmp.appDef"
}

process_audio_sources() {
  check_audio_sources
  if [[ "${ADD_AUDIO_UPDATE_SOURCE}" == "1" ]]; then
    replace_audio_sources
    SCRIPT_OPT="${SCRIPT_OPT} -audio-update-source SCRIPTORIA_SRC_SOURCE=SCRIPTORIA_DST_SOURCE"
  fi
}

process_audio_download() {
  if [[ "${BUILD_AUDIO_DOWNLOAD_URL}" == "" ]]; then
    BUILD_AUDIO_DOWNLOAD_URL="https://4.dbt.io"
  fi

  if [[ "${BUILD_AUDIO_DOWNLOAD}" == "1" ]]; then
    if [[ "${AUDIO_DOWNLOAD_MISSING_ASSETS_KEY}" != "" ]]; then
      SCRIPT_OPT="${SCRIPT_OPT} -audio-download-missing-assets-key ${AUDIO_DOWNLOAD_MISSING_ASSETS_KEY} -audio-download-url ${BUILD_AUDIO_DOWNLOAD_URL}"
    fi
    if [[ "${AUDIO_DOWNLOAD_BITRATE}" != "" ]]; then
      SCRIPT_OPT="${SCRIPT_OPT} -audio-download-bitrate ${AUDIO_DOWNLOAD_BITRATE}"
    fi
  fi
}

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
  process_audio_download
  process_audio_sources

  echo "BUILD_NUMBER=${BUILD_NUMBER}"
  echo "VERSION_NAME=${VERSION_NAME}"
  echo "VERSION_CODE=${VERSION_CODE}"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  echo "SCRIPT_OPT=${SCRIPT_OPT}"

  KEYSTORE_PATH="$(xmllint --xpath "/app-definition/signing/keystore/text()" "${PROJECT_DIR}/build.appDef")"
  KEYSTORE_UNIX_PATH=${KEYSTORE_PATH//\\//}
  KEYSTORE=${KEYSTORE_UNIX_PATH##*/}
  KS="${SECRETS_DIR}/google_play_store/${PUBLISHER}/${KEYSTORE}"
  if [[ "${PRODUCT_KEYSTORE}" != "" ]]; then
    echo "Using product keystore=${PRODUCT_KEYSTORE}"
    KS="${SECRETS_DIR}/google_play_store/${PUBLISHER}/${PRODUCT_KEYSTORE}/${PRODUCT_KEYSTORE}.keystore"
    KSP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/${PRODUCT_KEYSTORE}/ksp.txt")"
    KA="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/${PRODUCT_KEYSTORE}/ka.txt")"
    KAP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/${PRODUCT_KEYSTORE}/kap.txt")"
    { echo "-ksp \"${KSP}\"" ; echo "-ka \"${KA}\""; echo "-kap \"${KAP}\""; } >> "${SECRETS_DIR}/keys.txt"
    KS_OPT="-ks ${KS} -i ${SECRETS_DIR}/keys.txt"
  elif [[ "${BUILD_KEYSTORE}" != "" ]]; then
    echo "Using build keystore=${BUILD_KEYSTORE}"
    KS="${SECRETS_DIR}/google_play_store/${PUBLISHER}/${BUILD_KEYSTORE}/${BUILD_KEYSTORE}.keystore"
    KSP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/${BUILD_KEYSTORE}/ksp.txt")"
    KA="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/${BUILD_KEYSTORE}/ka.txt")"
    KAP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/${BUILD_KEYSTORE}/kap.txt")"
    { echo "-ksp \"${KSP}\"" ; echo "-ka \"${KA}\""; echo "-kap \"${KAP}\""; } >> "${SECRETS_DIR}/keys.txt"
    KS_OPT="-ks ${KS} -i ${SECRETS_DIR}/keys.txt"
  elif [[ -f "${KS}" ]]; then
    echo "Using project keystore=${KEYSTORE}"
    KS_OPT="-ks ${KS}"
  else
    echo "Using publisher keystore=${PUBLISHER}"
    KS="${SECRETS_DIR}/google_play_store/${PUBLISHER}/${PUBLISHER}.keystore"
    KSP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/ksp.txt")"
    KA="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/ka.txt")"
    KAP="$(cat "${SECRETS_DIR}/google_play_store/${PUBLISHER}/kap.txt")"
    { echo "-ksp \"${KSP}\"" ; echo "-ka \"${KA}\""; echo "-kap \"${KAP}\""; } >> "${SECRETS_DIR}/keys.txt"
    KS_OPT="-ks ${KS} -i ${SECRETS_DIR}/keys.txt"
  fi
  echo "KEYSTORE=${KS}"

  cd "$PROJECT_DIR" || exit 1

  # shellcheck disable=SC2086
  $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build ${KS_OPT} -fp apk.output="$OUTPUT_DIR" -vc "$VERSION_CODE" -vn "$VERSION_NAME" ${SCRIPT_OPT}
}

build_html() {
  echo "Build html"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  cd "$PROJECT_DIR" || exit 1

  HTML_OUTPUT_DIR=/tmp/output/html
  mkdir -p "${HTML_OUTPUT_DIR}"
  if [[ "${BUILD_HTML_COLLECTION_ID}" == *","* ]]; then
    IFS=',' read -ra COLLECTIONS <<< "${BUILD_HTML_COLLECTION_ID}"
    for i in "${COLLECTIONS[@]}"; do
      IFS='=' read -ra PARAMS <<< "${i}"
      $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -html "${PARAMS[0]}" -p "${PARAMS[1]}" -fp html.output="${HTML_OUTPUT_DIR}"
    done
    pushd "${HTML_OUTPUT_DIR}"
  else
    $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -html "${BUILD_HTML_COLLECTION_ID}" -fp html.output="${HTML_OUTPUT_DIR}"
    pushd "${HTML_OUTPUT_DIR}/${APPDEF_PACKAGE_NAME}"
  fi
  zip -r "${OUTPUT_DIR}/html.zip" .
  popd
  # Not exported so clear it
  VERSION_CODE=""
  APPDEF_PACKAGE_NAME=""
}

build_pwa() {
  echo "Build pwa"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  cd "$PROJECT_DIR" || exit 1

  PWA_OUTPUT_DIR=/tmp/output/pwa
  mkdir -p "${PWA_OUTPUT_DIR}"
  if [[ "${BUILD_PWA_COLLECTION_ID}" == *","* ]]; then
    IFS=',' read -ra COLLECTIONS <<< "${BUILD_PWA_COLLECTION_ID}"
    for i in "${COLLECTIONS[@]}"; do
      IFS='=' read -ra PARAMS <<< "${i}"
      $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -pwa "${PARAMS[0]}" -p "${PARAMS[1]}"-fp html.output="${HTML_OUTPUT_DIR}"
    done
    pushd "${HTML_OUTPUT_DIR}"
  else
    $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -pwa "${BUILD_PWA_COLLECTION_ID}" -fp html.output="${HTML_OUTPUT_DIR}"
    pushd "${PWA_OUTPUT_DIR}/${APPDEF_PACKAGE_NAME}"
  fi
  zip -r "${OUTPUT_DIR}/pwa.zip" .
  popd
  # Not exported so clear it
  VERSION_CODE=""
  APPDEF_PACKAGE_NAME=""
}

build_play_listing() {
  echo "Build play listing"
  echo "BUILD_NUMBER=${BUILD_NUMBER}"
  echo "VERSION_NAME=${VERSION_NAME}"
  echo "VERSION_CODE=${VERSION_CODE}"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  cd "$PROJECT_DIR" || exit 1

  APK_FILES=("${OUTPUT_DIR}"/*.apk)
  AAPT="$(find /opt/android-sdk/build-tools -name aapt | head -n 1)"

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
  find $PLAY_LISTING_DIR -type f | while read -r f
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
      if [ "${#APK_FILES[@]}" -gt 1 ]; then
        for apk in "${APK_FILES[@]}"
        do
          APK_VERSION_CODE=$($AAPT dump badging "${apk}" | grep "^package" | sed -n "s/.*versionCode='\([0-9]*\).*/\1/p")
          cp "$filename" "${DIR}/changelogs/${APK_VERSION_CODE}.txt"
        done
      else
        cp "$filename" "${DIR}/changelogs/${VERSION_CODE}.txt"
      fi
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

prepare_appbuilder_dir() {
  # Ensure 'App Projects' directory
  mkdir -p "${HOME}/App Builder/Scripture Apps/App Projects"

  if [[ "${BUILD_KEYS_FILE}" != "" ]]; then
    KEY_DEST_DIR="${HOME}/App Builder/Scripture Apps"
    mkdir -p "${KEY_DEST_DIR}"
    cp "${PROJECT_DIR}/build_data/${BUILD_KEYS_FILE}" "${KEY_DEST_DIR}/keys.txt"
  fi
}

prepare_appbuilder_project() {
  # In the past, we have had problems with multiple .appDef files being checked in and confusing error.
  # Fail quickly in this situation
  cd "$PROJECT_DIR" || exit 1
  PROJ_COUNT=$(find . -maxdepth 1 -name "*.appDef" | wc -l)
  if [[ "$PROJ_COUNT" -ne "1" ]]; then
    echo "ERROR: Wrong number of projects: ${PROJ_COUNT}"
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
      cp "${PUBLISH_PROPERTIES}" "${OUTPUT_DIR}/publish-properties.json"
  fi

  APPDEF_VERSION_NAME=$(xmllint --xpath "string(/app-definition/version/@name)" build.appDef)
  echo "APPDEF_VERSION_NAME=${APPDEF_VERSION_NAME}"
  echo "BUILD_MANAGE_VERSION_NAME=${BUILD_MANAGE_VERSION_NAME}"
  if [[ "${BUILD_MANAGE_VERSION_NAME}" == "0" ]]; then
      VERSION_NAME=${APPDEF_VERSION_NAME}
  else
      VERSION_NAME=$("${APP_BUILDER_SCRIPT_PATH}" -? | grep 'Version' | awk -F '[ +]' '{print $2}')
  fi

  APPDEF_PACKAGE_NAME=$(xmllint --xpath "/app-definition/package/text()" build.appDef)
  echo "APPDEF_PACKAGE_NAME=${APPDEF_PACKAGE_NAME}"

  APPDEF_VERSION_CODE=$(xmllint --xpath "string(/app-definition/version/@code)" build.appDef)
  echo "APPDEF_VERSION_CODE=${APPDEF_VERSION_CODE}"
  echo "BUILD_MANAGE_VERSION_CODE=${BUILD_MANAGE_VERSION_CODE}"
  if [[ "${BUILD_MANAGE_VERSION_CODE}" == "0" ]]; then
    VERSION_CODE=$((APPDEF_VERSION_CODE))
  else
    if [[ "$APPDEF_VERSION_CODE" -gt "$VERSION_CODE" ]]; then VERSION_CODE=$((APPDEF_VERSION_CODE)); fi
  fi
}

complete_successful_build() {
  if [[ "${APPDEF_PACKAGE_NAME}" != "" ]]; then
      echo "${APPDEF_PACKAGE_NAME}" > "$OUTPUT_DIR"/package_name.txt
  fi
  if [[ "${VERSION_CODE}" != "" ]]; then
      echo "${VERSION_CODE}" > "$OUTPUT_DIR"/version_code.txt
      echo "{ \"version\" : \"${VERSION_NAME} (${VERSION_CODE})\", \"versionName\" : \"${VERSION_NAME}\", \"versionCode\" : \"${VERSION_CODE}\" } " > "$OUTPUT_DIR"/version.json
  else
      echo "{ \"version\" : \"${VERSION_NAME}\", \"versionName\" : \"${VERSION_NAME}\" } " > "$OUTPUT_DIR"/version.json
  fi

  echo "ls -lR ${OUTPUT_DIR}"
  ls -lR "${OUTPUT_DIR}"
}

env | sort
prepare_appbuilder_project
prepare_appbuilder_dir

echo "TARGETS: $TARGETS"
for target in $TARGETS
do
  case "$target" in
    "apk") build_apk ;;
    "play-listing") build_play_listing ;;
    "html") build_html ;;
    "pwa") build_pwa ;;
    *) build_gradle "$target" ;;
  esac
done

complete_successful_build
