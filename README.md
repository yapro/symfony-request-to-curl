# symfony-request-to-curl-converter
Converts the current / specified Symfony http request to a curl command.

Tests
------------
```sh
docker build -t yapro/symfony-request-to-curl-converter:latest -f ./Dockerfile ./
docker run --rm -v $(pwd):/app yapro/symfony-request-to-curl-converter:latest bash -c "cd /app \
  && composer install --optimize-autoloader --no-scripts --no-interaction \
  && /app/vendor/bin/phpunit /app/tests"
```

Dev
------------
```sh
docker build -t yapro/symfony-request-to-curl-converter:latest -f ./Dockerfile ./
docker run -it --rm -v $(pwd):/app -w /app yapro/symfony-request-to-curl-converter:latest bash
composer install -o
```
