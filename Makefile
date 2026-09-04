.PHONY: test api-test web-test client-test layout

test: layout api-test web-test client-test

layout:
	sh scripts/verify-layout.sh

api-test:
	sh scripts/test-api.sh

web-test:
	sh scripts/test-web.sh

client-test:
	sh scripts/test-client.sh
