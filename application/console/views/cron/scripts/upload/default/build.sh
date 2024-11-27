#!/usr/bin/env bash
set -e -o pipefail
#set -x

LOG_FILE="${OUTPUT_DIR}"/console.log
exec > >(tee "${LOG_FILE}") 2>&1

export PATH="$HOME/.rbenv/bin:$PATH"
eval "$(rbenv init -)"

BUILD_DIR=/tmp/build
mkdir -p "$BUILD_DIR"
SCRIPT_OPT="-fp build=${BUILD_DIR}"

sync_secrets() {
  SECRETS_SUBDIR=$1
  SECRETS_S3="s3://${SECRETS_BUCKET}/jenkins/build"
  echo "sync secrets"
  echo "SECRETS_SUBDIR = ${SECRETS_SUBDIR}"
  aws s3 sync "${SECRETS_S3}/${SECRETS_SUBDIR}" "${SECRETS_DIR}"
}

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
  if [[ "${AUDIO_DOWNLOAD_MISSING_ASSETS_SOURCE}" == "${SRC_UPDATE_SOURCE}" ]]; then
    export AUDIO_DOWNLOAD_MISSING_ASSETS_SOURCE="SCRIPTORIA_SRC_SOURCE"
  fi
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
  if [[ "${BUILD_AUDIO_DOWNLOAD}" == "1" ]]; then
    if [[ "${AUDIO_DOWNLOAD_MISSING_ASSETS_KEY}" != "" ]]; then
      if [[ "${BUILD_AUDIO_DOWNLOAD_URL}" == "" ]]; then
        BUILD_AUDIO_DOWNLOAD_URL="https://4.dbt.io"
      fi
      SCRIPT_OPT="${SCRIPT_OPT} -audio-download-missing-assets-key ${AUDIO_DOWNLOAD_MISSING_ASSETS_KEY} -audio-download-url ${BUILD_AUDIO_DOWNLOAD_URL}"
      if [[ "${AUDIO_DOWNLOAD_BITRATE}" != "" ]]; then
        SCRIPT_OPT="${SCRIPT_OPT} -audio-download-bitrate ${AUDIO_DOWNLOAD_BITRATE}"
      fi
    elif [[ "${AUDIO_DOWNLOAD_MISSING_ASSETS_SOURCE}" != "" ]]; then
      SCRIPT_OPT="${SCRIPT_OPT} -audio-download-missing-assets-source ${AUDIO_DOWNLOAD_MISSING_ASSETS_SOURCE}"
    fi
    if [[ "${AUDIO_DOWNLOAD_CODEC}" != "" ]]; then
      SCRIPT_OPT="${SCRIPT_OPT} -audio-download-codec ${AUDIO_DOWNLOAD_CODEC}"
    fi
  fi
}

