/**
 * Tests for tools/check-connections.mjs against local stand-ins for Google's
 * and Meta's APIs (nothing real is called):
 *   node tools/test-check-connections.mjs
 */
import http from 'node:http';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const SCRIPT = fileURLToPath(new URL('./check-connections.mjs', import.meta.url));
let pass = 0, fail = 0;
const t = (name, cond, extra) => { cond ? pass++ : fail++; console.log(`${cond ? 'PASS' : 'FAIL'}  ${name}${!cond && extra !== undefined ? '\n      ' + String(extra).slice(0, 700) : ''}`); };

const KEY = 'AIzaTestKey1234567890', CID = 'client-id-123.apps', CSECRET = 'GOCSPX-test-secret-xyz', REFRESH = '1//refresh-token-abc', META_TOKEN = 'EAATESTTOKEN987654';

function server(S) {
  return http.createServer((req, res) => {
    let body = ''; req.on('data', d => body += d); req.on('end', () => {
      const u = new URL(req.url, 'http://x');
      const send = (st, j) => { res.writeHead(st, { 'Content-Type': 'application/json' }); res.end(JSON.stringify(j)); };
      const auth = req.headers.authorization || '';
      // Google sign-in
      if (u.pathname === '/token') {
        const f = new URLSearchParams(body);
        if (S.oauth === 'revoked') return send(400, { error: 'invalid_grant', error_description: 'Token has been expired or revoked.' });
        if (S.oauth === 'echo') return send(401, { error: 'invalid_client', error_description: 'bad client ' + f.get('client_secret') });
        if (f.get('refresh_token') !== REFRESH || f.get('client_secret') !== CSECRET) return send(400, { error: 'invalid_grant' });
        return send(200, { access_token: 'ya29.access', expires_in: 3599, scope: S.scopes.map(s => 'https://www.googleapis.com/auth/' + s).join(' ') });
      }
      if (u.pathname === '/youtube/v3/channels') {
        if (u.searchParams.get('mine') === 'true') return auth === 'Bearer ya29.access' ? send(200, { items: S.myChannels }) : send(401, { error: { message: 'Login Required' } });
        // a keyless call succeeds only when the "proxy" would add the key (S.keylessOk)
        const keyOk = u.searchParams.get('key') === KEY || (!u.searchParams.get('key') && S.keylessOk);
        if (!keyOk) return send(403, { error: { message: 'Method doesn\'t allow unregistered callers' } });
        return send(200, { items: [{ id: 'UC_GX4vXRQrN4GsvSfgmZxYw', snippet: { title: 'The Art Framer' }, statistics: { subscriberCount: '120', videoCount: '45' } }] });
      }
      if (u.pathname === '/webmasters/v3/sites') return send(200, { siteEntry: S.sites });
      if (u.pathname === '/accountSummaries') return send(200, { accountSummaries: [{ propertySummaries: [{ displayName: 'theartframer.us - GA4' }] }] });
      if (u.pathname === '/accounts') return send(403, { error: { message: 'Requests to this API mybusinessaccountmanagement method are blocked.' } });
      // Meta
      if (u.pathname === '/') return send(400, { error: { message: 'Unsupported get request.' } });
      const tokOk = u.searchParams.get('access_token') === META_TOKEN && !S.metaExpired;
      if (!tokOk) return send(400, { error: { message: 'Error validating access token: Session has expired', code: 190 } });
      if (u.pathname === '/me') return send(200, { id: '1', name: 'The ArtFramer system user' });
      if (u.pathname === '/111') return send(200, { name: 'The ArtFramer', followers_count: 900, instagram_business_account: { id: '222' } });
      if (u.pathname === '/222') return send(200, { username: 'theartframer136', followers_count: 1500, media_count: 300 });
      send(404, { error: { message: 'no such route ' + u.pathname } });
    });
  });
}

async function run(S, env) {
  const srv = server(S); await new Promise(r => srv.listen(0, '127.0.0.1', r));
  const base = `http://127.0.0.1:${srv.address().port}`;
  const out = await new Promise((resolve) => {
    const c = spawn(process.execPath, [SCRIPT], { env: { PATH: process.env.PATH, CC_GOOGLE: base, CC_YT_KEYLESS: base, CC_OAUTH: base + '/token', CC_GA: base, CC_GBP: base,
      CC_META: S.metaBlocked ? 'http://127.0.0.1:9' : base, ...env } });
    let o = ''; c.stdout.on('data', d => o += d); c.stderr.on('data', d => o += d); c.on('close', () => resolve(o));
  });
  srv.close();
  return out;
}
const line = (out, service) => (out.split('\n').find(l => l.startsWith(service)) || '');
const noSecrets = (out) => ![KEY, CSECRET, REFRESH, META_TOKEN].some(s => out.includes(s));
const base = { scopes: [], myChannels: [], sites: [], keyless: true, keylessOk: false };

