FROM silintl/php7:7.2
MAINTAINER Phillip Shipley <phillip_shipley@sil.org>

ENV REFRESHED_AT 2022-03-11

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

# Install composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Install/cleanup composer dependencies
RUN composer global remove "fxp/composer-asset-plugin" \
    && composer install --prefer-dist --no-interaction --no-dev --optimize-autoloader

# Install shellcheck for validating shell scripts
RUN apt-get update && apt-get install -y \
    shellcheck \
&& rm -rf /var/lib/apt/lists/*

EXPOSE 80
ENTRYPOINT ["s3-expand"]
CMD ["/data/run.sh"]
