FROM au9500/php:8.5

RUN apt-get update && apt-get install -y libgmp-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install bcmath gmp

WORKDIR /var/www/html

CMD ["/usr/local/bin/container-start.sh"]
