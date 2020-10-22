#!/usr/bin/env bash
set -e -o pipefail
set -x
LOG_FILE="${OUTPUT_DIR}"/console.log
exec > >(tee "${LOG_FILE}") 2>&1

publish_google_play() {
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  export SUPPLY_JSON_KEY="${SECRETS_DIR}/google_play_store/${PUBLISHER}/playstore_api.json"
  cd "$ARTIFACTS_DIR" || exit 1
  SUPPLY_PACKAGE_NAME="$(cat package_name.txt)"
  export SUPPLY_PACKAGE_NAME
  SUPPLY_VERSION_CODE="$(cat version_code.txt)"
  export SUPPLY_VERSION_CODE
  export SUPPLY_METADATA_PATH="play-listing"

  if [[ -n "${PUBLISH_DRAFT}" ]]; then
    echo "Publishing Draft"
    export SUPPLY_RELEASE_STATUS=draft
    # On the initial publish, an org admin has to create the app store entry and upload the APK
    # to associate the entry with the package name and keystore
    # Google Play APIs have changed so that you can't re-upload the APK. It gives a duplicate
    # version code error.
    if [[ "${SUPPLY_VERSION_CODE}" == "${PUBLISH_GOOGLE_PLAY_UPLOADED_VERSION_CODE}" ]]; then
      if [[ "${BUILD_NUMBER}" != "${PUBLISH_GOOGLE_PLAY_UPLOADED_BUILD_ID}" ]]; then
        echo "ERROR: Duplicate version code used on different builds during initial publish"
        exit 1
      fi
      PUBLISH_NO_APK=1
    fi
  fi
  if [[ -z "${PUBLISH_NO_APK}" ]]; then
    if [[ "${#APK_FILES[@]}" -gt 1 ]]; then
      echo "Publishing APKs"
      # Build a comma-separated list of files
      SUPPLY_APK_PATHS=$(find . -name "*.apk" | tr '\n' ',')
      export SUPPLY_APK_PATHS
    else
      echo "Publishing APK"
      export SUPPLY_APK="${APK_FILES[0]}"
    fi
  else
    echo "Not publishing APK"
    export SUPPLY_SKIP_UPLOAD_APK=true
  fi
  if [ -n "$PROMOTE_FROM" ]; then
    export SUPPLY_TRACK="${PROMOTE_FROM}"
    export SUPPLY_TRACK_PROMOTE_TO="${CHANNEL}"
  else
    export SUPPLY_TRACK="${CHANNEL}"
  fi
  env | grep "SUPPLY_"
  fastlane supply
  echo "https://play.google.com/store/apps/details?id=${SUPPLY_PACKAGE_NAME}" > "${OUTPUT_DIR}"/publish_url.txt
  echo "ls -l ${OUTPUT_DIR}"
  ls -l "${OUTPUT_DIR}"
}

publish_s3_bucket() {
  CREDENTIALS="${SECRETS_DIR}/s3_bucket/${PUBLISHER}/credentials"
  CONFIG="${SECRETS_DIR}/s3_bucket/${PUBLISHER}/config"
  SRC_FILE="${APK_FILES[0]}"
  DEST_BUCKET_PATH=$(cat "${SECRETS_DIR}/s3_bucket/${PUBLISHER}/bucket")
  DEST_FILE="$(basename "${SRC_FILE}")"

  IFS=/ read -r DEST_BUCKET DEST_PATH <<< "${DEST_BUCKET_PATH}"
  if [[ "${DEST_PATH}" == "" ]]; then
    PUBLISH_URL="https://${DEST_BUCKET}.s3.amazonaws.com/${DEST_FILE}"
  else
    PUBLISH_URL="https://${DEST_BUCKET}.s3.amazonaws.com/${DEST_PATH}/${DEST_FILE}"
  fi

  echo "CREDENTIALS=${CREDENTIALS}"
  echo "CONFIG=${CONFIG}"
  echo "SRC_FILE=${SRC_FILE}"
  echo "DEST_BUCKET_PATH=${DEST_BUCKET_PATH}"
  echo "DEST_FILE=${DEST_FILE}"
  echo "PUBLISH_URL=${PUBLISH_URL}"

  AWS_SHARED_CREDENTIALS_FILE="${CREDENTIALS}" AWS_CONFIG_FILE="${CONFIG}" aws s3 cp --acl public-read "${SRC_FILE}" "s3://${DEST_BUCKET_PATH}/${DEST_FILE}"

  echo "${PUBLISH_URL}" > "${OUTPUT_DIR}/publish_url.txt"
}

