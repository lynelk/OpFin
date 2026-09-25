// Test the compiled presentation helper with Node only. This does not run Next.js or browser tests.
import assert from 'node:assert/strict';
import { createRequire } from 'node:module';
import { resolve } from 'node:path';
if (!process.argv[2]) {
  console.error('Usage: node scripts/test-financial-intelligence-web.mjs /absolute/path/to/compiled/presentation.js');
  process.exit(2);
}
const require = createRequire(import.meta.url);
const { displayMetric, positiveId, integerInput, selectedTab, destination } = require(resolve(process.argv[2]));
let assertions = 0;
const equal = (actual, expected) => { assertions++; assert.equal(actual, expected); };
const rejects = fn => { assertions++; assert.throws(fn); };
const tests = {
  'unknown is distinct from zero': () => {
    equal(displayMetric('par30_bps', null), 'Not available');
    equal(displayMetric('par30_bps', 0), '0%');
    equal(displayMetric('principal_minor', 0), '0');
  },
  'unsafe integers are not displayed as reliable amounts': () => equal(displayMetric('principal_minor', Number.MAX_SAFE_INTEGER + 1), 'Not available'),
  'basis points presentation': () => equal(displayMetric('par30_bps', 1234), '12.34%'),
  'strict path-bound record identifiers': () => {
    for (const x of ['0', '-1', '1.2', '1e3', '01', '1/../../2', '999999999999999999', undefined]) rejects(() => positiveId(x));
    equal(positiveId('123'), 123);
  },
  'exact bounded input amounts': () => {
    for (const x of ['1,000', '1.5', '1e3', '', '01', '900000000000001']) rejects(() => integerInput(x));
    equal(integerInput('900000000000000'), 900000000000000);
    equal(integerInput('-12', -100), -12);
  },
  'collections navigation does not imply overview rights': () => {
    equal(selectedTab(undefined, ['case']), 'cases'); equal(selectedTab('overview', ['case']), 'cases');
  },
  'personal owner navigation remains statement-only': () => equal(selectedTab('imports', ['statement']), 'statements'),
  'board navigation excludes borrower detail': () => equal(selectedTab('imports', ['overview', 'report']), 'overview'),
  'destinations remain inside the selected Space': () => {
    equal(destination(7, 'cases', { case: 12 }), '/spaces/7/intelligence?tab=cases&case=12');
    rejects(() => destination(-1, 'overview'));
  },
};
let failed = 0;
for (const [name, test] of Object.entries(tests)) {
  try { test(); console.log(`PASS ${name}`); }
  catch (error) { failed++; console.error(`FAIL ${name}: ${error.message}`); }
}
console.log(JSON.stringify({ suite: 'standalone-web-presentation', tests: Object.keys(tests).length, assertions, failed, node: process.version }));
process.exit(failed ? 1 : 0);
