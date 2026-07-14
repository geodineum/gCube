# gCube — Integration Contract

**Role:** WordPress child theme providing 3D cube visual identity and cube-specific features (PWA, analytics, face management) on top of the **gTemplate** parent theme and the **gCore** framework. gCube owns only cube-specific surface; all ecosystem infrastructure (config, rendering, REST, CLI, gNode connectivity) is inherited from gTemplate or delegated to gCore.

---

## 1. PROVIDES

Interfaces other components may rely on.

### 1.1 WP-CLI command — `wp gcube viewkey`
Cube-only ViewKey command.
```
wp gcube viewkey [--regenerate]
```
Evidence: `inc/cli/class-gcube-cli.php`

### 1.2 REST endpoint — `POST /gcube/v1/sync-face-mapping`
Triggers synchronization of the cube face mapping to ValKey.
- **Auth:** requires `manage_options` capability.
- Evidence: `inc/rest/resources/sync.php`
- **Response (200):** `{ "success": bool, "message": string, "site_id": string }`
- **Response (500):** `{ "success": false, "message": string }`
- Evidence: `inc/rest/resources/sync.php`

Additional cube cache/bundle endpoints (all `permission_callback` = `manage_options`):
- **`GET /gcube/v1/cache/stats`** — returns cache statistics. Evidence: `inc/integrations/features/keybased.php`
- **`POST /gcube/v1/cache/invalidate`** — invalidates cache entries; optional body param `pattern` (defaults to `all`). Evidence: `inc/integrations/features/keybased.php`
- **`POST /gcube/v1/bundle/invalidate`** — invalidates the built bundle. Evidence: `inc/integrations/features/keybased.php`

### 1.3 Face mapping persistence — ValKey key `{site_id}:gnode:face_mapping`
gCube writes the cube's face mapping as a JSON-encoded ValKey STRING via gNode-Client.
```
gNodeClient::fcall('GNODE_CACHE_SET', [], [$key, $json, $ttl, $site_id])
  where $key = '{' . $site_id . '}:gnode:face_mapping'
```
- Literal braces `{…}` are intentional (ValKey cluster hash-tag).
- JSON encoded with `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`.
- Evidence: `inc/rendering/content-sync.php`

### 1.4 Customizer settings schema
Canonical per-face setting IDs (WordPress **theme mods**, resolved through `gcube_mod()`/`gcube_face_mod()` with canonical defaults), for `N` in `1..6` (customizer numbering; DOM face ids 0–5 are translated by `gcube_face_mod()`):
```
cube_face_N_source
cube_face_N_content_id
cube_face_N_title
cube_face_N_enabled
cube_face_N_category_filter
```
Evidence: `inc/customizer/schema.php`; `inc/rendering/content-sync.php`

### 1.5 Admin analytics dashboard
`wp-admin` submenu mounted under `gcore-dashboard`; page asset enqueues are gated on the hook suffix returned by `add_submenu_page()`. Requires the `AnalyticsManager` service from gCore.
Evidence: `inc/admin/analytics.php`

---

## 2. CONSUMES / REQUIRES

What gCube needs, and from which component.

| Requirement | From | Expected form | Evidence |
|---|---|---|---|
| **gTemplate parent theme** | gTemplate | Child-theme relationship `Template: gtemplate` in `style.css` | `style.css` |
| **gCore framework** | gCore | Service locator singleton `$gCore` (WP global) with `getService(name)` returning managers implementing `ModuleInterface` | `inc/rendering/content-sync.php` |
| **gNode-Client** | gCore (provides client) | `$gCore->getService('gnode_client')`; methods: `registerTemplate(id,content)`, `fcall(luaName,keys,args)`, `manifestSet(id,manifest)`, `assetStore(id,html,type,ttl)`, `getBundled(key)`, `invalidateBundle()` | `inc/rendering/content-sync.php`; `inc/integrations/features/keybased.php` |
| **gNodeConfigLoader** | gTemplate | `\gTemplate\gNodeConfigLoader` aliased to `\gCube\gNodeConfigLoader` via `class_alias()` | `inc/bootstrap/gNodeConfigLoader.php` |
| **Parent helper functions** | gTemplate | `gtemplate_gnode()`, `gtemplate_gnode_keybased()` → gNode-Client instances; `gtemplate_get_site_id()` → site ID string | `inc/rendering/content-sync.php` |
| **ValKey stream** `{environment}:gnode:unified:default` | ValKey (via gNode-Client) | XREAD/XADD command/response stream; used internally by gNode-Client, **not directly accessed by gCube** | gNode-Client internals (gCore); no direct reference in gCube code |
| **ValKey key** `{site_id}:gnode:face_mapping` | ValKey (written by gCube) | JSON face-mapping structure (see §4.1) | `inc/rendering/content-sync.php` |

