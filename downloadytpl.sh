#!/usr/bin/env bash
echo $$
YTDL="/usr/local/Cellar/youtube-dl/2015.12.23/bin/youtube-dl"
DIR=$1
PLAYLIST=$2
LOGFILE=$3
OUTPUT="$DIR/%(id)s.%(ext)s"
$YTDL  --write-info-json --yes-playlist -f mp4 -o $OUTPUT $PLAYLIST &>$LOGFILE
exit 0