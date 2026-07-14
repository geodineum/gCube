# gCube Manager Configuration Summary

**Date:** 2025-11-05  
**Site:** your-site.example  
**Environment:** testing/staging  
**Framework:** gCore 0.1.0

---

## Overview

This directory contains YAML configuration files for all gCore managers used by gCube. Each manager has been configured specifically for the staging environment with appropriate settings for security, performance, and the 3D cube interface.

---

## Manager Configurations

### 1. SecurityManager.yaml

**Purpose:** Security, XSS prevention, firewall, authentication

**Key Settings:**
- ✅ All security traits enabled
- ✅ TLS 1.3 with strong cipher suites
- ✅ Firewall enabled in deny mode
- ✅ Rate limiting enabled
- ✅ Audit logging: detailed, 90-day retention
- ⚙️ Notifications: alerts@example.com
- ⚙️ Auto-updates: **disabled** (manual control for staging)

**Implementation:** Standalone (ValKeyStorage)

---

### 2. ErrorManager.yaml

**Purpose:** Error logging, notifications, ValKey-based tracking

**Key Settings:**
- ✅ Log level: warning (staging)
- ✅ Log path: `/var/log/gcore/gcube-error.log`
- ✅ ValKey prefix: `{your_site}:error:` (multi-tenant)
- ✅ Error categories: system, security, cache, gnode, template
- ✅ Email notifications: alerts@example.com (CRITICAL only)
- ⚙️ Rate limit: 50 emails/hour
- ⚙️ Retention: 30 days

**Implementation:** Standalone (ValKeyStorage)

**⚠️ Important:** Requires proper ValKey ACL permissions for `{your_site}:error:*` keyspace.

---

### 3. CacheManager.yaml

**Purpose:** ValKey-based caching with gNode integration

**Key Settings:**
- ✅ Prefix: `{your_site}:cache:` (multi-tenant isolation)
- ✅ Default TTL: 1 hour
- ✅ Serializer: igbinary (fast binary serialization)
- ✅ Compression: enabled (threshold: 1KB)
- ✅ Persistent connection to ValKey
- ⚙️ Auth: gnode_client (Phase 4: migrate to gnode_client_your_site)

**gCube-Specific Cache Groups:**
- `page`: 3600s (full page cache)
- `fragment`: 1800s (HTML fragments)
- `template`: 7200s (Tera templates)
- `api`: 300s (API responses)
- `gnode`: 600s (gNode responses)
- `cube_face`: 3600s (3D cube face content)

**Implementation:** Standalone (ValKeyStorage)

**⚠️ Important:** 
- Requires ValKey auth credentials (TODO: read from .gnode/valkey_client.password)
- Requires ACL permissions for `{your_site}:cache:*` keyspace

---

### 4. APIManager.yaml

**Purpose:** REST API, rate limiting, caching

**Key Settings:**
- ✅ Namespace: `gcube/v1`
- ✅ Rate limiting: 100 req/min (relaxed for staging)
- ✅ Cache TTL: 30 minutes
- ✅ Metrics enabled
- ✅ Validation enabled
- ✅ Admin UI enabled
- ⚙️ WebSocket: **disabled** (future feature)

**Dynamic Endpoints:**
- `/health`: Health check (public)
- `/gnode`: gNode proxy (authenticated)

**Traits Enabled:**
- EndpointManagerTrait
- RequestProcessorTrait
- ResponseCacheTrait
- RateLimiterTrait
- AuthenticationTrait
- ValidationTrait
- MetricsCollectorTrait

**Implementation:** Standalone

---

### 5. FormatManager.yaml

**Purpose:** gNode-powered format detection and conversion

**Key Settings:**
- ✅ Implementation: **gNode** (uses daemon)
- ✅ Requires: gnode_client
- ✅ Validation: strict mode
- ✅ Security: sandboxed conversion
- ✅ Max message size: 1MB
- ✅ Metrics tracking enabled
- ⚙️ Conversion timeout: 5 seconds