**Hard dependency note:** Activating gCube without gTemplate as the parent theme causes a fatal error — the `gtemplate_*` helpers above are required and not vendored.

---

## 3. CROSS-DEPENDENCY FLOW

```
gCube → gTemplate (parent) → gCore        inherits operational infrastructure; calls gtemplate_* helpers
gCube → gCore.getService('gnode_client')  obtains gNode-Client for ValKey ops
gCube → gNode-Client → ValKey             writes face_mapping JSON for daemon's bundle builder
gCube → gTemplate REST (contact-form face)  form posts go to the parent's endpoint; the comms/email wire is gTemplate-owned
gCube → gNode daemon (via gNode-Client)   registerTemplate() → daemon renders Tera templates
gCube ← gNode daemon (via bundle)         daemon reads face_mapping, builds bundle; gCube reads via getBundled()
```

---

## 4. WIRE FORMATS & STREAM KEYS

### 4.1 `{site_id}:gnode:face_mapping` — ValKey STRING (JSON)
Written by gCube; read by the gNode daemon's bundle builder.

| Field | Type | Req | Notes |
|---|---|---|---|
| `site_id` | string | yes | scalar |
| `faces` | array of Face | yes | see §5 Face |
| `metadata` | object | yes | `{site_name, site_url, description, theme_version, synced_at:int}` |
| `navigation` | object | yes | |
| `posts` | array | yes | |

Evidence: `inc/rendering/content-sync.php`

### 4.2 FCALL — `GNODE_CACHE_SET`
RESP form: function name + empty key list + args `[key (string), value (JSON string), ttl (int, 0 = no expiry), site_id (string)]`. Returns boolean, or null on failure.
Key is passed **brace-literal**: `"{".$site_id."}:gnode:face_mapping"`. The consumer Lua `build_key` (gNode `gnode_cache.lua`, Case 1) returns an already-braced key unchanged.
Evidence: `inc/rendering/content-sync.php`

### 4.3 Outbound comms — parent-owned (not a gCube wire)
gCube no longer writes the `{site_id}:gnode:comms:{environment}` stream: no `CommsManager`/`queueCommsMessage` references remain in gCube code (removed together with the duplicate email-to-post bridge). The contact-form face ships markup only (`templates/faces/contact-form.tera`); submissions go to the parent gTemplate endpoint, whose contract documents the comms message wire.
Evidence: `grep -r "Comms\|comms" inc/ functions.php` → no matches; `templates/faces/contact-form.tera`

### 4.4 Customizer setting names
`cube_face_N_source`, `cube_face_N_content_id`, `cube_face_N_title`, `cube_face_N_enabled`, `cube_face_N_category_filter` for `N = 1..6`, stored as WordPress **theme mods** and read via `gcube_mod()`/`gcube_face_mod()` (see §1.4).
Evidence: `inc/customizer/schema.php`; `inc/rendering/content-sync.php`

### 4.5 REST sync response
See §1.2.

---

## 5. PUBLIC TYPES

**Face**
```
{
  id: int (0-5),
  label: string,
  source: string  (glass|glass_page|glass_custom|demo|page|post|posts|custom),
  enabled: bool,
  position: string (top|front|right|back|left|bottom),
  css_class: string (one|two|three|four|five|six),
  content_id?: int,
  template_id?: string,
  html?: string,
  category?: int
}
```