build_apk() {
  echo "Build APK"
  cd "$PROJECT_DIR" || exit 1
  if [[ "${BUILD_MANAGE_VERSION_CODE}" != "0" ]]; then
    VERSION_CODE=$((VERSION_CODE + 1))
  fi

  if [[ "${BUILD_COMPUTE_TYPE}" != "" ]]; then
    if [[ "${BUILD_COMPUTE_TYPE}" != "small" ]]; then
        export _JAVA_OPTIONS="-Xmx3072M"
    fi
  fi
  if [[ "${BUILD_JAVA_MAX_HEAP_SIZE}" == "1" ]]; then
    export _JAVA_OPTIONS="-Xmx2048M"
  fi

  echo "BUILD_SHARE_APP_LINK=${BUILD_SHARE_APP_LINK}"
  if [[ "${BUILD_SHARE_APP_LINK}" != "0" ]]; then
    SCRIPT_OPT="${SCRIPT_OPT} -ft share-app-link=true"
  fi
  echo "BUILD_SHARE_APP_INSTALLER=${BUILD_SHARE_APP_INSTALLER}"
  if [[ "${BUILD_SHARE_APP_INSTALLER}" == "1" ]]; then
    SCRIPT_OPT="${SCRIPT_OPT} -ft share-apk-file=true"
  fi
  echo "BUILD_SHARE_DOWNLOAD_APP_LINK=${BUILD_SHARE_DOWNLOAD_APP_LINK}"
  if [[ "${BUILD_SHARE_DOWNLOAD_APP_LINK}" == "1" ]]; then
    SCRIPT_OPT="${SCRIPT_OPT} -ft share-download-app-link=true -ft share-download-app-link-url=https://app.scriptoria.io/downloads/apk/${APPDEF_PACKAGE_NAME}/published"
  fi

  # if building APK for Google Play, then include data safety CSV in output
  echo "APPBUILDER_SCRIPT_VERSION=${APPBUILDER_SCRIPT_VERSION}"
  if dpkg --compare-versions "$APPBUILDER_SCRIPT_VERSION" ge "10.3"; then
    if [[ "${TARGETS}" == *"play-listing"* ]]; then
      SCRIPT_OPT="${SCRIPT_OPT} -data-safety-csv"
    fi
  fi

  process_audio_sources
  process_audio_download

  echo "BUILD_NUMBER=${BUILD_NUMBER}"
  echo "VERSION_NAME=${VERSION_NAME}"
  echo "VERSION_CODE=${VERSION_CODE}"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  echo "SCRIPT_OPT=${SCRIPT_OPT}"

  if [[ "${BUILD_KEYSTORE}" != "" ]]; then
    echo "Using build keystore=${BUILD_KEYSTORE}"
    SECRETS_SUBDIR="google_play_store/${PUBLISHER}/${BUILD_KEYSTORE}"
    sync_secrets "${SECRETS_SUBDIR}"
    KS="${SECRETS_DIR}/${BUILD_KEYSTORE}.keystore"
  else
    echo "Using publisher keystore=${PUBLISHER}"
    SECRETS_SUBDIR="google_play_store/${PUBLISHER}"
    sync_secrets "${SECRETS_SUBDIR}"
    KS="${SECRETS_DIR}/${PUBLISHER}.keystore"
  fi
  KSP="$(cat "${SECRETS_DIR}/ksp.txt")"
  KA="$(cat "${SECRETS_DIR}/ka.txt")"
  KAP="$(cat "${SECRETS_DIR}/kap.txt")"
  { echo "-ksp \"${KSP}\"" ; echo "-ka \"${KA}\""; echo "-kap \"${KAP}\""; } >> "${SECRETS_DIR}/keys.txt"
  KS_OPT="-ks ${KS} -i ${SECRETS_DIR}/keys.txt"

  echo "KEYSTORE=${KS}"

  cd "$PROJECT_DIR" || exit 1

  # shellcheck disable=SC2086
  $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build ${KS_OPT} -fp apk.output="$OUTPUT_DIR" -vc "$VERSION_CODE" -vn "$VERSION_NAME" ${SCRIPT_OPT}
  if [[ "${BUILD_ANDROID_AAB}" == "1" ]]; then
    # shellcheck disable=SC2086
    $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build -app-bundle ${KS_OPT} -fp apk.output="$OUTPUT_DIR" -vc "$VERSION_CODE" -vn "$VERSION_NAME" ${SCRIPT_OPT}
  fi

  # verify output -- AAPT2 is failing during appbuilder build but error is not getting back to script
  pushd "$OUTPUT_DIR"
  shopt -s nullglob
  for f in *.{apk,aab}; do
    echo "JARSIGNER: Checking ${OUTPUT_DIR}/$f"
    jarsigner -verify "$f"
  done
  shopt -u nullglob
  popd

  ###
  # For the download page, we need the primary color and localized string for "Download APK"
  #
  # Add primary-color. Look for an overriden value (will return error if not found). If not found, then look in build values (yuck).
  set +e
  PRIMARY_COLOR=$(xmlstarlet sel -t -v '/app-definition/colors/color[@name = "PrimaryColor"]/color-mapping/@value' "${PROJECT_DIR}/build.appDef" ) || echo "Color not set in appDef"
  if [[ "${PRIMARY_COLOR}" == "" ]]; then
    # This is a little ugly getting the color after a build
    PRIMARY_COLOR=$(xmlstarlet sel -t -v '/resources/color[@name = "colorPrimary"]' "/tmp/App Builder/build/${APP_BUILDER_TLA}.000/a/res/values/colors.xml")
  fi
  echo "$PRIMARY_COLOR" > "${PROJECT_DIR}/build_data/publish/play-listing/primary-color.txt"

  # Add download-apk-strings.json
  # Extract the entries from appDef as XML and then convert to JSON.
  # It is possible to have empty entries (e.g. <translation language="hu"></tranlsation>) which xmlstarlet returns as <translation lang="hu"/>.
  # This requires the first regex to remove these or it will cause problems with the converstion to JSON
  DOWNLOAD_STRINGS=$(xmlstarlet sel -t -c '/app-definition/translation-mappings/translation-mapping[@id = "Download_APK"]/translation' "${PROJECT_DIR}/build.appDef" | sed  -r 's/<translation lang="[^"]+"\/>//g; s/<translation lang=//g; s/<\/translation>/" ,/g; s/>/ : "/g' | sed 's/^/{/; s/,$/}/')
  if [[ "${DOWNLOAD_STRINGS}" == "" ]]; then
    DOWNLOAD_STRINGS='{"en" : "Download APK"}'
  fi
  echo "$DOWNLOAD_STRINGS" > "${PROJECT_DIR}/build_data/publish/play-listing/download-apk-strings.json"
  set -e
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
      # shellcheck disable=SC2086
      $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -html "${PARAMS[0]}" -p "${PARAMS[1]}" -fp html.output="${HTML_OUTPUT_DIR}" ${SCRIPT_OPT}
    done
    pushd "${HTML_OUTPUT_DIR}"
  else
    # shellcheck disable=SC2086
    $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -html "${BUILD_HTML_COLLECTION_ID}" -fp html.output="${HTML_OUTPUT_DIR}" ${SCRIPT_OPT}
    pushd "${HTML_OUTPUT_DIR}/${APPDEF_PACKAGE_NAME}"
  fi
  zip -r "${OUTPUT_DIR}/html.zip" .
  popd
  # Not exported so clear it
  VERSION_CODE=""
  APPDEF_PACKAGE_NAME=""
}

