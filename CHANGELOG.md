# Changelog

All notable changes to Arc are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Security
- **Critical**: Fixed XSS double-encoding in `Template::yield()` and `section()`. Content and named sections are now raw by default; use `e()` to escape user data
- **Critical**: Fixed SQL injection via column/table names in `Model`. All identifiers validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/`
- **Critical**: Added `CsrfMiddleware` with synchronizer token pattern, `SameSite=Strict` cookie, and `Template::csrfField()` helper
- **High**: Fixed open redirect vulnerability in `Controller::back()` — only relative paths allowed
- **High**: Added open redirect protection to `Response::redirect()` — external URLs rejected by default, `$allowExternal` opt-in flag
- **High**: Added `Content-Security-Policy` (default `default-src 'self'`) and `Strict-Transport-Security` headers to `SecurityMiddleware`
- **High**: Changed `.env.example` DB_USERNAME from `root` to `app_user`
- **High**: Added `RateLimitMiddleware` with in-memory store and `RateLimitStoreInterface` for custom backends
- **High**: Documented `extract()` risk in `Template::capture()`; added `getData()` and `allData()` for collision-free data access
- **High**: Added `Request::getFile()`, `validateFile()`, and `sanitizeFileName()` for safe file uploads

### Added
- `Arc\Http\Session` component with flash message support and `regenerate()` for session fixation prevention
- `Arc\Config\EnvLoader` for `.env` file parsing (no external dependency)
- `Arc\Database\DatabaseException` wrapping PDO errors to prevent information leakage
- `Connection::ping()` method for connection health checks
- `Application::resetInstance()` for test isolation
- Container auto-wiring via `ReflectionClass` for constructor dependency injection
- `Model::all()` now accepts `$limit` and `$offset` parameters (default: 1000/0)
- Router now resolves controllers and route middleware through the DI container

### Changed
- Router uses `Container` instead of `Application` reference (breaks circular dependency)
- Router throws `RouteNotFoundException` for 404s instead of returning a Response
- `Response::redirect()` rejects external URLs by default
- `Controller::back()` validates Referer as same-origin before redirecting
- `SecurityMiddleware` constructor now accepts `$csp` and `$hsts` parameters
- `ValidationException` message is now human-readable instead of raw JSON
- `Connection::selectOne()` uses strict `!== false` comparison instead of falsy `?:`
- Regex validation rule uses `~` delimiter and gracefully handles invalid patterns

### Fixed
- Fixed `SapiEmitter` missing import in `Application.php` (was resolving to wrong namespace)
- Fixed `Template::yield()` returning null for undefined named sections instead of default
- Fixed `Model::count()` validating column names when not `*`
- Added `psr/container` dependency (was missing, caused Application test failures)

### Tests
- Added 220+ tests across 22 test files covering all framework components