// 1. nothing set, Facebook blocked (as in the session that wrote this)
let o = await run({ ...base, metaBlocked: true }, {});
t('nothing set: YouTube key reported not set', /YouTube \(API key\)\s+not set/.test(o), o);
t('nothing set: Google sign-in not set', /Google sign-in\s+not set/.test(o), o);
t('nothing set: Meta not set, and the block is named', /Facebook \/ Instagram \(API\)\s+not set.*allow graph\.facebook\.com/.test(o), o);
t('nothing set: connectors pointed to the session', /Connectors \(MCP\)/.test(o));

// 2. everything set and valid
const ALL = { YOUTUBE_API_KEY: KEY, YT_CLIENT_ID: CID, YT_CLIENT_SECRET: CSECRET, YT_REFRESH_TOKEN: REFRESH, META_ACCESS_TOKEN: META_TOKEN, META_PAGE_ID: '111', IG_BUSINESS_ID: '222' };
o = await run({ ...base, scopes: ['youtube', 'yt-analytics.readonly', 'webmasters', 'analytics.readonly', 'business.manage'],
  myChannels: [{ id: 'UC_GX4vXRQrN4GsvSfgmZxYw', snippet: { title: 'The Art Framer' } }],
  sites: [{ siteUrl: 'sc-domain:theartframer.us', permissionLevel: 'siteOwner' }, { siteUrl: 'https://other.example/', permissionLevel: 'siteFullUser' }] }, ALL);
t('all: YouTube key connected with channel stats', /YouTube \(API key\)\s+connected\s+environment variable; channel "The Art Framer": 120 subscribers, 45 videos/.test(o), o);
t('all: scopes listed', /Google sign-in\s+connected\s+scopes granted: youtube, yt-analytics\.readonly, webmasters, analytics\.readonly, business\.manage/.test(o), o);
t('all: signed-in YouTube is the site channel', /YouTube \(signed in\)\s+connected\s+"The Art Framer" \(the site channel\)/.test(o), o);
t('all: Search Console sees the site as owner', /Search Console\s+connected\s+sc-domain:theartframer\.us \(siteOwner\)/.test(o), o);
t('all: Analytics property', /Google Analytics\s+connected/.test(o), o);
t('all: Business Profile waiting on Google', /Business Profile\s+waiting on Google/.test(o), o);
t('all: Facebook token, Page and Instagram', /Facebook \(token\)\s+connected/.test(o) && /Facebook Page\s+connected\s+"The ArtFramer", 900 followers/.test(o) && /Instagram\s+connected\s+@theartframer136/.test(o), o);
t('all: no secret value printed', noSecrets(o), o);
t('all: 8 connected', /=== 8 connected/.test(o), line(o, '==='));

// 3. YouTube key as a Network secret: no variable here, the keyless call works
o = await run({ ...base, keylessOk: true }, {});
t('network secret: YouTube connected without a variable', /YouTube \(API key\)\s+connected\s+network secret/.test(o), o);

// 4. revoked refresh token, and a secret echoed back in an error
o = await run({ ...base, oauth: 'revoked' }, { YT_CLIENT_ID: CID, YT_CLIENT_SECRET: CSECRET, YT_REFRESH_TOKEN: REFRESH });
t('revoked: says to run the OAuth Playground step again', /Google sign-in\s+failed.*OAuth Playground/.test(o), o);
o = await run({ ...base, oauth: 'echo' }, { YT_CLIENT_ID: CID, YT_CLIENT_SECRET: CSECRET, YT_REFRESH_TOKEN: REFRESH });
t('echoed secret is masked', o.includes('[hidden]') && noSecrets(o), o);

// 5. partial Google setup and missing scopes
o = await run({ ...base }, { YT_CLIENT_ID: CID });
t('partial: names the missing variables', /Google sign-in\s+not set\s+missing YT_CLIENT_SECRET, YT_REFRESH_TOKEN/.test(o), o);
o = await run({ ...base, scopes: ['youtube'], myChannels: [] }, { YT_CLIENT_ID: CID, YT_CLIENT_SECRET: CSECRET, YT_REFRESH_TOKEN: REFRESH });
t('no channel: explains the Brand Account manager step', /YouTube \(signed in\)\s+no channel.*Brand Account/.test(o), o);
t('scope not granted: Search Console says so', /Search Console\s+not granted/.test(o), o);

// 6. expired Meta token; and a token with Facebook blocked
o = await run({ ...base, metaExpired: true }, { META_ACCESS_TOKEN: META_TOKEN });
t('expired Meta token: says make a new one', /Facebook \(token\)\s+failed.*make a new one/.test(o) && noSecrets(o), o);
o = await run({ ...base, metaBlocked: true }, { META_ACCESS_TOKEN: META_TOKEN });
t('Meta token but host blocked: says allow it', /Facebook \/ Instagram \(API\)\s+blocked.*allow it/.test(o), o);

console.log(`\n${pass} passed, ${fail} failed`);
process.exit(fail ? 1 : 0);
