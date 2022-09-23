#!/usr/bin/env bash
shopt -s nullglob
set -e -o pipefail
set -x
LOG_FILE="${OUTPUT_DIR}"/console.log
exec > >(tee "${LOG_FILE}") 2>&1

export PATH="$HOME/.rbenv/bin:$PATH"
eval "$(rbenv init -)"
sync_secrets() {
  SECRETS_SUBDIR=$1
  SECRETS_S3="s3://${SECRETS_BUCKET}/jenkins/publish"
  echo "sync secrets"
  echo "SECRETS_SUBDIR = ${SECRETS_SUBDIR}"
  aws s3 sync "${SECRETS_S3}/${SECRETS_SUBDIR}" "${SECRETS_DIR}"
}

publish_google_play() {
  fastlane env
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  SECRETS_SUBDIR="google_play_store/${PUBLISHER}"
  sync_secrets ${SECRETS_SUBDIR}
  export SUPPLY_JSON_KEY="${SECRETS_DIR}/playstore_api.json"
  cd "$ARTIFACTS_DIR" || exit 1
  PACKAGE_NAME="$(cat package_name.txt)"
  export SUPPLY_PACKAGE_NAME="${PACKAGE_NAME}"
  VERSION_CODE="$(cat version_code.txt)"
  export SUPPLY_METADATA_PATH="play-listing"

  if [[ -n "${PUBLISH_NO_APK}" ]]; then
    export SUPPLY_SKIP_UPLOAD_APK=true
    export SUPPLY_SKIP_UPLOAD_AAB=true
    echo "APK: None (PUBLISH_NO_APK=1), just republish"
  elif [[ "${#AAB_FILES[@]}" -gt 0 ]]; then
    export SUPPLY_AAB="${AAB_FILES[0]}"
    export SUPPLY_SKIP_UPLOAD_APK=true
    echo "AAB: ${SUPPLY_AAB}"
  elif [[ "${#APK_FILES[@]}" -gt 1 ]]; then
    # Build a comma-separated list of files
    SUPPLY_APK_PATHS=$(find . -name "*.apk" | tr '\n' ',')
    export SUPPLY_APK_PATHS
    echo "APKs: ${SUPPLY_APK_PATHS}"
  elif [[ "${#APK_FILES[@]}" -gt 0 ]]; then
    export SUPPLY_APK="${APK_FILES[0]}"
    echo "APK: ${SUPPLY_APK}"
  else
    echo "APK: Missing AAB or APK!"
    exit 1
  fi

  if [[ -n "${PUBLISH_GOOGLE_PLAY_DRAFT}" ]]; then
    echo "Publishing Draft"
    export SUPPLY_RELEASE_STATUS=draft
    # On the initial publish, a user has to create the app store entry and upload the APK
    # to associate the entry with the package name and keystore
    # Google Play APIs have changed so that we can't re-upload the APK. It gives a duplicate
    # version code error. So we need to skip uploading APK that was uploaded by the user
    if [[ "${VERSION_CODE}" == "${PUBLISH_GOOGLE_PLAY_UPLOADED_VERSION_CODE}" ]]; then
      if [[ "${BUILD_NUMBER}" != "${PUBLISH_GOOGLE_PLAY_UPLOADED_BUILD_ID}" ]]; then
        echo "ERROR: Duplicate version code used on different builds during initial publish"
        exit 1
      fi
      echo "Not publishing APK(s) or AAB ... uploaded by user"
      export SUPPLY_SKIP_UPLOAD_APK=true
      export SUPPLY_SKIP_UPLOAD_AAB=true
    else
      echo "Publishing APK(s) ... rebuilt after first uploaded by user"
    fi

  fi

  # https://stackoverflow.com/a/23357277/35577
  CHANGELOGS=()
  if [[ -z "${PUBLISH_NO_APK}" ]]; then
    while IFS=  read -r -d $'\0'; do
      CHANGELOGS+=("$REPLY")
    done < <(find "${ARTIFACTS_DIR}"/play-listing -name "[0-9]*.txt" -print0 | grep -FzZ 'changelogs')
  fi

  if [[ "${#CHANGELOGS[@]}" -gt 0 ]]; then
    APK_VERSION_CODE="$(basename "${CHANGELOGS[0]%.txt}")"
    export SUPPLY_VERSION_CODE="${APK_VERSION_CODE}"
  else
    export SUPPLY_SKIP_UPLOAD_CHANGELOGS=true
  fi

  if [ -n "$PROMOTE_FROM" ]; then
    export SUPPLY_TRACK="${PROMOTE_FROM}"
    export SUPPLY_TRACK_PROMOTE_TO="${CHANNEL}"
  else
    export SUPPLY_TRACK="${CHANNEL}"
  fi
  env | grep "SUPPLY_"
  fastlane supply
  if [[ -n "${PUBLISH_NO_APK}" ]]; then
    PUBLISH_SIZE=0
    PERMALINK_URL=""
  else
    PUBLISH_SIZE="$(stat --format="%s" "${APK_FILES[0]}")"
    PERMALINK_URL="${UI_URL}/api/products/${PRODUCT_ID}/files/published/apk"
  fi
  PUBLISH_URL="https://play.google.com/store/apps/details?id=${PACKAGE_NAME}"
  echo "${PUBLISH_URL}" > "${OUTPUT_DIR}/publish_url.txt"
  echo "ls -l ${OUTPUT_DIR}"
  ls -l "${OUTPUT_DIR}"
}

