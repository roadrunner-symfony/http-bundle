fix-code-style:
	@vendor/bin/php-cs-fixer fix --allow-risky=yes --verbose --using-cache=no

lint-code-style:
	@vendor/bin/php-cs-fixer fix --allow-risky=yes --dry-run --stop-on-violation --diff --using-cache=no

analysis-code:
	@php -d memory_limit=-1 vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=-1


get-rr:
	./vendor/bin/rr get-binary  --no-config --no-interaction --no-ansi

start-rr:
	rm -rf var && ./rr serve -c tests/App/.rr.http.yaml

testing:
	@./rr serve -c tests/App/.rr.http.yaml 2> rr.log & \
	pid=$$!; \
	until nc -z localhost 8080; do sleep 0.1; done; \
	vendor/bin/codecept run; \
	kill -9 $$pid; \
	wait $$pid || true