publish_rclone() {
  CONFIG="${SECRETS_DIR}/rclone/${PUBLISHER}/rclone.conf"
  RCLONE="rclone -v --config ${CONFIG}"
  if [[ "${PUBLISH_CLOUD_REMOTE}" == "" ]]; then
    PUBLISH_CLOUD_REMOTE=$(${RCLONE} config dump | jq -r 'keys_unsorted|.[0]')
    if [[ "${PUBLISH_CLOUD_REMOTE}" == "" ]]; then
      echo "ERROR: No PUBLISH_CLOUD_REMOTE or default remote in ${CONFIG}"
      exit 2
    fi
  fi

  # Detect the type of publish: apk, html, pwa
  APK_COUNT=$(find "${ARTIFACTS_DIR}" -name "*.apk" | wc -l)
  PUBLISH_FILE=""
  if [[ ${APK_COUNT} -gt 0 ]]; then
    # apk: publish all the apks
    PUBLISH_CLOUD_SOURCE_PATH="${ARTIFACTS_DIR}/\*.apk"
  elif [[ -f "${ARTIFACTS_DIR}/pwa.zip" ]]; then
    # pwa: unzip the files to a directory and push the directory
    mkdir "${ARTIFACTS_DIR}/pwa"
    unzip "${ARTIFACTS_DIR}/pwa.zip" -d "${ARTIFACTS_DIR}/pwa"
    PUBLISH_CLOUD_SOURCE_PATH="${ARTIFACTS_DIR}/pwa"
    PUBLISH_FILE="index.html"
  elif [[ -f "${ARTIFACTS_DIR}/html.zip" ]]; then
    # html: unzip the files to a directory and push the directory
    mkdir "${ARTIFACTS_DIR}/html"
    unzip "${ARTIFACTS_DIR}/html.zip" -d "${ARTIFACTS_DIR}/html"
    PUBLISH_CLOUD_SOURCE_PATH="${ARTIFACTS_DIR}/html"
    PUBLISH_FILE="index.html"
  else
    # Fallback to publishing all artifacts
    PUBLISH_CLOUD_SOURCE_PATH="${ARTIFACTS_DIR}"
    PUBLISH_CLOUD_BACKUP=0
  fi

  if [[ "${PUBLISH_CLOUD_REMOTE_PATH}" == "" ]]; then
    echo "ERROR: PUBLISH_CLOUD_REMOTE_PATH is not set"
    exit 2
  fi

  PUBLISH_CLOUD_REMOTE_PATH_ALLOWED=$(${RCLONE} config dump | jq -r ".[\"${PUBLISH_CLOUD_REMOTE}\"].remote_path_allowed")

  if [[ "${PUBLISH_CLOUD_REMOTE_PATH_ALLOWED}" != "" ]]; then
      [[ ${PUBLISH_CLOUD_REMOTE_PATH} =~ ${PUBLISH_CLOUD_REMOTE_PATH_ALLOWED} ]]
      if [[ "${#BASH_REMATCH[0]}" == "0" ]]; then
        echo "ERROR: PUBLISH_CLOUD_REMOTE_PATH=${PUBLISH_CLOUD_REMOTE_PATH} doesn't match PUBLISH_CLOUD_REMOTE_PATH_ALLOWED=${PUBLISH_CLOUD_REMOTE_PATH_ALLOWED}"
        exit 2
      fi
  fi

  if [[ "${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}" == "" ]]; then
    PUBLISH_CLOUD_BACKUP_REMOTE_PATH="backups"
  fi

  echo "PUBLISH_CLOUD_SOURCE_PATH=${PUBLISH_CLOUD_SOURCE_PATH}"
  echo "PUBLISH_CLOUD_REMOTE=${PUBLISH_CLOUD_REMOTE}"
  echo "PUBLISH_CLOUD_REMOTE_PATH=${PUBLISH_CLOUD_REMOTE_PATH}"
  echo "PUBLISH_CLOUD_REMOTE_PATH_ALLOWED=${PUBLISH_CLOUD_REMOTE_PATH_ALLOWED}"
  echo "PUBLISH_CLOUD_BACKUP=${PUBLISH_CLOUD_BACKUP}"
  echo "PUBLISH_CLOUD_BACKUP_ZIP=${PUBLISH_CLOUD_BACKUP_ZIP}"
  echo "PUBLISH_CLOUD_BACKUP_REMOTE_PATH=${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}"

  # if there are files to backup and backup is requested...
  set +e
  BACKUP_FILE_COUNT=$(${RCLONE} size "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_PATH}" --json 2>/dev/null | jq -r ".count")
  set -e
  echo "Current file count: ${BACKUP_FILE_COUNT}"
  if [[ "${PUBLISH_CLOUD_BACKUP}" == "1" && ${BACKUP_FILE_COUNT} -gt 0 ]]; then
    DATE=$(date -u +"%Y-%m-%d_%H-%M-%S")
    if [[ "${PUBLISH_CLOUD_BACKUP_ZIP}" == "1" && "${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}" != "" ]]; then
        # When performing a zip backup, we have to do download the current files to zip them and then re-upload them
        # It is likely that the new files are similar to the old ones.  So we will:
        # 1. Copy the new files to the backup directory
        mkdir -p "${ARTIFACTS_DIR}/Backup"
        cp -r "${PUBLISH_CLOUD_SOURCE_PATH}" "${ARTIFACTS_DIR}/Backup"
        # 2. Sync the current files to the backup directory
        ${RCLONE} sync "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_PATH}" "${ARTIFACTS_DIR}/Backup"
        # 3. Zip the files
        BACKUP_FILENAME="$(basename "${PUBLISH_CLOUD_REMOTE_PATH}")-${DATE}.zip"
        pushd "${ARTIFACTS_DIR}/Backup"
        zip -qr ../"${BACKUP_FILENAME}" -- *
        popd
        # 4. Sync the new files to the remote
        ${RCLONE} sync "${PUBLISH_CLOUD_SOURCE_PATH}" "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_PATH}"
        # 5. Copy the backup to the Backup path
        ${RCLONE} mkdir "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}"
        ${RCLONE} copy "${ARTIFACTS_DIR}/${BACKUP_FILENAME}" "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}/${PUBLISH_CLOUD_REMOTE_PATH}"
    else
        ${RCLONE} mkdir "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}/${PUBLISH_CLOUD_REMOTE_PATH}/${DATE}"
        ${RCLONE} copy "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_PATH}" "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}/${PUBLISH_CLOUD_REMOTE_PATH}/${DATE}" 
    fi
  fi

  ${RCLONE} mkdir "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_PATH}"
  ${RCLONE} sync "${PUBLISH_CLOUD_SOURCE_PATH}" "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_PATH}"

  PUBLISH_BASE_URL=$(${RCLONE} config dump | jq -r ".[\"${PUBLISH_CLOUD_REMOTE}\"].public_url")
  if [[ "${PUBLISH_BASE_URL}" == "null" ]]; then
      echo "ERROR: No public_url for rclone config ${PUBLISH_CLOUD_REMOTE}"
      exit 2
  fi
  PUBLISH_SERVER_PATH_ROOT=$(${RCLONE} config dump | jq -r ".[\"${PUBLISH_CLOUD_REMOTE}\"].server_root")
  PUBLISH_REMOTE_PATH="${PUBLISH_CLOUD_REMOTE_PATH}"
  if [[ "${PUBLISH_SERVER_PATH_ROOT}" != "null" ]]; then
    PUBLISH_REMOTE_PATH=${PUBLISH_REMOTE_PATH//$PUBLISH_SERVER_PATH_ROOT\//}
  fi
  PUBLISH_URL="${PUBLISH_BASE_URL}/${PUBLISH_REMOTE_PATH}/${PUBLISH_FILE}"
  echo "${PUBLISH_URL}" > "${OUTPUT_DIR}/publish_url.txt"
}

publish_gradle() {
  echo "Gradle $1"
  if [ -f "${PROJECT_DIR}/publish.gradle" ]; then
    pushd "$PROJECT_DIR" || exit 1
    gradle "$1"
    popd || exit 1
  elif [ -f "${SCRIPT_DIR}/publish.gradle" ]; then
    pushd "$SCRIPT_DIR" || exit 1
    gradle "$1"
    popd || exit 1
  fi
}

prepare_publish() {
  PUBLISH_PROPERTIES="${ARTIFACTS_DIR}/publish-properties.json"
  if [[ -f "${PUBLISH_PROPERTIES}" ]]; then
      for s in $(jq -r "to_entries|map(\"\(.key)=\(.value|tostring)\")|.[]" "${PUBLISH_PROPERTIES}"); do
        # shellcheck disable=SC2086 disable=SC2163
        export $s
      done
  fi
}

APK_FILES=( "${ARTIFACTS_DIR}"/*.apk )

prepare_publish

echo "TARGETS: $TARGETS"
env
for target in $TARGETS
do
  case "$target" in
    "google-play") publish_google_play ;;
    "rclone") publish_rclone ;;
    "s3-bucket") publish_s3_bucket ;;
    *) publish_gradle "$target" ;;
  esac
done
