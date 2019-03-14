#!/bin/bash

google_play() {
  PAJ="${SECRETS_DIR}/playstore_api.json"
  cd $ARTIFACTS_DIR
  set +x
  if [ -z "$PROMOTE_FROM" ]; then	fastlane supply -j $PAJ -b *.apk -p $(cat package_name.txt) --track $CHANNEL -m play-listing; else fastlane supply -j $PAJ -b *.apk -p $(cat package_name.txt) --track $PROMOTE_FROM --track_promote_to $CHANNEL -m play-listing; fi
}
publish_gradle() {
  echo "Gradle $1"
  if [ -f "${PROJECT_DIR}/publish.gradle" ]; then
    pushd $PROJECT_DIR
    gradle $1
    popd
  elif [ -f "${SCRIPT_DIR}/publish.gradle" ]; then
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
    "google-play") google_play ;;
    *) publish_gradle $target ;;
  esac
done
