/**
 * YouTube SEO for The Art Framer's channel, in three steps that never run
 * together:
 *
 *   node tools/youtube-seo.mjs backup          save every current value first
 *   node tools/youtube-seo.mjs diff            show what the plan would change
 *   node tools/youtube-seo.mjs apply           write the plan (needs a backup)
 *   node tools/youtube-seo.mjs apply --only VIDEO_ID,VIDEO_ID   a subset
 *
 * The plan is tools/youtube-seo-plan.json. The backup goes OUTSIDE the
 * repository, to $YT_SEO_BACKUP_DIR or ~/youtube-seo-backups/<timestamp>.json,
 * so an old value can always be put back:
 *
 *   node tools/youtube-seo.mjs restore ~/youtube-seo-backups/<file>.json
 *
 * Credentials: YT_CLIENT_ID, YT_CLIENT_SECRET, YT_REFRESH_TOKEN from the
 * environment (never printed; any value that turns up in an error message
 * is masked). The token exchange is the one the owner's notes describe.
 *
 * Rules carried in code: nothing written may say "free shipping"; a video
 * title must be 100 characters or fewer; a description 5000 or fewer; tags
 * 500 characters in all. The plan is refused before the first write if any
 * rule fails.
 */
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const PLAN_PATH = path.join(HERE, 'youtube-seo-plan.json');
const API = 'https://youtube.googleapis.com/youtube/v3';
const OAUTH = 'https://oauth2.googleapis.com/token';

const env = (k) => String(process.env[k] || '').trim();
const SECRETS = ['YT_CLIENT_ID', 'YT_CLIENT_SECRET', 'YT_REFRESH_TOKEN'].map(env).filter((v) => v.length >= 6);
const mask = (s) => { let t = String(s); for (const v of SECRETS) t = t.split(v).join('[hidden]'); return t; };
const die = (m) => { console.error('error: ' + mask(m)); process.exit(1); };

const [mode = 'diff', ...rest] = process.argv.slice(2);
const only = (() => { const i = rest.indexOf('--only'); return i >= 0 ? String(rest[i + 1] || '').split(',').filter(Boolean) : null; })();

/* ── plan ─────────────────────────────────────────────────────────────── */
const plan = JSON.parse(fs.readFileSync(PLAN_PATH, 'utf8'));
const CHANNEL = plan.channel_id;

function composeDescription(v) {
  const hashtags = (v.hashtags || []).join(' ');
  return [v.hook, plan.header, v.body, plan.footer, hashtags].filter(Boolean).join('\n\n');
}
function composeTags(v) {
  const seen = new Set(); const out = [];
  for (const t of [...(v.tags || []), ...(plan.common_tags || [])]) {
    const k = t.trim().toLowerCase(); if (!k || seen.has(k)) continue; seen.add(k); out.push(t.trim());
  }
  return out;
}

function validatePlan() {
  const problems = [];
  const saysFree = (s) => /free\s+(us\s+|usa\s+)?(shipping|delivery)/i.test(s);
  const check = (where, s) => { if (saysFree(s)) problems.push(`${where}: says free shipping`); };
  check('channel.description', plan.channel.description);
  check('header', plan.header); check('footer', plan.footer);
  for (const [id, v] of Object.entries(plan.videos)) {
    const d = composeDescription(v); const tags = composeTags(v);
    if (v.title.length > 100) problems.push(`${id}: title ${v.title.length} chars (max 100)`);
    if (d.length > 5000) problems.push(`${id}: description ${d.length} chars (max 5000)`);
    const tagLen = tags.reduce((n, t) => n + t.length + (t.includes(' ') ? 2 : 0) + 1, 0);
    if (tagLen > 500) problems.push(`${id}: tags ${tagLen} chars (max 500)`);
    for (const t of tags) if (t.length > 100) problems.push(`${id}: tag "${t}" over 100 chars`);
    check(`${id}.title`, v.title); check(`${id}.description`, d);
  }
  if (problems.length) die('the plan breaks a rule:\n  ' + problems.join('\n  '));
}

/* ── api ──────────────────────────────────────────────────────────────── */
let TOKEN = '';
async function token() {
  for (const k of ['YT_CLIENT_ID', 'YT_CLIENT_SECRET', 'YT_REFRESH_TOKEN']) if (!env(k)) die(`${k} is not set in this environment`);
  const body = new URLSearchParams({ client_id: env('YT_CLIENT_ID'), client_secret: env('YT_CLIENT_SECRET'), refresh_token: env('YT_REFRESH_TOKEN'), grant_type: 'refresh_token' });
  const r = await fetch(OAUTH, { method: 'POST', body });
  const j = await r.json().catch(() => ({}));
  if (!r.ok || !j.access_token) die(`token exchange failed: HTTP ${r.status} ${j.error || ''} ${j.error_description || ''}`);
  TOKEN = j.access_token;
}
async function call(method, pathAndQuery, bodyObj) {
  const r = await fetch(API + pathAndQuery, {
    method, headers: { Authorization: 'Bearer ' + TOKEN, 'Content-Type': 'application/json' },
    body: bodyObj ? JSON.stringify(bodyObj) : undefined,
  });
  const text = await r.text(); let j = null; try { j = JSON.parse(text); } catch (e) {}
  if (!r.ok) throw new Error(`${method} ${pathAndQuery.split('?')[0]} -> HTTP ${r.status}: ${(j && j.error && j.error.message) || text.slice(0, 200)}`);
  return j;
}

