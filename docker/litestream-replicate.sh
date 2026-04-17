#!/bin/sh
# If no replica URL is configured, sleep forever (supervisord keeps this slot alive but idle).
if [ -z "$LITESTREAM_REPLICA_URL" ]; then
    exec sleep infinity
fi
exec litestream replicate /var/www/html/database/database.sqlite "$LITESTREAM_REPLICA_URL"
