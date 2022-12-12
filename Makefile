build:
	composer install
	docker build containers/tests-php --tag slic-test-php
.PHONY: build

clean:
	$(if $(shell docker ps -a -q -f name=slic-test-php), docker rm -f slic-test-php)
	docker rmi slic-test-php

test:
	docker run --name slic-test-php --rm -v $(PWD):$(PWD) -w $(PWD) slic-test-php:latest sh -c "vendor/bin/phpunit"
.PHONY: test