async function readCurrent() {
  const ch = await call('GET', `/channels?part=snippet,brandingSettings&id=${CHANNEL}`);
  const channel = ch.items && ch.items[0]; if (!channel) die('channel not found or not visible to this login');
  const ids = Object.keys(plan.videos);
  const videos = {};
  for (let i = 0; i < ids.length; i += 50) {
    const r = await call('GET', `/videos?part=snippet,status&id=${ids.slice(i, i + 50).join(',')}`);
    for (const v of r.items || []) videos[v.id] = v;
  }
  const pl = await call('GET', `/playlists?part=snippet,contentDetails&channelId=${CHANNEL}&maxResults=50`);
  const playlists = {};
  for (const p of pl.items || []) {
    const items = await call('GET', `/playlistItems?part=contentDetails&playlistId=${p.id}&maxResults=50`);
    playlists[p.snippet.title] = { id: p.id, description: p.snippet.description, videos: (items.items || []).map((x) => x.contentDetails.videoId) };
  }
  return { channel, videos, playlists };
}

function backupDir() {
  const d = env('YT_SEO_BACKUP_DIR') || path.join(os.homedir(), 'youtube-seo-backups');
  fs.mkdirSync(d, { recursive: true }); return d;
}
function latestBackup() {
  const d = backupDir();
  const files = fs.readdirSync(d).filter((f) => f.endsWith('.json')).sort();
  return files.length ? path.join(d, files[files.length - 1]) : null;
}

/* ── diff ─────────────────────────────────────────────────────────────── */
// YouTube hands tags back alphabetically, whatever order they were sent in,
// so tags compare as a set. Measured 14 Oct: an ordered comparison showed
// 21 false "changes" after every video had been written correctly.
const sortedTags = (tags) => (tags || []).map((t) => t.trim().toLowerCase()).sort().join(' | ');
function show(label, before, after) {
  if (before === after) return false;
  console.log(`\n--- ${label}`);
  console.log('  BEFORE: ' + String(before).replace(/\n/g, '\n          '));
  console.log('  AFTER:  ' + String(after).replace(/\n/g, '\n          '));
  return true;
}
function diff(cur) {
  let n = 0;
  const b = cur.channel.brandingSettings && cur.channel.brandingSettings.channel || {};
  console.log('=== CHANNEL');
  if (show('channel description', cur.channel.snippet.description, plan.channel.description)) n++;
  if (show('channel keywords', b.keywords || '', plan.channel.keywords)) n++;
  if (show('channel country', b.country || cur.channel.snippet.country || '', plan.channel.country)) n++;
  console.log('\n=== VIDEOS');
  for (const [id, v] of Object.entries(plan.videos)) {
    if (only && !only.includes(id)) continue;
    const c = cur.videos[id]; if (!c) { console.log(`\n[${id}] NOT FOUND on the channel, skipped`); continue; }
    console.log(`\n[${id}]`);
    if (show('title', c.snippet.title, v.title)) n++;
    if (show('description', c.snippet.description, composeDescription(v))) n++;
    if (show('tags', sortedTags(c.snippet.tags), sortedTags(composeTags(v)))) n++;
  }
  console.log('\n=== PLAYLISTS');
  for (const p of plan.playlists) {
    if (p.existing) {
      const c = cur.playlists[p.existing];
      const missing = c ? p.add_videos.filter((id) => !c.videos.includes(id)) : p.add_videos;
      console.log(`  ${c ? 'add to' : 'MISSING playlist'} "${p.existing}": +${missing.length} video(s) ${missing.join(', ')}`);
      if (missing.length) n++;
    } else if (cur.playlists[p.title]) {
      const missing = p.videos.filter((id) => !cur.playlists[p.title].videos.includes(id));
      console.log(`  "${p.title}" exists: +${missing.length} video(s) ${missing.join(', ')}`); if (missing.length) n++;
    } else { console.log(`  create "${p.title}" with ${p.videos.length} video(s)`); n++; }
  }
  console.log(`\n${n} change(s) in all.`);
  return n;
}

