#!/bin/bash

publish_google_play() {
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  PAJ="${SECRETS_DIR}/google_play_store/${PUBLISHER}/playstore_api.json"
  cd "$ARTIFACTS_DIR" || exit 1
  set +x
  set -o pipefail
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
  if [ -z "$PROMOTE_FROM" ]; then
    # shellcheck disable=SC2086
    fastlane supply -j "$PAJ" ${APK_OPT} -p "$(cat package_name.txt)" --track "$CHANNEL" -m play-listing |& tee "${OUTPUT_DIR}"/console.log
  else
    # shellcheck disable=SC2086
    fastlane supply -j "$PAJ" ${APK_OPT} -p "$(cat package_name.txt)" --track "$PROMOTE_FROM" --track_promote_to "$CHANNEL" -m play-listing |& tee "${OUTPUT_DIR}"/console.log
  fi
  exit_code=$?
  set +o pipefail
  echo "ls -l ${OUTPUT_DIR}"
  ls -l "${OUTPUT_DIR}"
  return ${exit_code}
}

publish_s3_bucket() {
  # shellcheck disable=SC2034
  AWS_SHARED_CREDENTIALS_FILE="${SECRETS_DIR}/s3_bucket/${PUBLISHER}/credentials"
  # shellcheck disable=SC2034
  AWS_CONFIG_FILE="${SECRETS_DIR}/s3_bucket/${PUBLISHER}/config"
  DEST_BUCKET=$(cat "${SECRETS_DIR}/s3_bucket/${PUBLISHER}/bucket")
  for apk in $APK_FILES
  do
    aws s3 cp "$apk" "${DEST_BUCKET}"
  done
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
  # shellcheck disable=SC2181
  if [ $? -ne 0 ]; then
    echo "Target ${target} failed"
    exit 1
  fi
done