**Implementation:** gNode (requires gNode daemon)

**⚠️ Important:** Requires working gNode-Client connection.

---

### 6. TemplateRenderer.yaml

**Purpose:** gNode-powered Tera template rendering for 3D cube faces

**Key Settings:**
- ✅ Implementation: **gNode** (uses daemon for Tera)
- ✅ Requires: gnode_client
- ✅ HTMX: **enabled** (critical for progressive enhancement)
- ✅ Cache: 1 hour TTL
- ✅ Auto-escape: enabled (XSS prevention)
- ✅ Sandbox: enabled (security)
- ✅ Dependency tracking: enabled (transitive invalidation)
- ⚙️ Render timeout: 5 seconds

**gCube 3D Cube Face Templates:**
1. `cube/face-one.html.tera` - Top face
2. `cube/face-two.html.tera` - Front face (home)
3. `cube/face-three.html.tera` - Right face
4. `cube/face-four.html.tera` - Back face
5. `cube/face-five.html.tera` - Left face
6. `cube/face-six.html.tera` - Bottom face

**Implementation:** gNode (requires gNode daemon)

**⚠️ Important:** 
- Requires working gNode-Client connection
- HTMX support is critical for gCube's progressive enhancement architecture

---

## Configuration Loading

gCore loads manager configurations in this order:

1. **gCore defaults** (`~/gh/gCore/config/managers/*.yaml`)
2. **gCube overrides** (`~/gh/gCube/config/managers/*.yaml`) ← **This directory**
3. **Runtime config** (passed to gCore initialization)

gCube-specific configs take precedence over gCore defaults.

---

## Multi-Tenant Isolation (CRITICAL)

All managers that use ValKey storage MUST use site-specific key prefixes:

- ✅ **ErrorManager:** `{your_site}:error:*`
- ✅ **CacheManager:** `{your_site}:cache:*`
- ⚠️ **SecurityManager:** Uses ValKeyStorage (check prefix)

### Phase 4 Migration (Pending)

**Current ACL User:** `gnode_client` (shared across all sites)

**Phase 4 Goal:** Per-site ACL users
- Create user: `gnode_client_your_site`
- ACL pattern: `~{your_site}:*`
- Password: `/opt/.gnode/valkey_client_your_site.password`

This provides full keyspace isolation and limits blast radius in case of compromise.

---

## gNode Integration

Two managers use gNode daemon for processing:

### FormatManager (gNode)
- Format detection via gNode daemon
- Conversion via gNode daemon
- Requires gNode-Client connection
- Falls back to local processing if gNode unavailable

### TemplateRenderer (gNode)
- Tera template rendering via gNode daemon
- Server-side rendering in Rust (fast!)
- Caches rendered output in ValKey
- HTMX support for progressive enhancement

**Workaround:** gNode-Client has fallback mode for when daemon is unavailable.

---

## Staging vs Production Differences

| Setting | Staging | Production |
|---------|---------|------------|
| Debug | false | false |
| Error Log Level | warning | error |
| Cache TTL | 1800s (30min) | 3600s (1hr) |
| API Rate Limit | 100 req/min | 60 req/min |
| Auto Updates | disabled | enabled |
| Notification Email | alerts@example.com | ops@example.com |
| Log Retention | 30 days | 90 days |

---

## Environment Variables

Some configurations reference environment variables:

**Required:**
- `GNODE_ENVIRONMENT=testing` (set in `~/gh/gCube/.env`)

**Optional:**
- `VALKEY_PASSWORD` (if not using password file)
- `NOTIFICATION_EMAIL` (override default)

---

## File Locations

### Manager Configs (gCube-specific)
```
~/gh/gCube/config/managers/
├── SecurityManager.yaml
├── ErrorManager.yaml
├── CacheManager.yaml
├── APIManager.yaml
├── FormatManager.yaml
└── TemplateRenderer.yaml
```

