#!/usr/bin/env bash

if [[ "x" == "x$LOGENTRIES_KEY" ]]; then
    echo "Missing LOGENTRIES_KEY environment variable";
else
    # Set logentries key based on environment variable
    sed -i /etc/rsyslog.conf -e "s/LOGENTRIESKEY/${LOGENTRIES_KEY}/"
    # Start syslog
    rsyslogd
fi

# fix script permissions
chmod a+x /data/vendor/cpliakas/git-wrapper/bin/git-ssh-wrapper.sh

# fix folder permissions
chown -R www-data:www-data \
  /data/console/runtime/ \
  /data/frontend/assets/ \
  /data/frontend/runtime/ \
  /data/frontend/web/assets/

# make sure rsyslog can read logentries cert
chmod a+r /opt/ssl/logentries.all.crt

# Dump env to a file
touch /etc/cron.d/appbuilder
env | while read line ; do
   if [[ "${line: -1}" != "=" ]] 
   then
     echo "$line" >> /etc/cron.d/appbuilder
   fi
done

# Add env vars to doorman-cron to make available to scripts
cat /etc/cron.d/appbuilder-cron >> /etc/cron.d/appbuilder

# Remove original cron file without env vars
rm -f /etc/cron.d/appbuilder-cron

# Start cron daemon
cron -f
