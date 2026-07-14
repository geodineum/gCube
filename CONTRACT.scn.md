# gCube :: CONTRACT primer (SCN)

> one-line: SCN primer — TRUTH = code on disk; this file is a point-in-time compression. Companion: CONTRACT.md (authoritative).

## ::ROLE
gCube = WordPress **child theme** = cube visual identity (3D 6-face geometry) + cube-only features (PWA, analytics, face mgmt) layered on **gTemplate** (parent) over **gCore** (framework). gNode=Sun, ValKey=backend. Stateless/state-aware: all persistent state → ValKey or WP options, never class properties. Owns ONLY cube surface; everything ecosystem-wide is inherited (gTemplate) or delegated (gCore). Composer pkg `geodineum/gcube-theme`.

## ::ANCHOR
- CLI `wp gcube viewkey [--regenerate]` → `inc/cli/class-gcube-cli.php`
- REST `POST /gcube/v1/sync-face-mapping` (cap `manage_options`) → `inc/rest/resources/sync.php`; resp `{success,message,site_id}`
- REST `GET /gcube/v1/cache/stats` | `POST /gcube/v1/cache/invalidate` (body `pattern`, default all) | `POST /gcube/v1/bundle/invalidate` — all cap `manage_options` → `inc/integrations/features/keybased.php`
- fcall `GNODE_CACHE_SET [] [$key,$json,$ttl,$site_id]`, `$key="{".$site_id."}:gnode:face_mapping"` → `inc/rendering/content-sync.php`; json flags `JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE`
- customizer IDs `cube_face_N_{source,content_id,title,enabled,category_filter}` N=1..6 THEME MODS via `gcube_mod()/gcube_face_mod()` (DOM face 0-5 → setting 1-6) → `inc/customizer/schema.php`; content-sync.php
- admin analytics submenu under `gcore-dashboard`, enqueues gated on `add_submenu_page()` hook suffix, needs `AnalyticsManager` → `inc/admin/analytics.php`
- config-loader alias `\gTemplate\gNodeConfigLoader`→`\gCube\gNodeConfigLoader` → `inc/bootstrap/gNodeConfigLoader.php`
- parent helpers `gtemplate_gnode() | gtemplate_gnode_keybased() | gtemplate_get_site_id()` → content-sync.php
- keybased integration → `inc/integrations/features/keybased.php`

## ::ARCHITECTURE
PHP (`inc/`). **No PSR-4** — explicit `require_once` via 9-phase bootstrap (`inc/bootstrap/autoload.php`). Dep `geodineum/gcore` (composer). Modules: (1) Bootstrap [constants, autoload, configloader alias] (2) Assets [CSS/JS enqueue (built-bundle-or-main.css), PWA manifest/sw rewrite gated on TWO conditions — Chapter-2 `gcore-manifest` ext present (`gcube_manifest_available()`, false under inert Ch.1 stub) AND `enable_pwa` default OFF; inert in base Ch.1] (3) Rendering [face content sources incl. glass, template sync, content-sync→ValKey] (4) Integrations [bundle-manifest nav sync, keybased cache — email→post is parent-owned, child copy deleted] (5) REST [sync-face-mapping] (6) Admin [analytics] (7) CLI [viewkey only] (8) Customizer [schema (canonical IDs+defaults), face controls, CSS out — sanitization parent-side `gtemplate_sanitize_option`]. Design: ALL gNode/ValKey ops route through `$gCore->getService()` — never direct client instantiation, never raw Redis/ValKey. Daemon comms via (a) `registerTemplate()` Tera sync (b) `fcall('GNODE_CACHE_SET')` face_mapping persist (c) `manifestSet/assetStore` bundle build. Philosophy: single-responsibility (cube-only); zero parent duplication (pre-launch cut ~14k LOC, e.g. `wp gcube register`→`wp gtemplate register`); platform-aware (gCore auto-detect env+site_id → multi-tenant, no hardcoding); graceful degradation (`GCUBE_FREE_TIER` → WP transients + PHP render if gNode down); transparent abstraction (gCore service layer respects ACL, enables backend swap).

## ::IO
INPUTS:
- read gCore svc locator `$gCore->getService(name)` → managers (ModuleInterface)
- read gNode-Client via `getService('gnode_client')`
- read parent helpers `gtemplate_*` (site_id, client instances)
- read customizer THEME MODS `cube_face_N_*` via `gcube_mod/gcube_face_mod` (canonical defaults in schema.php)
- read bundle cache `getBundled(key)` ← daemon-built from face_mapping
- read env+site_id ← gCore auto-detect (WP_ENVIRONMENT_TYPE | domain); `registration.yaml`
OUTPUTS:
- write ValKey STRING `{site_id}:gnode:face_mapping` = JSON{site_id, faces[], metadata{site_name,site_url,description,theme_version,synced_at}, navigation{}, posts[]} via `fcall(GNODE_CACHE_SET,[],[key,json,ttl,site_id])`
- PUBLISH pub/sub channel `{site_id}:events:invalidate` = JSON{event:bundle_rebuild_requested,site_id,timestamp} via `gcube_trigger_bundle_rebuild()` → `content-sync.php` (ACL must grant this channel)
- write theme mods (customizer)
- register Tera templates via `registerTemplate(id,content)`
- emit `manifestSet/assetStore` for bundle building
- REST JSON `{success,message,site_id}`
NON-OUTPUT: comms STREAM `{site_id}:gnode:comms:{env}` — parent-owned; zero CommsManager/queueCommsMessage refs in gCube; contact-form face = markup only, posts to gTemplate endpoint.
WIRE: face_mapping key brace-literal `{`+site_id+`}` (cluster hash-tag); fcall ttl=0→no expiry.

