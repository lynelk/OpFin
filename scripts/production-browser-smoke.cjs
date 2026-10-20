const { chromium } = require("playwright");
const assert = require("node:assert/strict");

(async () => {
  const origin = process.env.OPFIN_PRODUCTION_WEB_URL || "https://opfin-web-production.up.railway.app";
  if (!origin.startsWith("https://")) throw new Error("Production browser checks require HTTPS.");
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ ignoreHTTPSErrors: false });
    const page = await context.newPage();
    const results = [];
    for (const path of ["/", "/login"]) {
      const response = await page.goto(origin + path, { waitUntil: "domcontentloaded", timeout: 30000 });
      assert(response && response.status() === 200, `${path} should load with 200`);
      assert((await page.locator("body").innerText()).includes("OpFin"), `${path} must identify OpFin`);
      results.push({ path, status: response.status(), title: await page.title() });
    }
    for (const path of ["/dashboard", "/setup/organisation"]) {
      await page.goto(origin + path, { waitUntil: "domcontentloaded", timeout: 30000 });
      const finalUrl = new URL(page.url());
      assert(finalUrl.pathname.startsWith("/login"), `${path} must redirect unauthenticated users to login; got ${finalUrl.pathname}`);
      results.push({ path, redirectedTo: finalUrl.pathname });
    }
    await page.screenshot({ path: "opfin-production-login-smoke.png", fullPage: true });
    console.log(JSON.stringify({ result: "PASS", checks: results }, null, 2));
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
