# gCube Manager Configuration Summary

**Created:** 2025-11-05  
**For:** your-site.example (gCube theme)  
**Environment:** testing/staging

---

## ✅ Configured Managers (6 Total)

### 1. **SecurityManager** - Security & Authentication
- **File:** `SecurityManager.yaml`
- **Implementation:** Standalone (ValKeyStorage)
- **Key Features:**
  - All 12 security traits enabled
  - TLS 1.3 with strong ciphers
  - Firewall + rate limiting
  - Email alerts: alerts@example.com
  - 90-day audit retention
- **Special Config:**
  - Auto-updates: DISABLED (manual control for staging)

### 2. **ErrorManager** - Dual Logging System ⭐
- **File:** `ErrorManager.yaml`
- **Implementation:** Standalone (ValKeyStorage + File)
- **Key Features:**
  - **File logging:** `~/gh/gCore/logs/gcube-error.log` (dev team access)
  - **ValKey logging:** `{your_site}:error:*` (fast queries)
  - Log rotation: 5MB x 5 files
  - Stack trace collection
  - Email alerts for CRITICAL errors only
- **Special Config:**
  - Dual logging strategy (ValKey + File)
  - Rich context collection (request, user, stack)

### 3. **CacheManager** - ValKey Caching
- **File:** `CacheManager.yaml`
- **Implementation:** Standalone (ValKeyStorage)
- **Key Features:**
  - Prefix: `{your_site}:cache:*` (multi-tenant)
  - igbinary serialization (fast binary)
  - Compression enabled (>1KB data)
  - Persistent ValKey connection
- **gCube Cache Groups:**
  - `page`: 3600s (full page)
  - `fragment`: 1800s (HTML fragments)
  - `template`: 7200s (Tera templates)
  - `cube_face`: 3600s (3D cube faces)
  - `gnode`: 600s (gNode responses)
  - `api`: 300s (API calls)

### 4. **APIManager** - REST API + Rate Limiting
- **File:** `APIManager.yaml`
- **Implementation:** Standalone
- **Key Features:**
  - Namespace: `gcube/v1`
  - Rate limiting: 100 req/min (staging)
  - Cache TTL: 30 min (staging)
  - Admin UI enabled
  - Metrics collection
- **Dynamic Endpoints:**
  - `/health` - Health check (public)
  - `/gnode` - gNode proxy (authenticated)

### 5. **FormatManager** - gNode Format Processing
- **File:** `FormatManager.yaml`
- **Implementation:** **gNode** (requires daemon)
- **Key Features:**
  - Format detection via gNode
  - Strict validation mode
  - Sandboxed conversion
  - 1MB message size limit
  - 5s conversion timeout
- **Requires:** Working gNode-Client connection

### 6. **TemplateRenderer** - Tera Templates via gNode ⭐
- **File:** `TemplateRenderer.yaml`
- **Implementation:** **gNode** (requires daemon)
- **Key Features:**
  - **HTMX enabled** (critical for gCube!)
  - Server-side Tera rendering (Rust)
  - 1 hour cache TTL
  - Auto-escape (XSS prevention)
  - Dependency tracking
- **gCube 3D Cube Templates:**
  1. `cube/face-one.html.tera` (top)
  2. `cube/face-two.html.tera` (front/home)
  3. `cube/face-three.html.tera` (right)
  4. `cube/face-four.html.tera` (back)
  5. `cube/face-five.html.tera` (left)
  6. `cube/face-six.html.tera` (bottom)

---

## 📂 File Structure

```
~/gh/gCube/config/
├── managers/
│   ├── SecurityManager.yaml         # Security + firewall
│   ├── ErrorManager.yaml            # Dual logging (ValKey + File)
│   ├── CacheManager.yaml            # ValKey caching
│   ├── APIManager.yaml              # REST API
│   ├── FormatManager.yaml           # gNode format processing
│   ├── TemplateRenderer.yaml        # gNode Tera templates
│   ├── CONFIGURATION_SUMMARY.md     # This file
│   └── MANAGER_CONFIGS_README.md    # Detailed docs
└── default-config.php                # Legacy config

~/gh/gCore/logs/
└── gcube-error.log                   # ErrorManager file logs
```

---

## 🔑 Key Configuration Decisions

### Multi-Tenant Isolation (CRITICAL)
All managers use site-specific prefixes:
- **ErrorManager:** `{your_site}:error:*`
- **CacheManager:** `{your_site}:cache:*`

### Dual Logging Strategy (ErrorManager)
**Why both ValKey and file logging?**

1. **ValKey Logging:**
   - Fast queries (O(1) hash lookups)
   - Real-time dashboards
   - Structured data for analytics
   - Auto-expiration (30 days)

