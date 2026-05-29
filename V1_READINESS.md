# Arc v1.0 Readiness Assessment

**Date:** May 29, 2026
**Based on:** Full code review (38 issues) plus implementation work

---

## Summary

| Category | Done | Remaining |
|----------|------|-----------|
| Critical security | 4/4 | 0 |
| High security | 8/8 | 0 |
| Medium issues | 12/14 | 2 (deferred) |
| Low issues | 6/12 | 6 (deferred) |
| Tests | 240+ | integration tests |
| Documentation | comprehensive README, CHANGELOG, CONTRIBUTING, SECURITY | API docs inline |

**Verdict: 6 items stand between current state and v1.0.**

---

## Must-Have Before v1.0

### 1. Update skeleton app with security middleware stack

The skeleton still only registers `SecurityMiddleware`. It needs `CsrfMiddleware` and `.env` loading. A new user copying the skeleton gets no CSRF protection and no env loading.

**File:** `skeleton/bootstrap/app.php`

Current:
```php
$app = new Application(__DIR__ . '/../config', dirname(__DIR__));
$app->addMiddleware(SecurityMiddleware::class);
```

Should be:
```php
use Arc\Config\EnvLoader;
use Arc\Http\Middleware\SecurityMiddleware;
use Arc\Http\Middleware\CsrfMiddleware;

EnvLoader::load(dirname(__DIR__) . '/.env');

$app = new Application(__DIR__ . '/../config', dirname(__DIR__));
$app->addMiddleware(SecurityMiddleware::class);
$app->addMiddleware(new CsrfMiddleware());
```

### 2. Skeleton layout and forms need CSRF integration

The skeleton layout and view templates should demonstrate `csrfField()` usage so new projects have working forms out of the box.

### 3. Add a version constant

No version number exists anywhere. `composer.json` has no version field. A v1.0 release needs a version to reference.

**File:** `src/Application.php` (or a dedicated `Arc\Version` class):
```php
public const VERSION = '1.0.0';
```

And in `composer.json`:
```json
"version": "1.0.0"
```

### 4. Tag v1.0.0 in git

After the above changes are merged:
```bash
git tag -a v1.0.0 -m "Arc v1.0.0"
git push origin v1.0.0
```

### 5. Ensure the skeleton actually boots

Run the skeleton with `arc serve` and verify it renders the homepage without errors. The skeleton hasn't been tested against the current codebase since many breaking changes (Router no longer uses Application, container auto-wiring, etc.).

### 6. Smoke test the full request lifecycle

An integration test that boots Application, dispatches a request through middleware + router + controller + view, and asserts the response. This is the only completely untested path and would catch wiring mistakes.

---

## Should-Have (strongly recommended)

### 7. Console command tests

The `make:controller`, `make:model`, `serve`, `serve:stop`, and `routes` commands have zero tests. They generate files on disk and start processes. At minimum, test that `make:` commands produce valid PHP files.

### 8. Integration test for the full request lifecycle

One test that:
- Creates an Application with config
- Adds SecurityMiddleware + CsrfMiddleware
- Registers a route
- Dispatches a GET request and asserts 200
- Dispatches a POST without CSRF token and asserts 419

This catches wiring bugs that unit tests miss.

### 9. Add `.github/` issue/PR templates

Small touch that shows the project is set up for community contributions. Already have CONTRIBUTING.md and SECURITY.md.

---

## Nice-to-Have (can ship without)

| Item | Why deferrable |
|------|---------------|
| Route caching / radix tree (3.1) | Micro-frameworks are fine with linear scan under ~100 routes |
| Config caching (3.3) | Only matters under extreme load; v1.0 apps are small |
| Query builder (10.5) | `Model::query()` exists for raw SQL; builder is a v2 feature |
| PSR-7/PSR-15 adapters (2.7) | Intentional design choice; can add adapters later |
| Session management improvements (1.15) | Basic `Session` class exists; flash messages work |
| Logging abstraction (5.1) | `error_log()` in handler works; PSR-3 is a v2 concern |
| Environment-specific config (9.2) | Current `.env` + `EnvLoader` is sufficient for v1.0 |
| Production debug guard (5.2) | `APP_DEBUG=false` default is already safe |
| UUID/composite primary keys (10.4) | Edge case; `create()` returning int is standard AR |
| `posix_kill()` guard (10.1) | Only affects `arc serve:stop`; niche |

---

## What the code review fixed (38 issues total)

### Critical (4/4 fixed)
- 1.1 XSS double-encoding in Template::yield()
- 1.2 SQL injection via column names in Model::where()
- 1.3 SQL injection via table/column names in Model CRUD
- 1.4 No CSRF protection (added CsrfMiddleware)

### High (8/8 fixed)
- 1.5 Open redirect in Controller::back()
- 1.6 Open redirect in Response::redirect()
- 1.7 Missing Content-Security-Policy header
- 1.8 Missing HSTS header
- 1.9 DB_USERNAME=root in .env.example
- 1.10 No rate limiting (added RateLimitMiddleware)
- 1.11 extract() risk in Template (added getData/allData helpers)
- 1.12 No file upload validation (added getFile/validateFile/sanitizeFileName)

### Medium (12/14 fixed, 2 deferred)
- 1.13 SapiEmitter headers_sent() guard
- 1.14 Regex validation rule delimiter and error handling
- 1.15 No session management (added Session class)
- 2.1 Singleton + resetInstance()
- 2.2 Container auto-wiring
- 2.3 Router-Application circular dependency (injected Container)
- 3.4 Model::all() pagination
- 4.3 ValidationException human-readable messages
- 4.4 selectOne() strict null check
- 4.6 RouteNotFoundException actually used
- 5.3 DatabaseException wrapping PDO errors
- 9.1 .env file parsing (added EnvLoader)
- 2.4 Static global connection (deferred)
- 3.1 Linear regex route matching (deferred)

### Low (6/12 fixed, 6 deferred)
- 2.6 SapiEmitter import
- 3.5 Connection::ping()
- 4.5 Container PHPDoc @template
- 4.7 Application SapiEmitter reference
- 10.2 HTTP method override (added)
- 10.3 CORS middleware (added)
- 2.5 RoutesCommand standalone router (deferred)
- 2.7 PSR-7/PSR-15 compliance (deferred)
- 3.2 Middleware per-request instantiation (partially fixed)
- 3.3 Config caching (deferred)
- 4.1 ANSI codes (not a bug)
- 4.2 Config set() overwrite (deferred)
- 5.1 PSR-3 logging (deferred)

### Documentation (5/5 fixed)
- 7.1 README expanded with full API reference
- 7.2 PHPDoc on public methods
- 7.3 CHANGELOG, CONTRIBUTING, SECURITY added
- 7.4 Skeleton config files documented
- 7.5 Inline comments in dispatch() and runMiddleware()

---

## Test Coverage

- **240 tests, 365 assertions** across 24 test files
- New test files added for: Container, CsrfMiddleware, SecurityMiddleware, RateLimitMiddleware, Session, Model, Template, SapiEmitter, Request, Response, Controller, CorsMiddleware, EnvLoader
- **Gap:** Integration tests (full request lifecycle), console command tests