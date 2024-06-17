# Google Podcasts to AntennaPod Migration

As Google announced the [discontinuation of Google Podcasts](https://www.theverge.com/2024/4/2/24118873/google-podcasts-shutdown-graveyard), I needed a new app that could import my podcast listening history.

After evaluating various options, I chose an open-source, self-hosted solution combining [AntennaPod](https://antennapod.org), an Android app with a similar interface to Google Podcasts, and [oPodSync](https://github.com/kd2org/opodsync), a server for synchronizing podcast subscriptions and listening history.

In this repository, I document the process of migrating my podcast listening history from Google Podcasts to AntennaPod using the oPodSync synchronization server.

The migration process involved parsing the HTML from Google Podcasts' web interface to obtain my listening history due to limitations with Google Takeout.

This transition, though challenging, provided a cost-effective alternative with full control over my data, allowing regular backups and ensuring the integrity of my podcast listening history.

## Contents

- [google_podcasts_html](google_podcasts_html): HTML files from the Google Podcasts web interface of my subscriptions details.
- [opodsync-api-tests](opodsync-api-tests): Tests for the oPodSync API.
- [env-sample.sh](env-sample.sh): Environment variables for tests and migration.
- [migrate_to_antennapod.php](migrate_to_antennapod.php): Parses the HTML from the Google Podcasts web interface and marks episodes as listened in oPodSync.
- [migrate-all.sh](migrate-all.sh): Process all my subscriptions.