2. **File Logging:**
   - Dev team can `tail -f` logs
   - No database queries needed
   - Works when ValKey is down
   - Easy `grep` for debugging
   - Located in dev-accessible path

**Best of both worlds!**

### gNode Integration
Two managers use gNode daemon:
- **FormatManager:** Format detection/conversion
- **TemplateRenderer:** Tera template rendering

**Benefits:**
- Server-side rendering in Rust
- Template caching in ValKey (<1ms retrieval)
- HTMX progressive enhancement

**Known Issue:** Stream processing currently broken (see SESSION_HANDOFF)
**Workaround:** Fallback mode available

### Staging vs Production
Key differences for staging environment:
- Cache TTL: 30 min (vs 1 hour production)
- Rate limits: 100 req/min (vs 60 production)
- Auto-updates: DISABLED (manual control)
- Log level: warning (vs error production)

---

## ⚠️ Prerequisites & TODOs

### ✅ Completed
- [x] All 6 manager YAML files created
- [x] gCore logs directory created (`~/gh/gCore/logs/`)
- [x] Multi-tenant prefixes configured
- [x] Dual logging configured (ErrorManager)
- [x] HTMX enabled (TemplateRenderer)
- [x] Cache groups defined for gCube
- [x] Documentation created

### ⏳ Pending (Phase 4 - Per-Site ACL)
- [ ] Create ValKey user: `gnode_client_your_site`
- [ ] Update `/etc/valkey/users.acl` with pattern: `~{your_site}:*`
- [ ] Generate password file: `/opt/.gnode/valkey_client_your_site.password`
- [ ] Update CacheManager.yaml with new credentials
- [ ] Test keyspace isolation

### ⏳ Pending (gNode Stream Debugging)
- [ ] Fix consumer group issue (see SESSION_HANDOFF)
- [ ] Verify FormatManager works via gNode
- [ ] Verify TemplateRenderer works via gNode
- [ ] Test 3D cube face rendering

### ⏳ Pending (Operational)
- [ ] Test ErrorManager file logging: `~/gh/gCore/logs/gcube-error.log`
- [ ] Verify log rotation works (5MB limit)
- [ ] Test email notifications (trigger CRITICAL error)
- [ ] Verify ValKey error logging: `{your_site}:error:*`

---

## 🧪 Quick Tests

### Test 1: Verify Configs Load
```bash
cd ~/gh/gCube
php -r "var_dump(yaml_parse_file('config/managers/ErrorManager.yaml'));"
```

### Test 2: Check gCore Logs Directory
```bash
ls -la ~/gh/gCore/logs/
touch ~/gh/gCore/logs/test.log
# Should succeed without permission errors
```

### Test 3: Test ErrorManager Logging
```php
// In WordPress or test script
$gCore = \gCore\Modules\Core\gCore::getInstance();
$errorManager = $gCore->getService('error_manager');
$errorManager->logError('TEST', 'This is a test error', ['context' => 'manual test']);

// Check file: tail -f ~/gh/gCore/logs/gcube-error.log
// Check ValKey: valkey-cli KEYS "{your_site}:error:*"
```

### Test 4: Verify Multi-Tenant Isolation
```bash
# Check that keys use proper prefixes
valkey-cli --user gnode_daemon --pass "..." KEYS "{your_site}:*"

# Should see:
# {your_site}:error:*
# {your_site}:cache:*
```

---

## 📊 Configuration Matrix

| Manager | Implementation | ValKey | File Logging | gNode | Email Alerts |
|---------|---------------|--------|--------------|-----|--------------|
| SecurityManager | Standalone | ✅ | ❌ | ❌ | ✅ |
| ErrorManager | Standalone | ✅ | ✅ | ❌ | ✅ |
| CacheManager | Standalone | ✅ | ❌ | ❌ | ❌ |
| APIManager | Standalone | ✅ (cache) | ❌ | ❌ | ❌ |
| FormatManager | **gNode** | ✅ | ❌ | ✅ | ❌ |
| TemplateRenderer | **gNode** | ✅ | ❌ | ✅ | ❌ |

---

## 🚀 Next Steps

1. **Immediate:** Verify config files load without errors
2. **High:** Test ErrorManager dual logging (file + ValKey)
3. **High:** Debug gNode stream processing (FormatManager, TemplateRenderer)
4. **Medium:** Create per-site ValKey ACL user (Phase 4)
5. **Low:** Test email notifications

---

## 📚 Documentation Links

- **Detailed Docs:** [MANAGER_CONFIGS_README.md](./MANAGER_CONFIGS_README.md)

---

**Status:** ✅ All manager configurations created and documented  
**Last Updated:** 2025-11-05  
**Ready for:** Testing + Deployment
