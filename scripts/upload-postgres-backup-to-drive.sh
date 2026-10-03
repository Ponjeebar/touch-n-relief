#!/usr/bin/env bash

set -Eeuo pipefail

required_variables=(
    HEROKU_APP_NAME
    HEROKU_API_KEY
    GOOGLE_DRIVE_BACKUP_CLIENT_ID
    GOOGLE_DRIVE_BACKUP_CLIENT_SECRET
    GOOGLE_DRIVE_BACKUP_REFRESH_TOKEN
)

for variable_name in "${required_variables[@]}"; do
    if [[ -z "${!variable_name:-}" ]]; then
        echo "Required secret is missing: ${variable_name}" >&2
        exit 1
    fi
done

retention_days="${GOOGLE_DRIVE_BACKUP_RETENTION_DAYS:-14}"
if ! [[ "$retention_days" =~ ^[1-9][0-9]*$ ]]; then
    echo 'GOOGLE_DRIVE_BACKUP_RETENTION_DAYS must be a positive integer.' >&2
    exit 1
fi

working_directory="${RUNNER_TEMP:-/tmp}/touchnrelief-postgres-backup"
mkdir -p "$working_directory"
backup_date="$(TZ=Asia/Manila date '+%Y-%m-%d')"
backup_filename="touchnrelief-postgresql-${backup_date}.dump"
backup_path="${working_directory}/${backup_filename}"
response_headers="${working_directory}/upload-headers.txt"
response_body="${working_directory}/upload-response.json"
file_list="${working_directory}/drive-files.json"

cleanup() {
    rm -f "$backup_path" "$response_headers" "$response_body" "$file_list"
}
trap cleanup EXIT

echo 'Downloading the latest completed Heroku PGBackup.'
heroku pg:backups:download --app "$HEROKU_APP_NAME" --output "$backup_path"
if [[ ! -s "$backup_path" ]]; then
    echo 'Heroku returned an empty PostgreSQL backup.' >&2
    exit 1
fi

access_token="$({
    curl --silent --show-error --fail-with-body \
        --request POST 'https://oauth2.googleapis.com/token' \
        --data-urlencode "client_id=${GOOGLE_DRIVE_BACKUP_CLIENT_ID}" \
        --data-urlencode "client_secret=${GOOGLE_DRIVE_BACKUP_CLIENT_SECRET}" \
        --data-urlencode "refresh_token=${GOOGLE_DRIVE_BACKUP_REFRESH_TOKEN}" \
        --data-urlencode 'grant_type=refresh_token'
} | jq --raw-output --exit-status '.access_token')"

folder_id="${GOOGLE_DRIVE_BACKUP_FOLDER_ID:-}"
if [[ -n "$folder_id" ]]; then
    if ! curl --silent --show-error --fail \
        --header "Authorization: Bearer ${access_token}" \
        --get 'https://www.googleapis.com/drive/v3/files/'"${folder_id}" \
        --data-urlencode 'fields=id' \
        --output /dev/null; then
        echo 'Configured Drive folder is not available to this OAuth authorization; using the app-managed folder.'
        folder_id=''
    fi
fi

if [[ -z "$folder_id" ]]; then
    folder_query="mimeType = 'application/vnd.google-apps.folder' and trashed = false and appProperties has { key='touchnrelief_backup_folder' and value='true' }"
    folder_id="$({
        curl --silent --show-error --fail-with-body \
            --header "Authorization: Bearer ${access_token}" \
            --get 'https://www.googleapis.com/drive/v3/files' \
            --data-urlencode "q=${folder_query}" \
            --data-urlencode 'fields=files(id)' \
            --data-urlencode 'pageSize=1'
    } | jq --raw-output '.files[0].id // empty')"
fi

if [[ -z "$folder_id" ]]; then
    folder_metadata="$(jq --null-input --compact-output '{
        name: "TouchNRelief Backups",
        mimeType: "application/vnd.google-apps.folder",
        appProperties: {touchnrelief_backup_folder: "true"}
    }')"
    folder_id="$({
        curl --silent --show-error --fail-with-body \
            --request POST 'https://www.googleapis.com/drive/v3/files?fields=id' \
            --header "Authorization: Bearer ${access_token}" \
            --header 'Content-Type: application/json; charset=UTF-8' \
            --data "$folder_metadata"
    } | jq --raw-output --exit-status '.id')"
fi

existing_query="'${folder_id}' in parents and trashed = false and name = '${backup_filename}' and appProperties has { key='touchnrelief_postgresql_backup' and value='true' }"
existing_file_id="$({
    curl --silent --show-error --fail-with-body \
        --header "Authorization: Bearer ${access_token}" \
        --get 'https://www.googleapis.com/drive/v3/files' \
        --data-urlencode "q=${existing_query}" \
        --data-urlencode 'fields=files(id)' \
        --data-urlencode 'pageSize=1'
} | jq --raw-output '.files[0].id // empty')"

if [[ -n "$existing_file_id" ]]; then
    echo "PostgreSQL backup already exists in Drive: ${backup_filename}"
else
    metadata="$(jq --null-input --compact-output \
        --arg name "$backup_filename" \
        --arg folder "$folder_id" \
        '{
            name: $name,
            parents: [$folder],
            appProperties: {touchnrelief_postgresql_backup: "true"}
        }')"

    curl --silent --show-error --fail-with-body \
        --request POST 'https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id,name,size,createdTime' \
        --header "Authorization: Bearer ${access_token}" \
        --header 'Content-Type: application/json; charset=UTF-8' \
        --header 'X-Upload-Content-Type: application/octet-stream' \
        --header "X-Upload-Content-Length: $(wc -c < "$backup_path")" \
        --data "$metadata" \
        --dump-header "$response_headers" \
        --output /dev/null

    upload_url="$(awk 'BEGIN {IGNORECASE=1} /^location:/ {sub(/^[^:]+:[[:space:]]*/, ""); sub(/\r$/, ""); print; exit}' "$response_headers")"
    if [[ -z "$upload_url" ]]; then
        echo 'Google Drive did not provide a resumable upload URL.' >&2
        exit 1
    fi

    curl --silent --show-error --fail-with-body \
        --request PUT "$upload_url" \
        --header 'Content-Type: application/octet-stream' \
        --upload-file "$backup_path" \
        --output "$response_body"

    uploaded_name="$(jq --raw-output --exit-status '.name' "$response_body")"
    echo "Uploaded PostgreSQL backup: ${uploaded_name}"
fi

backup_query="'${folder_id}' in parents and trashed = false and appProperties has { key='touchnrelief_postgresql_backup' and value='true' }"
curl --silent --show-error --fail-with-body \
    --header "Authorization: Bearer ${access_token}" \
    --get 'https://www.googleapis.com/drive/v3/files' \
    --data-urlencode "q=${backup_query}" \
    --data-urlencode 'fields=files(id,createdTime)' \
    --data-urlencode 'pageSize=1000' \
    --output "$file_list"

cutoff="$(date --utc --date="${retention_days} days ago" '+%Y-%m-%dT%H:%M:%SZ')"
while IFS= read -r expired_file_id; do
    [[ -z "$expired_file_id" ]] && continue
    curl --silent --show-error --fail-with-body \
        --request DELETE \
        --header "Authorization: Bearer ${access_token}" \
        "https://www.googleapis.com/drive/v3/files/${expired_file_id}" \
        --output /dev/null
    echo "Removed expired PostgreSQL backup ${expired_file_id}."
done < <(jq --raw-output --arg cutoff "$cutoff" '.files[] | select(.createdTime < $cutoff) | .id' "$file_list")