## ::CONTRACT
PROVIDES:
- `wp gcube viewkey [--regenerate]`
- REST `POST /gcube/v1/sync-face-mapping` + `GET /gcube/v1/cache/stats` + `POST /gcube/v1/cache/invalidate` + `POST /gcube/v1/bundle/invalidate` (all manage_options)
- ValKey `{site_id}:gnode:face_mapping` (FaceMapping JSON)
- customizer schema `cube_face_N_*` N=1..6 (theme mods, `gcube_mod/gcube_face_mod`)
- admin analytics panel (under gcore-dashboard)
CONSUMES:
- gTemplate: parent theme (`style.css Template: gtemplate`), helpers `gtemplate_*`, `gNodeConfigLoader` alias, sanitizer `gtemplate_sanitize_option`, contact-form/email endpoint — HARD dep (fatal if absent)
- gCore: `$gCore` svc locator, `gnode_client` svc {registerTemplate, fcall, manifestSet, assetStore, getBundled, invalidateBundle}, `AnalyticsManager`, `Cache`
- ValKey (via client): face_mapping key (write), unified:default stream (indirect, NOT direct), bundle cache (read)

## ::USECASES
- deploy 3D cube WP site (gCube+gTemplate identity + gCore+daemon accel)
- multi-tenant: one theme → many sites, per-site auto-detect site_id/env/creds, face_mapping per `{site_id}`
- offline PWA: Chapter-2-gated — requires BOTH Chapter-2 `gcore-manifest` ext (`gcube_manifest_available()`, false under inert Ch.1 stub) AND OPT-IN `enable_pwa` theme mod (default OFF); inert in base Ch.1; disabled → self-unregistering sw.js; runtime-rewritten manifest.json + sw.js; manifest URLs exempt non-prod gate
- face analytics: AnalyticsManager visitor-journey, hashed identifiers
- bundle render: sync face_mapping → daemon builds compressed HTML bundle → `getBundled()` ~1ms
- CSS3 GPU cube transforms (will-change, backface-visibility, contain) 60fps

## ::LIMITATIONS
- NO `deleteTemplate()` — orphaned templates linger in daemon RAM till restart (content-sync.php)
- `registerTemplate()` SYNCHRONOUS/blocking — page-save timeout risk; mitigated `wp_schedule_single_event()`
- face_mapping sync NON-ATOMIC — no txn/rollback on fcall() fail; partial write possible on crash
- REST sync has NO explicit nonce check — relies on WP standard REST auth (manage_options)
- bundle key `{site_id}:gnode:bundle:{key}` UNVERSIONED — schema change → incompatible stale bundles
- free-tier (`GCUBE_FREE_TIER`) incomplete — no automated free-tier manifest/sw.js
- NO circular-dependency guard (face→page→template→face = infinite recursion possible)
- NO i18n — strings hard-coded English
- per-site manifest only — no shared/global manifest for multisite
- KeyBasedClient deprecated note in keybased.php but old paths may persist
- HARD gTemplate helper dependency — no parent ⇒ fatal

## ::GRAPH
DEPENDS_ON: gTemplate (parent theme, helpers, configloader alias, sanitizers, contact-form endpoint) · gCore (svc locator, gnode_client, Analytics/Cache managers) · ValKey (face_mapping, bundle) · gNode daemon (Tera render, bundle build — indirect via client)
PROVIDES_TO: WordPress (child theme + REST + filter + WP-CLI + customizer + admin panel) · gNode daemon (face_mapping JSON to bundle-build)
ADHERES_TO: GNODE_CACHE_SET build_key contract of gNode (gnode_cache.lua) · FCALL allowlist `^(GNODE|GCUBE|COMMS|GC)_` of gNode-Client
ISOLATED_FROM: `{site_id}:config:*` keyspace (owned by gTemplate config.php) · `{site_id}:gnode:comms:{env}` stream (parent-owned; child email-to-post bridge deleted) · `{env}:gnode:unified:default` stream (client-internal, gCube never touches directly) · direct Redis/ValKey wire (always via gCore svc layer)

## ::LATENT
- "child theme over gTemplate over gCore; cube identity only; everything else inherited/delegated"
- "face_mapping JSON → {site_id}:gnode:face_mapping via fcall GNODE_CACHE_SET, brace-literal key, daemon builds bundle"
- "all ValKey through gCore service locator $gCore->getService('gnode_client') — never direct, ACL-respecting"
- "braced (gCube) vs unbraced (other child themes) face_mapping key both normalized by build_key — latent drift, low severity"
- "customizer = theme mods cube_face_N_* keyed 1..6; gcube_face_mod translates DOM face 0-5; schema.php single source of defaults"
- "comms/email wire is parent-owned; gCube ships contact-form markup only, zero CommsManager refs"
- "synchronous registerTemplate blocks; no deleteTemplate; non-atomic fcall sync"
- "9-phase require_once bootstrap, no PSR-4; inc/; pre-launch cut ~14k dup LOC"
- "graceful degradation GCUBE_FREE_TIER → WP transients; auto-detect site_id+env from domain"