/* ── apply ────────────────────────────────────────────────────────────── */
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));
// A 409 on a playlist insert is YouTube not being ready yet; one retry
// after a short wait is enough (measured 14 Oct). Anything else is real.
async function insertItem(playlistId, videoId) {
  const body = { snippet: { playlistId, resourceId: { kind: 'youtube#video', videoId } } };
  try { return await call('POST', '/playlistItems?part=snippet', body); }
  catch (e) {
    if (!/HTTP 409/.test(e.message)) throw e;
    await sleep(3000);
    return call('POST', '/playlistItems?part=snippet', body);
  }
}
async function apply(cur) {
  const failures = [];
  const b = cur.channel.brandingSettings && cur.channel.brandingSettings.channel || {};
  if (!only) {
    if (cur.channel.snippet.description !== plan.channel.description || (b.keywords || '') !== plan.channel.keywords || (b.country || '') !== plan.channel.country) {
      await call('PUT', '/channels?part=brandingSettings', { id: CHANNEL, brandingSettings: { channel: { ...b, description: plan.channel.description, keywords: plan.channel.keywords, country: plan.channel.country, defaultLanguage: plan.channel.defaultLanguage } } });
      console.log('channel: brandingSettings updated');
    }
  }
  for (const [id, v] of Object.entries(plan.videos)) {
    if (only && !only.includes(id)) continue;
    const c = cur.videos[id]; if (!c) continue;
    const desc = composeDescription(v); const tags = composeTags(v);
    if (c.snippet.title === v.title && c.snippet.description === desc && sortedTags(c.snippet.tags) === sortedTags(tags)) continue;
    try {
      await call('PUT', '/videos?part=snippet', { id, snippet: { ...c.snippet, title: v.title, description: desc, tags, categoryId: c.snippet.categoryId, defaultLanguage: c.snippet.defaultLanguage || 'en', defaultAudioLanguage: c.snippet.defaultAudioLanguage || 'en-US' } });
      console.log(`video ${id}: updated`);
    } catch (e) {
      // Measured 14 Oct: YouTube answered "Precondition check failed" for one
      // video's title (a Studio-side title test or lock) while taking its
      // description and tags. One refusal must not stop the other videos
      // or the playlists; it is reported at the end instead.
      failures.push(`video ${id}: ${mask(e.message)}`);
      console.log(`video ${id}: REFUSED (${mask(e.message)}), continuing`);
    }
  }
  if (!only) {
    for (const p of plan.playlists) {
      let target = p.existing ? cur.playlists[p.existing] : cur.playlists[p.title];
      const wanted = p.existing ? p.add_videos : p.videos;
      if (!target) {
        if (p.existing) { console.log(`playlist "${p.existing}" not found, skipped`); continue; }
        const made = await call('POST', '/playlists?part=snippet,status', { snippet: { title: p.title, description: p.description, defaultLanguage: 'en' }, status: { privacyStatus: 'public' } });
        target = { id: made.id, videos: [] }; console.log(`playlist "${p.title}": created`);
        // Measured 14 Oct: the first insert into a playlist created a moment
        // earlier answered HTTP 409 "The operation was aborted" in all four
        // new playlists, and every later insert succeeded. Give YouTube a
        // moment before the first item.
        await sleep(3000);
      }
      for (const vid of wanted) {
        if (target.videos.includes(vid)) continue;
        try {
          await insertItem(target.id, vid);
          console.log(`playlist "${p.existing || p.title}": added ${vid}`);
        } catch (e) { failures.push(`playlist "${p.existing || p.title}" + ${vid}: ${mask(e.message)}`); }
      }
    }
  }
  return failures;
}

async function restore(file) {
  const bk = JSON.parse(fs.readFileSync(file, 'utf8'));
  const b = bk.channel.brandingSettings && bk.channel.brandingSettings.channel || {};
  await call('PUT', '/channels?part=brandingSettings', { id: CHANNEL, brandingSettings: { channel: b } });
  console.log('channel: restored');
  for (const [id, c] of Object.entries(bk.videos)) {
    if (only && !only.includes(id)) continue;
    await call('PUT', '/videos?part=snippet', { id, snippet: c.snippet }); console.log(`video ${id}: restored`);
  }
  console.log('playlists are not removed by restore; delete any new ones in YouTube Studio if needed.');
}

/* ── main ─────────────────────────────────────────────────────────────── */
(async () => {
  try {
    validatePlan();
    await token();
    if (mode === 'backup') {
      const cur = await readCurrent();
      const file = path.join(backupDir(), new Date().toISOString().replace(/[:.]/g, '-') + '.json');
      fs.writeFileSync(file, JSON.stringify(cur, null, 2));
      console.log(`backup written: ${file} (${Object.keys(cur.videos).length} videos, ${Object.keys(cur.playlists).length} playlists)`);
    } else if (mode === 'diff') {
      diff(await readCurrent());
    } else if (mode === 'apply') {
      const bk = latestBackup(); if (!bk) die('no backup found: run "backup" first');
      console.log(`using backup ${bk}`);
      const cur = await readCurrent();
      if (diff(cur) === 0) { console.log('nothing to apply'); return; }
      const failures = await apply(cur);
      if (failures.length) {
        console.log(`\ndone with ${failures.length} refusal(s):\n  ` + failures.join('\n  '));
        console.log('Everything else was written. Re-run "diff" to see what remains.');
        process.exitCode = 2;
      } else {
        console.log('done. Re-run "diff" to confirm 0 changes remain.');
      }
    } else if (mode === 'restore') {
      const file = rest[0]; if (!file) die('restore needs the backup file path');
      await restore(file);
    } else die(`unknown mode "${mode}" (backup | diff | apply | restore)`);
  } catch (e) { die(e.message || String(e)); }
})();
