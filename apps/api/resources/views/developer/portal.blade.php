<!doctype html>
<html lang="en-GB">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>OpFin Developer Centre</title>
<link rel="stylesheet" href="/developer-assets/portal.css">
<script src="/developer-assets/portal.js" defer></script>
</head>
<body>
<header><p class="eyebrow">OPFIN · DEVELOPER CENTRE</p><h1>Build with a clear contract.</h1>
<p>Search the API, learn the financial rules and connect an AI without handing it unchecked authority.</p></header>
<main>
<section class="controls" aria-label="API search and access">
<label for="query">Search a task, endpoint or guide</label>
<input id="query" type="search" placeholder="Try treasury, repayment, identity or agents" maxlength="160" autocomplete="off">
<div class="filters"><label>Method<select id="method"><option value="">All methods</option><option>GET</option><option>POST</option><option>PUT</option><option>PATCH</option><option>DELETE</option></select></label>
<button id="search" type="button">Search</button></div>
<details><summary>Use authorised developer access</summary><p>The token stays in this page's memory and is sent only to this origin's documentation API. It is not stored in your browser.</p>
<label for="token">Your approved developer token</label><input id="token" type="password" autocomplete="off" spellcheck="false">
<button id="authorise" type="button">Load my visible catalogue</button><button id="clear" type="button">Clear token</button>
<button id="export" type="button">Export reviewed OpenAPI</button></details>
<p id="status" role="status" aria-live="polite">Loading current documentation…</p>
<nav id="guides" aria-label="Learning tracks"></nav>
<nav id="operations" aria-label="API results"></nav>
<div class="filters"><button id="previous" type="button">Previous</button><button id="next" type="button">Next</button></div>
</section>
<article id="reader" tabindex="-1"><h2>Choose your learning track</h2><p>Begin with your first request, then explore contracts, financial reliability, AI integration, sandbox testing and maintenance.</p>
<p><strong>Source discovery is not launch approval.</strong> Registration-only operations are visible as gaps, not invented schemas. The AI bridge retrieves documentation only and cannot move money or change financial records.</p></article>
</main>
<footer><p id="provenance"></p><p>No external scripts, analytics, model calls or provider transactions are used by this centre.</p></footer>
</body></html>
