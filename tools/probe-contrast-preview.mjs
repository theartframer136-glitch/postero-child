// probe-contrast.mjs in preview mode, for the qa-personas workflow, which runs
// a script by name and passes it nothing else: this checkout's CSS measured on
// top of the live pages, in the test browser only, before it is deployed.
//
// Run: node tools/probe-contrast-preview.mjs [url]

process.env.AF_PREVIEW = '1';
await import('./probe-contrast.mjs');
