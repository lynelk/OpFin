.PHONY: help test api-test web-test client-test layout docs-check publication-check docs-search api-search api-docs api-docs-check agent-docs-test

help:
	@printf '%s\n' \
	  'OpFin developer commands:' \
	  '  make test                         Run layout + API + web + client test suites' \
	  '  make api-test                     Run API tests' \
	  '  make web-test                     Run web tests/build checks' \
	  '  make client-test                  Run Flutter checks' \
	  '  make docs-check                   Verify current documentation drift rules' \
	  '  make publication-check            Verify publication-facing documentation' \
	  '  make docs-search QUERY="receipt" Search current repository documentation' \
	  '  make api-search QUERY="umra"      Search registered Laravel API routes' \
	  '  make api-docs                     Export current catalogue, reviewed OpenAPI and guides' \
	  '  make api-docs-check               Check reviewed definitions; report coverage gaps' \
	  '  make agent-docs-test              Test the documentation-only MCP bridge'

test: layout api-test web-test client-test

layout:
	sh scripts/verify-layout.sh

api-test:
	sh scripts/test-api.sh

web-test:
	sh scripts/test-web.sh

client-test:
	sh scripts/test-client.sh

docs-check:
	python3 scripts/verify-documentation-drift.py
	python3 scripts/verify-publication-readiness.py

publication-check:
	python3 scripts/verify-publication-readiness.py

docs-search:
	@test -n "$(QUERY)" || (echo 'Set QUERY, e.g. make docs-search QUERY="credit reporting"' && exit 2)
	python3 scripts/search-docs.py "$(QUERY)"

api-search:
	@test -n "$(QUERY)" || (echo 'Set QUERY, e.g. make api-search QUERY="umra"' && exit 2)
	python3 scripts/search-api.py "$(QUERY)"

api-docs:
	cd apps/api && php artisan api:catalogue

api-docs-check:
	cd apps/api && php artisan api:catalogue --check

agent-docs-test:
	python3 -m unittest discover -s tools/opfin-mcp -p 'test_*.py' -v
	php tools/opfin-mcp/test_catalogue.php
