#!/usr/bin/env bash

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
  PACKAGE_NAME=$(cat package_name.txt)
  if [ -z "$PROMOTE_FROM" ]; then
    # shellcheck disable=SC2086
    fastlane supply -j "$PAJ" ${APK_OPT} -p "$PACKAGE_NAME" --track "$CHANNEL" -m play-listing |& tee "${OUTPUT_DIR}"/console.log
  else
    # shellcheck disable=SC2086
    fastlane supply -j "$PAJ" ${APK_OPT} -p "$PACKAGE_NAME" --track "$PROMOTE_FROM" --track_promote_to "$CHANNEL" -m play-listing |& tee "${OUTPUT_DIR}"/console.log
  fi
  exit_code=$?
  set +o pipefail
  echo "https://play.google.com/store/apps/details?id=${PACKAGE_NAME}" > "${OUTPUT_DIR}"/publish_url.txt
  echo "ls -l ${OUTPUT_DIR}"
  ls -l "${OUTPUT_DIR}"
  return ${exit_code}
}

publish_s3_bucket() {
  CREDENTIALS="${SECRETS_DIR}/s3_bucket/${PUBLISHER}/credentials"
  CONFIG="${SECRETS_DIR}/s3_bucket/${PUBLISHER}/config"
  SRC_FILE="${APK_FILES[0]}"
  DEST_BUCKET=$(cat "${SECRETS_DIR}/s3_bucket/${PUBLISHER}/bucket")
  DEST_FILE="$(basename "${SRC_FILE}")"

  scheme="$(parse_scheme "${DEST_BUCKET}")"
  if [ "$scheme" != "s3" ]; then
    echo "Invalid Bucket Path: ${DEST_BUCKET}" > "${OUTPUT_DIR}"/console.log
    exit 1
  fi

  url_host="$(parse_host "${DEST_BUCKET}")"
  url_path="$(parse_path "${DEST_BUCKET}")"

  # if DEST_BUCKET=s3://foo : host= , path=//foo
  # if DEST_BUCKET=s3://foo/path : host=foo, path=/path/to
  if [[ "$url_host" == "" ]]; then
    url="https:${url_path}.s3.amazonaws.com/${DEST_FILE}"
  else
    url="https://${url_host}.s3.amazonaws.com${url_path}/${DEST_FILE}"
  fi

  echo "CREDENTIALS=${CREDENTIALS}"
  echo "CONFIG=${CONFIG}"
  echo "SRC_FILE=${SRC_FILE}"
  echo "DEST_BUCKET=${DEST_BUCKET}"
  echo "DEST_FILE=${DEST_FILE}"
  echo "URL=${url}"

  AWS_SHARED_CREDENTIALS_FILE="${CREDENTIALS}" AWS_CONFIG_FILE="${CONFIG}" aws s3 cp "${SRC_FILE}" "${DEST_BUCKET}/${DEST_FILE}" |& tee -a "${OUTPUT_DIR}"/console.log
  echo "${url}" > "${OUTPUT_DIR}"/publish_url.txt

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

# https://stackoverflow.com/a/45977232/35577
#
# Following regex is based on https://tools.ietf.org/html/rfc3986#appendix-B with
# additional sub-expressions to split authority into userinfo, host and port
#
readonly URI_REGEX='^(([^:/?#]+):)?(//((([^:/?#]+)@)?([^:/?#]+)(:([0-9]+))?))?(/([^?#]*))(\?([^#]*))?(#(.*))?'
#                    ↑↑            ↑  ↑↑↑            ↑         ↑ ↑            ↑ ↑        ↑  ↑        ↑ ↑
#                    |2 scheme     |  ||6 userinfo   7 host    | 9 port       | 11 rpath |  13 query | 15 fragment
#                    1 scheme:     |  |5 userinfo@             8 :…           10 path    12 ?…       14 #…
#                                  |  4 authority
#                                  3 //…

parse_scheme () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[2]}"
}

parse_authority () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[4]}"
}

parse_user () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[6]}"
}

parse_host () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[7]}"
}

parse_port () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[9]}"
}

parse_path () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[10]}"
}

parse_rpath () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[11]}"
}

parse_query () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[13]}"
}

parse_fragment () {
    # shellcheck disable=SC2199
    [[ "$@" =~ $URI_REGEX ]] && echo "${BASH_REMATCH[15]}"
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