publish_s3_bucket() {
  ZIP_FILES=( "${ARTIFACTS_DIR}"/asset-package/*.zip )

  SECRETS_SUBDIR="s3_bucket/${PUBLISHER}"
  sync_secrets ${SECRETS_SUBDIR}
  CREDENTIALS="${SECRETS_DIR}/credentials"
  CONFIG="${SECRETS_DIR}/config"
  DEST_BUCKET_PATH=$(cat "${SECRETS_DIR}/bucket")
  if [[ "${DEST_BUCKET_PATH}" == "" ]]; then
    echo "bucket file for S3 ${PUBLISHER} is empty!"
    exit 1
  fi

  # add the product id to make it unique
  if [[ "${PRODUCT_ID}" != "" && "${PUBLISH_S3_ROOT}" == "" ]]; then
      DEST_BUCKET_PATH="${DEST_BUCKET_PATH}/${PRODUCT_ID}"
  fi

  IFS=/ read -r DEST_BUCKET DEST_PATH <<< "${DEST_BUCKET_PATH}"

  # Detect tye type of publish: apk or asset-package
  if [[ "${#APK_FILES[@]}" -gt 0 ]]; then
    # apk: publish all the apks
    PUBLISH_S3_INCLUDE="*.apk"
    PUBLISH_S3_SOURCH_PATH="${ARTIFACTS_DIR}"
    PERMALINK_URL="${UI_URL}/api/products/${PRODUCT_ID}/files/published/apk"
    SRC_FILE="${APK_FILES[0]}"
  elif [[ "${#ZIP_FILES[@]}" -gt 0 ]]; then
    # asset-package: publish all the zip files
    PUBLISH_S3_INCLUDE="*.zip"
    PUBLISH_S3_SOURCH_PATH="${ARTIFACTS_DIR}/asset-package"
    PERMALINK_URL="${UI_URL}/api/products/${PRODUCT_ID}/files/published/asset-package"
    SRC_FILE="${ZIP_FILES[0]}"
  fi
  PUBLISH_SIZE="$(stat --format="%s" "${SRC_FILE}")}"
  DEST_FILE="$(basename "${SRC_FILE}")"

  if [[ "${DEST_PATH}" == "" ]]; then
    PUBLISH_URL="https://${DEST_BUCKET}.s3.amazonaws.com/${DEST_FILE}"
  else
    PUBLISH_URL="https://${DEST_BUCKET}.s3.amazonaws.com/${DEST_PATH}/${DEST_FILE}"
  fi

  echo "CREDENTIALS=${CREDENTIALS}"
  echo "CONFIG=${CONFIG}"
  echo "SRC_FILE=${SRC_FILE}"
  echo "PUBLISH_S3_SOURCH_PATH=${PUBLISH_S3_SOURCH_PATH}"
  echo "PUBLISH_S3_INCLUDE=${PUBLISH_S3_INCLUDE}"
  echo "DEST_BUCKET_PATH=${DEST_BUCKET_PATH}"
  echo "DEST_FILE=${DEST_FILE}"
  echo "PUBLISH_URL=${PUBLISH_URL}"

  AWS_SHARED_CREDENTIALS_FILE="${CREDENTIALS}" AWS_CONFIG_FILE="${CONFIG}" aws s3 cp "${PUBLISH_S3_SOURCH_PATH}" "s3://${DEST_BUCKET_PATH}" --acl public-read --acl bucket-owner-full-control --recursive --exclude "*" --include "${PUBLISH_S3_INCLUDE}"

  echo "${PUBLISH_URL}" > "${OUTPUT_DIR}/publish_url.txt"
}

publish_rclone() {
  SECRETS_SUBDIR="rclone/${PUBLISHER}"
  sync_secrets ${SECRETS_SUBDIR}
  CONFIG="${SECRETS_DIR}/rclone.conf"
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

  if [[ "${PUBLISH_CLOUD_COMMAND}" == "" ]]; then
    PUBLISH_CLOUD_COMMAND=sync
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

  # Add after the PUBLISH_CLOUD_REMOTE_PATH_ALLOWED has been check against PUBLISH_CLOUD_REMOTE_PATH
  PUBLISH_CLOUD_REMOTE_ROOT=$(${RCLONE} config dump | jq -r ".[\"${PUBLISH_CLOUD_REMOTE}\"].remote_root")
  if [[ "${PUBLISH_CLOUD_REMOTE_ROOT}" == "null" ]]; then
    # should be blank or end with slash so that it can be safely inserted below
    PUBLISH_CLOUD_REMOTE_ROOT=""
  else
    #ensure that it ends with a slash
    PUBLISH_CLOUD_REMOTE_ROOT="${PUBLISH_CLOUD_REMOTE_ROOT%/}/"
  fi

  echo "PUBLISH_CLOUD_SOURCE_PATH=${PUBLISH_CLOUD_SOURCE_PATH}"
  echo "PUBLISH_CLOUD_REMOTE=${PUBLISH_CLOUD_REMOTE}"
  echo "PUBLISH_CLOUD_REMOTE_ROOT=${PUBLISH_CLOUD_REMOTE_ROOT}"
  echo "PUBLISH_CLOUD_REMOTE_PATH=${PUBLISH_CLOUD_REMOTE_PATH}"
  echo "PUBLISH_CLOUD_REMOTE_PATH_ALLOWED=${PUBLISH_CLOUD_REMOTE_PATH_ALLOWED}"
  echo "PUBLISH_CLOUD_BACKUP=${PUBLISH_CLOUD_BACKUP}"
  echo "PUBLISH_CLOUD_BACKUP_ZIP=${PUBLISH_CLOUD_BACKUP_ZIP}"
  echo "PUBLISH_CLOUD_BACKUP_REMOTE_PATH=${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}"

  # if there are files to backup and backup is requested...
  set +e
  BACKUP_FILE_COUNT=$(${RCLONE} size "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_REMOTE_PATH}" --json 2>/dev/null | jq -r ".count")
  set -e
  echo "Current file count: ${BACKUP_FILE_COUNT}"
  if [[ "${PUBLISH_CLOUD_BACKUP}" == "1" && ${BACKUP_FILE_COUNT} -gt 0 ]]; then
    DATE=$(date -u +"%Y-%m-%d_%H-%M-%S")
    if [[ "${PUBLISH_CLOUD_BACKUP_ZIP}" == "1" && "${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}" != "" ]]; then
        # When performing a zip backup, we have to do download the current files to zip them and then re-upload them
        # It is likely that the new files are similar to the old ones.  So we will:
        # 1. Sync the new files to the backup directory (seed the directory so only reverse diffs are copied)
        ${RCLONE} sync "${PUBLISH_CLOUD_SOURCE_PATH}" "${ARTIFACTS_DIR}/Backup"
        # 2. Sync the current files to the backup directory
        ${RCLONE} sync "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_REMOTE_PATH}" "${ARTIFACTS_DIR}/Backup"
        # 3. Zip the files
        BACKUP_FILENAME="$(basename "${PUBLISH_CLOUD_REMOTE_PATH}")-${DATE}.zip"
        pushd "${ARTIFACTS_DIR}/Backup"
        zip -qr ../"${BACKUP_FILENAME}" -- *
        popd
        # 4. Copy the backup to the Backup path
        ${RCLONE} mkdir "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}"
        ${RCLONE} copy "${ARTIFACTS_DIR}/${BACKUP_FILENAME}" "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}/${PUBLISH_CLOUD_REMOTE_PATH}"
    else
        ${RCLONE} mkdir "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}/${PUBLISH_CLOUD_REMOTE_PATH}/${DATE}"
        ${RCLONE} copy "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_REMOTE_PATH}" "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_BACKUP_REMOTE_PATH}/${PUBLISH_CLOUD_REMOTE_PATH}/${DATE}"
    fi
  fi

  ${RCLONE} mkdir "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_REMOTE_PATH}"
  ${RCLONE} ${PUBLISH_CLOUD_COMMAND} -u "${PUBLISH_CLOUD_SOURCE_PATH}" "${PUBLISH_CLOUD_REMOTE}:${PUBLISH_CLOUD_REMOTE_ROOT}${PUBLISH_CLOUD_REMOTE_PATH}"

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
      # Handle spaces in properties values
      # https://stackoverflow.com/a/48513046/35577
      values=$(cat "${PUBLISH_PROPERTIES}")
      while read -rd $'' line
      do
          echo "exporting ${line}"
          export "${line?}"
      done < <(jq -r <<<"$values" 'to_entries|map("\(.key)=\(.value)\u0000")[]')
  fi

  PUBLISH_JSON="{}"
}

notify_scripture_earth_update_json() {
  local i_json="$1"
  local i_type="$2"
  local i_url="$3"

  echo "${i_json}" | jq \
    --arg type "${i_type}" \
    --arg idx "${SCRIPTURE_EARTH_ID}" \
    --arg url "${i_url}" \
    --arg email "${PROJECT_OWNER_EMAIL}" \
    --arg projectName "${PROJECT_NAME}" \
    --arg username "${PROJECT_OWNER_NAME}" \
    --arg organization "${PROJECT_ORGANIZATION}" \
    --arg project "${PROJECT_URL}" \
    '. + [ { type: $type, idx: $idx, url: $url, email: $email, projectName: $projectName, username: $username, organization: $organization}]'
}

notify_scripture_earth() {
  local notify_json="$1"
  # curl does not work! SE.org returns 406 Not Acceptable and can't figure out why.
  wget \
     --header "Content-Type: application/json" \
     --header "Accept: application/json" \
     --post-data "${notify_json}" \
     "https://scriptureearth.org/api/add_resource.php?v=1&key=${SCRIPTURE_EARTH_KEY}"

}

publish_base_update_json() {
  local i_json="$1"
  local version
  version="$(${APP_BUILDER_SCRIPT_PATH} "-?" | grep Version | cut -d\  -f2)"

  echo "${i_json}" | jq \
    --arg project_url "${PROJECT_URL}" \
    --arg project_name "${PROJECT_NAME}" \
    --arg product_name "${WORKFLOW_PRODUCT_NAME}" \
    --arg project_language "${PROJECT_LANGUAGE}" \
    --arg project_repo "${PROJECT_S3}" \
    --arg publish_url "${PUBLISH_URL}" \
    --arg permalink_url "${PERMALINK_URL}" \
    --arg size "${PUBLISH_SIZE}" \
    --arg app_builder "${APP_BUILDER_SCRIPT_PATH}" \
    --arg app_builder_version "${version}" \
    '. + { project_url: $project_url, project_name: $project_name, product_name: $product_name, project_language: $project_language, project_repo: $project_repo, publish_url: $publish_url, permalink_url: $permalink_url, size: $size, app_builder: $app_builder, app_builder_version: $app_builder_version }'
}

post_publish() {
  PUBLISH_JSON="$(publish_base_update_json "${PUBLISH_JSON}")"

  # The WORKFLOW_PRODUCT_NAME is a user generated name and we are expecting the name to
  # contain the keywords: ios, android, google (play), and pwa.  So the system admin
  # should be aware when adding new products.
  WORKFLOW_PRODUCT_NAME_LOWER=$(echo "${WORKFLOW_PRODUCT_NAME}" | awk '{print tolower($0)}')
  if [[ "${WORKFLOW_TYPE}" == "Startup" && "${PUBLISH_NOTIFY}" != "" ]]; then
    # See BuildEngineServiceBase::AddProductProperitiesToEnvironment for the list of properties
    # that are added.

    # Notify Scripture Earth
    if [[ "${PUBLISH_NOTIFY})" == *"SCRIPTURE_EARTH"* && "${SCRIPTURE_EARTH_KEY}" != "" ]]; then
      EMPTY_NOTIFY_JSON="[]"
      NOTIFY_JSON=$EMPTY_NOTIFY_JSON
      if [[ $WORKFLOW_PRODUCT_NAME_LOWER == *"ios"* ]]; then
        # Send Notification for iOS Asset Package
        NOTIFY_TYPE="ios"
        # change protocol to "asset://" so that container app will recognize as asset-package
        # shellcheck disable=SC2001
        NOTIFY_URL="$(sed -e 's/https*:/asset:/' <<< "$UI_URL")/api/products/${PRODUCT_ID}/files/published/asset-package"
        NOTIFY_JSON="$(notify_scripture_earth_update_json "${NOTIFY_JSON}" "${NOTIFY_TYPE}" "${NOTIFY_URL}")"
      elif [[ $WORKFLOW_PRODUCT_NAME_LOWER == *"android"* ]]; then
        # Send Notification for Android app to Google Play
        NOTIFY_TYPE="apk"
        NOTIFY_URL="${UI_URL}/api/products/${PRODUCT_ID}/files/published/apk"
        NOTIFY_JSON="$(notify_scripture_earth_update_json "${NOTIFY_JSON}" "${NOTIFY_TYPE}" "${NOTIFY_URL}")"
        if [[ $WORKFLOW_PRODUCT_NAME_LOWER == *"google"* ]]; then
          # Send additional notification for Android app to Google Play
          NOTIFY_TYPE="google_play"
          NOTIFY_URL="${PUBLISH_URL}"
          NOTIFY_JSON="$(notify_scripture_earth_update_json "${NOTIFY_JSON}" "${NOTIFY_TYPE}" "${NOTIFY_URL}")"
        fi
      elif [[ $WORKFLOW_PRODUCT_NAME_LOWER == *"pwa"* ]]; then
        # Send Notification for PWA
        NOTIFY_TYPE="sab_html"
        NOTIFY_URL="${PUBLISH_URL}"
        NOTIFY_JSON="$(notify_scripture_earth_update_json "${NOTIFY_JSON}" "${NOTIFY_TYPE}" "${NOTIFY_URL}")"
      fi

      if [[ "${NOTIFY_JSON}" != "{$EMPTY_NOTIFY_JSON}" ]]; then
        notify_scripture_earth "${NOTIFY_JSON}"
      fi
    fi
  fi

  echo "${PUBLISH_JSON}" > "${OUTPUT_DIR}/publish.json"
}

APK_FILES=( "${ARTIFACTS_DIR}"/*.apk )
AAB_FILES=( "${ARTIFACTS_DIR}"/*.aab )

prepare_publish
env | sort
echo "TARGETS: $TARGETS"
for target in $TARGETS
do
  case "$target" in
    "google-play") publish_google_play ;;
    "rclone") publish_rclone ;;
    "s3-bucket") publish_s3_bucket ;;
    *) publish_gradle "$target" ;;
  esac
done

post_publish
