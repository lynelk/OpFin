.PHONY: help test api-test web-test client-test layout docs-check docs-test docs-build docs-serve docs-search api-search

help:
	@printf '%s\n' \
	  'OpFin developer commands:' \
	  '  make test                          Layout + API + web + client tests' \
	  '  make api-test | web-test | client-test' \
	  '  make docs-test                     Documentation tooling regression tests' \
	  '  make docs-check BASE=origin/main   Content, link and change-impact checks' \
	  '  make docs-build                    Build offline searchable docs' \
	  '  make docs-serve                    Serve generated docs on 127.0.0.1:8008' \
	  '  make docs-search QUERY="receipt"   Search current tracked Markdown' \
	  '  make api-search QUERY="umra"       Search registered API routes'

test: layout api-test web-test client-test

layout:
	sh scripts/verify-layout.sh

api-test:
	sh scripts/test-api.sh

web-test:
	sh scripts/test-web.sh

client-test:
	sh scripts/test-client.sh

docs-test:
	python3 -m unittest discover -s scripts -p 'test_docs_toolkit.py' -v

docs-check:
	python3 scripts/verify-documentation-drift.py $(if $(BASE),--base "$(BASE)",)
	python3 scripts/docs_toolkit.py check $(if $(BASE),--base "$(BASE)",)

docs-build:
	python3 scripts/docs_toolkit.py build

docs-serve: docs-build
	python3 -m http.server 8008 --bind 127.0.0.1 --directory .build/docs

docs-search:
	@test -n "$(QUERY)" || (echo 'Set QUERY, e.g. make docs-search QUERY="credit reporting"' && exit 2)
	python3 scripts/search-docs.py "$(QUERY)"

api-search:
	@test -n "$(QUERY)" || (echo 'Set QUERY, e.g. make api-search QUERY="umra"' && exit 2)
	python3 scripts/search-api.py "$(QUERY)"
