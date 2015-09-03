DATETIME_STAMP=$(date +%s)
curl -i \
	-H "Accept: application/json" \
    -H "Content-Type: application/json" \
    -X POST -d "{\"request_id\": \"$DATETIME_STAMP\", \"git_url\": \"ssh://github.com/chrisvire/org.wycliffe.app.cuk.bible\", \"app_id\":\"scriptureappbuilder\"}" http://192.168.70.121/job
