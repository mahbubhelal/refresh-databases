.PHONY: testing down

testing:
	@docker compose up -d test-mysql

down:
	@docker compose down