make_pwa_audio_url_relative() {
  AUDIO_URL=$(xmlstarlet sel -t -v "/app-definition/features/feature[@name = 'export-html-audio-path']/@value" "${PROJECT_DIR}/build.appDef" || true)
  if [[ "${AUDIO_URL}" == "" ]]; then
    echo "Export HTML Audio Path not set"
    exit 1
  fi
  AUDIO_RELATIVE_URL=$(echo "${AUDIO_URL}" | sed -E 's/^https?://')
  xmlstarlet ed --inplace -u "/app-definition/features/feature[@name = 'export-html-audio-path']/@value" -v "${AUDIO_RELATIVE_URL}" "${PROJECT_DIR}/build.appDef"
}

build_pwa() {
  echo "Build pwa"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  cd "$PROJECT_DIR" || exit 1

  if [[ "${BUILD_PWA_AUDIO_RELATIVE_URL}" == "1" ]]; then
    make_pwa_audio_url_relative
  fi
  PWA_OUTPUT_DIR=/tmp/output/pwa
  mkdir -p "${PWA_OUTPUT_DIR}"
  if [[ "${BUILD_PWA_COLLECTION_ID}" == *","* ]]; then
    IFS=',' read -ra COLLECTIONS <<< "${BUILD_PWA_COLLECTION_ID}"
    for i in "${COLLECTIONS[@]}"; do
      IFS='=' read -ra PARAMS <<< "${i}"
      # shellcheck disable=SC2086
      $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -pwa "${PARAMS[0]}" -p "${PARAMS[1]}" -fp html.output="${PWA_OUTPUT_DIR}" ${SCRIPT_OPT}
    done
    pushd "${PWA_OUTPUT_DIR}"
  else
    # shellcheck disable=SC2086
    $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -pwa "${BUILD_PWA_COLLECTION_ID}" -fp html.output="${PWA_OUTPUT_DIR}" ${SCRIPT_OPT}
    pushd "${PWA_OUTPUT_DIR}/${APPDEF_PACKAGE_NAME}"
  fi
  zip -r "${OUTPUT_DIR}/pwa.zip" .
  popd
  # Not exported so clear it
  VERSION_CODE=""
  APPDEF_PACKAGE_NAME=""
}

build_modern_pwa() {
  echo "Build Modern PWA"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  cd "$PROJECT_DIR" || exit 1

  PWA_OUTPUT_DIR=/tmp/output/pwa
  mkdir -p "${PWA_OUTPUT_DIR}"

  # shellcheck disable=SC2086
  $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build-modern-pwa -fp pwa.output="${PWA_OUTPUT_DIR}" ${SCRIPT_OPT}
  pushd "${PWA_OUTPUT_DIR}/${APPDEF_PACKAGE_NAME}/build"
  zip -r "${OUTPUT_DIR}/pwa.zip" .
  popd
  VERSION_CODE=""
  APPDEF_PACKAGE_NAME=""
}

