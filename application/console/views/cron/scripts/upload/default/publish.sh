#!/bin/bash

google_play() {
  echo "OUTPUT_DIR=${OUTPUT_DIR}"
  PAJ="${SECRETS_DIR}/playstore_api.json"
  cd "$ARTIFACTS_DIR" || exit 1
  set +x
  set -o pipefail

  if [[ -z "${PUBLISH_NO_APK}" ]]; then
    echo "Publishing APK"
    APK_OPT = "-b ./*.apk "
  else
    echo "Not publishing APK"
    APK_OPT = "--skip_upload_apk true "
  fi
  if [ -z "$PROMOTE_FROM" ]; then
    fastlane supply -j "$PAJ" ${APK_OPT} -p "$(cat package_name.txt)" --track "$CHANNEL" -m play-listing | tee ${OUTPUT_DIR}/console.log
  else
    fastlane supply -j "$PAJ" ${APK_OPT} -p "$(cat package_name.txt)" --track "$PROMOTE_FROM" --track_promote_to "$CHANNEL" -m play-listing | tee ${OUTPUT_DIR}/console.log
  fi
  exit_code=$?
  set +o pipefail
  echo "ls -l ${OUTPUT_DIR}"
  ls -l ${OUTPUT_DIR}
  return ${exit_code}
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

echo "TARGETS: $TARGETS"
env
for target in $TARGETS
do
  case "$target" in
    "google-play") google_play ;;
    *) publish_gradle "$target" ;;
  esac
  if [ $? -ne 0 ]; then
    echo "Target ${target} failed"
    exit 1
  fi
done