**FaceMapping**
```
{
  site_id: string,
  faces: Face[],
  metadata: { site_name, site_url, description, theme_version: string, synced_at: int },
  navigation: object,
  posts: array
}
```

**Manifest**
```
{ layout: string, type: string, version: string,
  slots: [{id, asset_key, type}],
  sections: { navigation, meta },
  build_options: { compress: bool, ttl: int } }
```

**Bundle** (from keybased integration)
```
{ faces: [{ face_id: int, content_html: string, ... }], ... }
```

---

## 6. EXAMPLE — sync face mapping

```bash
# Trigger a face-mapping sync via REST (requires manage_options auth).
curl -X POST https://site.example/wp-json/gcube/v1/sync-face-mapping \
  -H "X-WP-Nonce: <wp_rest_nonce>" \
  --cookie "<authenticated wp cookies>"
# 200 -> {"success":true,"message":"...","site_id":"site.example"}
```

Internally this builds the FaceMapping JSON and calls:
```php
$key = '{' . $site_id . '}:gnode:face_mapping';
$json = wp_json_encode($mapping, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$client->fcall('GNODE_CACHE_SET', [], [$key, $json, $ttl, $site_id]);
```

---

## 7. ADHERENCE — known cross-component notes

- **ADHERES (face_mapping key):** gCube writes the brace-literal key `"{".$site_id."}:gnode:face_mapping"` (`content-sync.php`). gNode `GNODE_CACHE_SET` `build_key` Case 1 (`gnode_cache.lua`) returns the already-braced key unchanged → correct final key `{site_id}:gnode:face_mapping`.
- **LATENT DRIFT (low):** a child theme may write the *same* artifact key **unbraced**; `build_key` Case 2 (`gnode_cache.lua`) normalizes it to the identical key. Harmless today because both spellings go through `GNODE_CACHE_SET`, but they rely on consumer leniency — any future write via raw `XADD`/`SET` (bypassing `build_key`) would split the keyspace and orphan one theme's bundle.
- **Comms is parent-owned:** gCube emits no comms messages itself (see §4.3); the comms wire adherence notes live with gTemplate / Geodineum-COMMS.
- **ACL note:** gCube's write surface is the `{site_id}:gnode:face_mapping` STRING (via `GNODE_CACHE_SET`) **plus** a PUBLISH to the pub/sub channel `{site_id}:events:invalidate` (bundle-rebuild cache-invalidation events, fired by `gcube_trigger_bundle_rebuild()` → `content-sync.php`). An operator scoping a ValKey ACL must grant both. gCube does **not** write `{site_id}:config:*` (owned by gTemplate `inc/integrations/gnode/config.php`); all persistence flows through gCore's service layer (no raw Redis/ValKey wire).

---

## 8. LIMITATIONS (contract-relevant)

- **No template deletion:** no `deleteTemplate()`; orphaned templates persist in daemon memory until restart (`content-sync.php`).
- **Synchronous registration:** `registerTemplate()` blocks on the daemon; page saves may time out (mitigated by `wp_schedule_single_event()` deferral).
- **Non-atomic sync:** `fcall()` face_mapping write has no transaction/rollback; a mid-sync crash can leave a partially-written key.
- **No REST nonce enforcement:** `POST /gcube/v1/sync-face-mapping` does not explicitly validate a nonce; it relies on WordPress's standard REST auth (`manage_options`).
- **Unversioned bundle keys:** `{site_id}:gnode:bundle:{key}` has no version suffix; structural changes can leave incompatible cached bundles.
- **Hard gTemplate dependency:** requires `gtemplate_get_site_id()`, `gtemplate_gnode()`, `gtemplate_gnode_keybased()` from the parent.
- **No i18n:** customizer/admin/analytics strings are hard-coded English (not wrapped in `__()`/`_e()`).
- **KeyBasedClient deprecation:** `inc/integrations/features/keybased.php` notes "KeyBasedClient deprecated — gNodeClient is now unified"; some paths may still expect old behavior.
