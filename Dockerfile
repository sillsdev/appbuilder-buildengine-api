FROM silintl/php-web:latest
MAINTAINER Phillip Shipley <phillip_shipley@sil.org>

ENV REFRESHED_AT 2015-05-26

COPY build/appbuilder.conf /etc/apache2/sites-enabled/

# Copy in cron configuration
COPY build/appbuilder-cron /etc/cron.d/
RUN chmod 0644 /etc/cron.d/appbuilder-cron

RUN mkdir -p /data

RUN curl https://raw.githubusercontent.com/silinternational/s3-expand/master/s3-expand > /usr/local/bin/s3-expand
RUN chmod a+x /usr/local/bin/s3-expand

# Copy in syslog config
RUN rm -f /etc/rsyslog.d/*
COPY build/rsyslog.conf /etc/rsyslog.conf
RUN mkdir -p /opt/ssl
COPY build/logentries.all.crt /opt/ssl/logentries.all.crt

# Copy in updated php.ini
COPY build/php.ini /etc/php5/apache2/
COPY build/php.ini /etc/php5/cli/

# Copy logrotate file to manage logs
COPY build/sab /etc/logrotate.d
RUN chmod 0644 /etc/logrotate.d/sab

# It is expected that /data is = application/ in project folder
COPY application/ /data/

WORKDIR /data

# Fix folder permissions
RUN chown -R www-data:www-data \
    console/runtime/ \
    frontend/runtime/ \
    frontend/web/assets/

# Install/cleanup composer dependencies
RUN composer global require "fxp/composer-asset-plugin:^1.1.4" \
    && composer install --prefer-dist --no-interaction --no-dev --optimize-autoloader

EXPOSE 80
ENTRYPOINT ["s3-expand"]
CMD ["/data/run.sh"]
