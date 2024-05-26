curl -u $OPODSYNC_USER:$OPODSYNC_PASS -X POST \
     --data '{"add": ["https://feeds.megaphone.fm/darknetdiaries"] }' \
     $OPODSYNC_URL/api/2/subscriptions/manuel/data_from_google_podcasts.json