set_default_asset_package() {
    ASSET_FILENAME="${APPDEF_PACKAGE_NAME}.zip"
    echo "Updating ipa-app-type=assets"
    echo "Updating ipa-asset-filename=${ASSET_FILENAME}"
    echo "Project=${PROJECT_DIR}/build.appDef"
    if grep -q "<ipa-app-type>" "${PROJECT_DIR}/build.appDef"; then
      xmlstarlet ed --inplace -u "/app-definition/ipa-app-type" -v "assets" "${PROJECT_DIR}/build.appDef"
    else
      xmlstarlet ed --inplace -s "/app-definition" -t elem -n "ipa-app-type" -v "assets" "${PROJECT_DIR}/build.appDef"
    fi
    if grep -q "<ipa-asset-filename>" "${PROJECT_DIR}/build.appDef"; then
      xmlstarlet ed --inplace -u "/app-definition/ipa-asset-filename" -v "${ASSET_FILENAME}" "${PROJECT_DIR}/build.appDef"
    else
      xmlstarlet ed --inplace -s "/app-definition" -t elem -n "ipa-asset-filename" -v "${ASSET_FILENAME}" "${PROJECT_DIR}/build.appDef"
    fi
}

build_asset_package() {
  echo "Build asset-package"
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  cd "$PROJECT_DIR" || exit 1

  ASSET_OUTPUT_DIR="${OUTPUT_DIR}/asset-package"
  mkdir -p "${ASSET_OUTPUT_DIR}"

  APP_TYPE_COUNT=$(xmlstarlet sel -t -v "count(/app-definition/ipa-app-type)" "${PROJECT_DIR}/build.appDef")
  ASSET_FILENAME_COUNT=$(xmlstarlet sel -t -v "count(/app-definition/ipa-asset-filename)" "${PROJECT_DIR}/build.appDef")
  if [[ "$APP_TYPE_COUNT" == 0 || "$ASSET_FILENAME_COUNT" == 0 ]]; then
    # Older project; provide default
    set_default_asset_package
  else
    APP_TYPE=$(xmlstarlet sel -t -v "/app-definition/ipa-app-type" "${PROJECT_DIR}/build.appDef")
    if [[ "$APP_TYPE" != "assets" ]]; then
      set_default_asset_package
    fi
  fi

  APP_TYPE=$(xmlstarlet sel -t -v "/app-definition/ipa-app-type" "${PROJECT_DIR}/build.appDef")
  ASSET_FILENAME="$(xmlstarlet sel -t -v "//app-definition/ipa-asset-filename" "${PROJECT_DIR}/build.appDef")"
  if [[ "$ASSET_FILENAME" == "" ]]; then
    set_default_asset_package
  fi
  APP_NAME="$(xmlstarlet sel -t -v "/app-definition/app-name" "${PROJECT_DIR}/build.appDef")"
  echo "APP_TYPE=${APP_TYPE}"
  echo "ASSET_FILENAME=${ASSET_FILENAME}"
  echo "APP_NAME=${APP_NAME}"

  # shellcheck disable=SC2086
  $APP_BUILDER_SCRIPT_PATH -load build.appDef -no-save -build-assets -fp ipa.output="${ASSET_OUTPUT_DIR}" -vn "$VERSION_NAME" ${SCRIPT_OPT}

  # Build preview
  cat >"${ASSET_OUTPUT_DIR}/preview.html" <<EOL
<html><head><meta charset="UTF-8"><style>
.container {
  display: flex;
  justify-content: center;
  font-size: 40px;
}
.center {
  width: 800px;
}
</style></head>
<body>
<p id="top" class="container">Preview</p>
<div class="container">
<p><a href="${ASSET_FILENAME}">${APP_NAME}</a></p>
</div>
</body>
</html>
EOL

  ### Build notification

  # query langtags
  NOTIFY_LANG_TMP=$(mktemp)
  jq -cM "{app_lang: .[] | select(.tag==\"${PROJECT_LANGUAGE}\") }" /root/langtags.json > "${NOTIFY_LANG_TMP}"
  if [ ! -s "${NOTIFY_LANG_TMP}" ]; then
    # The language was not found; provide default
    echo '{}' | jq -cM --arg lang "${PROJECT_LANGUAGE}" '. + { app_lang: { tag: $lang } }' > "$NOTIFY_LANG_TMP"
  fi

  # build listing
  NOTIFY_LISTING_TMP=$(mktemp)
  NOTIFY_BASE_TMP=$(mktemp)
  PLAY_LISTING_DIR="build_data/publish/play-listing"
  echo '{}' | jq -cM '. + { listing: [] }' > "$NOTIFY_BASE_TMP"
  pushd "${PLAY_LISTING_DIR}"
  NOTIFY_LANGS=$(find . -mindepth 1 -maxdepth 1 -type d | cut -d/ -f2)
  for lang in $NOTIFY_LANGS
  do
    sd="$(cat "$lang/short_description.txt")"
    fd="$(cat "$lang/full_description.txt")"
    title="$(cat "$lang/title.txt")"
    jq -cM --arg lang "$lang" --arg sd "$sd" --arg fd "$fd" --arg title "$title" \
    '.listing += [ { lang: $lang, title: $title, short_description: $sd, full_description: $fd } ]' \
    "$NOTIFY_BASE_TMP" > "$NOTIFY_LISTING_TMP"
    cp "$NOTIFY_LISTING_TMP" "$NOTIFY_BASE_TMP"
  done
  popd

  # extract image
  pushd "build_data/images"
  # look for ios images
  NOTIFY_IMAGES_TMP=$(mktemp)
  echo '{}' | jq -cM '. + { image: { files: [] } }' > "$NOTIFY_BASE_TMP"
  if [ -d "ios/drawer" ]; then
    # copy predefined images
    pushd "ios/drawer"
    NOTIFY_IMAGES=$(find . -mindepth 1 -maxdepth 1 -type f | cut -d/ -f2)
    for image in $NOTIFY_IMAGES
    do
      size=1x
      if [[ "$image" == *"@2x"* ]]; then
        size=2x
      elif [[ "$image" == *"@3x"* ]]; then
        size=3x
      fi
      jq -cM --arg size "$size" --arg image "$image" '.image.files += [ {size: $size, src: $image} ]' "$NOTIFY_BASE_TMP" > "$NOTIFY_IMAGES_TMP"
      cp "$image" "${ASSET_OUTPUT_DIR}/${image}"
      cp "$NOTIFY_IMAGES_TMP" "$NOTIFY_BASE_TMP"
    done
    popd
  else
    # resize largest image to desired sizes
    for size in "xxxhdpi" "xxhdpi" "xhdpi" "hdpi"
    do
      image_file="drawable-${size}/nav_drawer.png"
      if [ -f "${image_file}" ]; then
        convert "${image_file}" -resize 750x422 "${ASSET_OUTPUT_DIR}/nav_drawer@3x.png"
        convert "${image_file}" -resize 500x282 "${ASSET_OUTPUT_DIR}/nav_drawer@2x.png"
        convert "${image_file}" -resize 250x141 "${ASSET_OUTPUT_DIR}/nav_drawer.png"
        jq -cM '.image.files += [ { size: "1x", src: "nav_drawer.png" }, { size: "2x", src: "nav_drawer@2x.png" }, {size: "3x", src: "nav_drawer@3x.png" } ]' "$NOTIFY_BASE_TMP" > "$NOTIFY_IMAGES_TMP"
        break
      fi
    done
  fi
  popd

  # combine json objects
  if [ -s "${NOTIFY_IMAGES_TMP}" ]; then
    jq -cM -s '.[0] * .[1] * .[2]' "$NOTIFY_LANG_TMP" "$NOTIFY_IMAGES_TMP" "$NOTIFY_LISTING_TMP" > "${ASSET_OUTPUT_DIR}/notify.json"
  else
    jq -cM -s '.[0] * .[1]' "$NOTIFY_LANG_TMP" "$NOTIFY_LISTING_TMP" > "${ASSET_OUTPUT_DIR}/notify.json"
  fi

  # Not exported so clear it
  VERSION_CODE=""
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
        # If there are multiple APK files, we only need to provide 1 changelog,
        # but it has to match one of the version codes.
        apk=${APK_FILES[0]}
        APK_VERSION_CODE=$($AAPT dump badging "${apk}" | grep "^package" | sed -n "s/.*versionCode='\([0-9]*\).*/\1/p")
        cp "$filename" "${DIR}/changelogs/${APK_VERSION_CODE}.txt"
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
  if [[ "${APP_BUILDER_SCRIPT_PATH}" == "scripture-app-builder" ]]; then
    APP_BUILDER_TLA=SAB
    APP_BUILDER_FOLDER="Scripture Apps"
  elif [[ "${APP_BUILDER_SCRIPT_PATH}" == "reading-app-builder" ]]; then
    APP_BUILDER_TLA=RAB
    APP_BUILDER_FOLDER="Reading Apps"
  elif [[ "${APP_BUILDER_SCRIPT_PATH}" == "dictionary-app-builder" ]]; then
    APP_BUILDER_TLA=DAB
    APP_BUILDER_FOLDER="Dictionary Apps"
  elif [[ "${APP_BUILDER_SCRIPT_PATH}" == "keyboard-app-builder" ]]; then
    APP_BUILDER_TLA=KAB
    APP_BUILDER_FOLDER="Keyboard Apps"
  fi

  mkdir -p "${HOME}/App Builder/${APP_BUILDER_FOLDER}/App Projects"

  if [[ "${BUILD_KEYS_FILE}" != "" ]]; then
    KEY_DEST_DIR="${HOME}/App Builder/${APP_BUILDER_FOLDER}"
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

  APPBUILDER_SCRIPT_VERSION=$($APP_BUILDER_SCRIPT_PATH -? | grep "Version" | cut -d\  -f2)

  PROJ_NAME=$(basename -- *.appDef .appDef)
  PROJ_DIR=$(find . -maxdepth 1 -type d | grep -i -F "${PROJ_NAME}_data")
  if [[ -f "${PROJ_NAME}.appDef" && -d "${PROJ_DIR}" ]]; then
    echo "Moving ${PROJ_NAME}.appDef and ${PROJ_DIR}"
    mv "${PROJ_NAME}.appDef" build.appDef
    mv "${PROJ_DIR}" build_data
  else
    echo "ERROR: Project appDef or project data not found"
    exit 3
  fi
  
  PUBLISH_PROPERTIES="build_data/publish/properties.json"
  if [ -f "${PUBLISH_PROPERTIES}" ]; then
      # Handle spaces in properties values
      # https://stackoverflow.com/a/48513046/35577
      values=$(cat "${PUBLISH_PROPERTIES}")
      while read -rd $'' line
      do
          echo "exporting ${line}"
          export "${line?}"
      done < <(jq -r <<<"$values" 'to_entries|map("\(.key)=\(.value)\u0000")[]')

      OUTPUT_PUBLISH_PROPERTIES="${OUTPUT_DIR}/publish-properties.json"
      # Add addition properties
      PUBLISH_SE_RECORD="build_data/publish/se-record.json"
      if [[ -f "${PUBLISH_SE_RECORD}" && "$(jq -r '. | length' "${PUBLISH_SE_RECORD}")" == "1" ]]; then
        # if there is at least one Scripture Earth record
        # Note: it is possible to have the record, but not the notify property -- "|| true" eats the error and still returns blank
        PUBLISH_NOTIFY_SCRIPTURE_EARTH=$(xmlstarlet sel -t -v "/app-definition/publishing/scripture-earth/@notify" build.appDef || true)
        PUBLISH_NOTIFY_SCRIPTURE_EARTH_ID=$(jq -r '.["0"].relationships.idx' "${PUBLISH_SE_RECORD}")
        if [[ "${PUBLISH_NOTIFY_SCRIPTURE_EARTH}" == "true" ]]; then
          echo "Notify Scripture Earth: id=${PUBLISH_NOTIFY_SCRIPTURE_EARTH_ID}"
          # If the "Notify Scripture Earth" property is enabled in the AppDef
          PUBLISH_TMP=$(mktemp)
          PUBLISH_NOTIFY_TYPE=$(jq -r '.PUBLISH_NOTIFY | type' "${PUBLISH_PROPERTIES}")
          # We are currently only supporting notifying Scripture Earth of product updates.
          # However, Kalaam has expressed interested in being notified as well. This would
          # allow Kalaam to add a PUBLISH_NOTIFY publishing property. We would also have to
          # implement something in publish.sh to notify their server correctly.
          if [[ "${PUBLISH_NOTIFY_TYPE}" == "null" ]]; then
            # There is no property so set it (as an array) to Scripture Earth entry
            jq -cM '.PUBLISH_NOTIFY += "SCRIPTURE_EARTH"' "${PUBLISH_PROPERTIES}" > "${PUBLISH_TMP}"
          elif [ "${PUBLISH_NOTIFY_TYPE}" == "string" ]; then
            # There is an existing property so convert to an array and add to Scripture Earth entry.
            # We are only going to deal with one item being there. If there will be multiple, then we
            # will have to split the string and create an array of the results
            PUBLISH_NOTIFY_CURRENT=$(jq -r '.PUBLISH_NOTIFY' "${PUBLISH_PROPERTIES}")
            jq -cM --arg cur "${PUBLISH_NOTIFY_CURRENT}" '.PUBLISH_NOTIFY += ",SCRIPTURE_EARTH"' "${PUBLISH_PROPERTIES}" > "${PUBLISH_TMP}"
          fi
          cp "${PUBLISH_TMP}" "${OUTPUT_PUBLISH_PROPERTIES}"
          jq -cM --arg idx "${PUBLISH_NOTIFY_SCRIPTURE_EARTH_ID}" '.SCRIPTURE_EARTH_ID = $idx' "${OUTPUT_PUBLISH_PROPERTIES}" > "${PUBLISH_TMP}"
          cp "${PUBLISH_TMP}" "${OUTPUT_PUBLISH_PROPERTIES}"
        fi
      fi

      # If modern-pwa, then update the subdirectory configuration from the rclone publish path if not defined
      for target in $TARGETS; do
        if [ "$target" = "modern-pwa" ]; then
          INPUT_PUBLISH_PROPERTIES=$PUBLISH_PROPERTIES
          if [ -f "${OUTPUT_PUBLISH_PROPERTIES}" ]; then
            INPUT_PUBLISH_PROPERTIES=$OUTPUT_PUBLISH_PROPERTIES
          fi
          if jq -e '.PUBLISH_CLOUD_REMOTE_PATH' "${INPUT_PUBLISH_PROPERTIES}" >/dev/null; then
            if ! xmlstarlet sel -t -v "/app-definition/pwa-manifest/pwa-sub-directory" build.appDef 2>/dev/null; then
                echo "PUBLISH_CLOUD_REMOTE_PATH exists, but PWA Sub Directory is missing."
                echo "PUBLISH_CLOUD_REMOTE_PATH=${PUBLISH_CLOUD_REMOTE_PATH} so update PWA Sub Directory=/${PUBLISH_CLOUD_REMOTE_PATH}"
                APPDEF_TMP=$(mktemp)
                xmlstarlet ed -s "/app-definition/pwa-manifest" -t elem -n "pwa-sub-directory" -v "/${PUBLISH_CLOUD_REMOTE_PATH}" build.appDef > "${APPDEF_TMP}"
                cp "${APPDEF_TMP}" build.appDef
            fi
          else
            PWA_SUBDIR=$(xmllint --xpath "/app-definition/pwa-manifest/pwa-sub-directory/text()" build.appDef 2>/dev/null || echo "")
            if [ "$PWA_SUBDIR" != "" ]; then
              echo "PUBLISH_CLOUD_REMOTE_PATH does not exist, but PWA Sub Directory is set."
              echo "PWA Sub Directory=${PWA_SUBDIR} so update PUBLISH_CLOUD_REMOTE_PATH=${PWA_SUBDIR#/}"
              PUBLISH_TMP=$(mktemp)
              jq -cM ".PUBLISH_CLOUD_REMOTE_PATH += \"${PWA_SUBDIR#/}\"" "${INPUT_PUBLISH_PROPERTIES}" > "${PUBLISH_TMP}"
              cp "${PUBLISH_TMP}" "${OUTPUT_PUBLISH_PROPERTIES}"
            fi
          fi
        fi
      done

      if [ ! -f "${OUTPUT_PUBLISH_PROPERTIES}" ]; then
        # if no Scripture Earth record, then copy straight as normal
        cp "${PUBLISH_PROPERTIES}" "${OUTPUT_PUBLISH_PROPERTIES}"
      fi

      cat "${OUTPUT_PUBLISH_PROPERTIES}"
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
    "asset-package") build_asset_package ;;
    "play-listing") build_play_listing ;;
    "html") build_html ;;
    "pwa") build_pwa ;;
    "modern-pwa") build_modern_pwa ;;
    *) build_gradle "$target" ;;
  esac
done

complete_successful_build
