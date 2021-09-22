FROM php:8.0-cli-alpine as composer
RUN apk --no-cache add wget
ARG COMPOSER_COMMIT=b2ffe854401d063de8a3bf6b0811884243a381ba
RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/${COMPOSER_COMMIT}/web/installer -O - -q \
  | php -- --install-dir=/tmp

FROM php:8.0-cli-alpine
RUN apk add --no-cache --update --virtual buildDeps \
    autoconf \
    gcc \
    libc-dev \
    make \
 && pecl install xdebug \
 && docker-php-ext-enable xdebug \
 && apk del buildDeps

COPY --from=composer /tmp/composer.phar /usr/local/bin/composer
RUN chmod a+x /usr/local/bin/composer