### gCore Defaults (fallback)
```
~/gh/gCore/config/managers/
├── SecurityManager.yaml
├── ErrorManager.yaml
├── CacheManager.yaml
├── APIManager.yaml
├── FormatManager.yaml
└── TemplateRenderer.yaml
```

### ValKey Credentials
```
/opt/.gnode/valkey_daemon.password      # gNode daemon (read-only for user)
/opt/.gnode/valkey_client.password      # gNode client (TODO: create if missing)
```

### Log Files
```
/var/log/gcore/gcube-error.log        # ErrorManager logs
/var/log/valkey/valkey.log            # ValKey server logs
```

---

## Verification Checklist

### ✅ Configuration Files Created
- [x] SecurityManager.yaml
- [x] ErrorManager.yaml
- [x] CacheManager.yaml
- [x] APIManager.yaml
- [x] FormatManager.yaml
- [x] TemplateRenderer.yaml

### ⏳ ValKey ACL Permissions (Phase 4)
- [ ] Per-site user created: `gnode_client_your_site`
- [ ] ACL pattern: `~{your_site}:*`
- [ ] Password generated and stored
- [ ] CacheManager updated to use per-site user
- [ ] ErrorManager tested with new ACL

### ⏳ gNode Stream Processing (Debug Needed)
- [ ] Consumer group issue resolved
- [ ] geometric_discover_range responding
- [ ] FormatManager tested with gNode
- [ ] TemplateRenderer tested with gNode

### ⏳ Log Directory Setup
- [ ] `/var/log/gcore/` directory created
- [ ] Proper permissions (writable by web server)
- [ ] Log rotation configured
- [ ] ErrorManager logging verified

---

## Next Steps

1. **Immediate:** Verify configs load correctly
   ```bash
   # Check for YAML syntax errors
   php -r "yaml_parse_file('~/gh/gCube/config/managers/SecurityManager.yaml');"
   ```

2. **High Priority:** Fix ValKey ACL permissions
   - Create test: Can ErrorManager create `{your_site}:error:*` keys?
   - Create test: Can CacheManager create `{your_site}:cache:*` keys?

3. **High Priority:** Debug gNode stream processing
   - Verify FormatManager and TemplateRenderer work

4. **Medium Priority:** Create log directory
   ```bash
   sudo mkdir -p /var/log/gcore
   sudo chown www-data:www-data /var/log/gcore
   sudo chmod 755 /var/log/gcore
   ```

5. **Phase 4:** Migrate to per-site ACL users
   - Generate password for `gnode_client_your_site`
   - Update `/etc/valkey/users.acl`
   - Update CacheManager config
   - Test isolation

---

## Troubleshooting

### Manager Not Loading
**Symptom:** Manager missing from gCore instances list

**Check:**
```php
$gCore = \gCore\Modules\Core\gCore::getInstance();
var_dump($gCore->hasService('security_manager'));
```

**Possible Causes:**
- YAML syntax error
- Missing dependency (e.g., gnode_client for FormatManager)
- Manager disabled in config

### ValKey Permission Errors
**Symptom:** NOPERM errors in logs

**Check:**
```bash
sudo tail -100 /var/log/valkey/valkey.log | grep NOPERM
```

**Fix:**
- Update `/etc/valkey/users.acl`
- Add pattern: `~{your_site}:*`
- Reload: `valkey-cli ACL LOAD`

### gNode Connection Errors
**Symptom:** FormatManager or TemplateRenderer failing

**Check:**
```bash
systemctl status gnode-daemon
valkey-cli PING
```

**Fix:**
- Verify gNode daemon running
- Check gNode-Client connection

---

**Created:** 2025-11-05  
**Last Updated:** 2025-11-05  
**Maintainer:** Geodineum project  
**Documentation:** This file
