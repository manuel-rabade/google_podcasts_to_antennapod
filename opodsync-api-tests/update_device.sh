curl -u $OPODSYNC_USER:$OPODSYNC_PASS -X POST --data @device.json \
     $OPODSYNC_URL/api/2/devices/manuel/data_from_google_podcasts.json
