#!/usr/bin/env bash
set -eu -o pipefail

publish_google_play() {
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  PAJ="${SECRETS_DIR}/google_play_store/${PUBLISHER}/playstore_api.json"
  cd "$ARTIFACTS_DIR" || exit 1
  if [[ -z "${PUBLISH_NO_APK}" ]]; then
    echo "Publishing APK"
    if [[ "${#APK_FILES[@]}" -gt 1 ]]; then
      echo "Too many APK files: ${#APK_FILES[@]}"
      exit 2
    fi
    APK_OPT="-b ${APK_FILES[0]} "
  else
    echo "Not publishing APK"
    APK_OPT="--skip_upload_apk true "
  fi
  echo "APK_OPT=${APK_OPT}"
  PACKAGE_NAME=$(cat package_name.txt)
  if [ -z "$PROMOTE_FROM" ]; then
    # shellcheck disable=SC2086
    fastlane supply -j "$PAJ" ${APK_OPT} -p "$PACKAGE_NAME" --track "$CHANNEL" -m play-listing |& tee "${OUTPUT_DIR}"/console.log
  else
    # shellcheck disable=SC2086
    fastlane supply -j "$PAJ" ${APK_OPT} -p "$PACKAGE_NAME" --track "$PROMOTE_FROM" --track_promote_to "$CHANNEL" -m play-listing |& tee "${OUTPUT_DIR}"/console.log
  fi
  echo "https://play.google.com/store/apps/details?id=${PACKAGE_NAME}" > "${OUTPUT_DIR}"/publish_url.txt
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
    PUBLISH_URL="https:${DEST_BUCKET}.s3.amazonaws.com/${DEST_FILE}"
  else
    PUBLISH_URL="https://${DEST_BUCKET}.s3.amazonaws.com/${DEST_PATH}/${DEST_FILE}"
  fi

  echo "CREDENTIALS=${CREDENTIALS}"
  echo "CONFIG=${CONFIG}"
  echo "SRC_FILE=${SRC_FILE}"
  echo "DEST_BUCKET=${DEST_BUCKET_PATH}"
  echo "DEST_FILE=${DEST_FILE}"
  echo "URL=${PUBLISH_URL}"

  AWS_SHARED_CREDENTIALS_FILE="${CREDENTIALS}" AWS_CONFIG_FILE="${CONFIG}" aws s3 cp "${SRC_FILE}" "${DEST_BUCKET}/${DEST_FILE}" |& tee -a "${OUTPUT_DIR}"/console.log

  echo "${PUBLISH_URL}" > "${OUTPUT_DIR}"/publish_url.txt
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

APK_FILES=( "${ARTIFACTS_DIR}"/*.apk )

echo "TARGETS: $TARGETS"
env
for target in $TARGETS
do
  case "$target" in
    "google-play") publish_google_play ;;
    "s3-bucket") publish_s3_bucket ;;
    *) publish_gradle "$target" ;;
  esac
done
