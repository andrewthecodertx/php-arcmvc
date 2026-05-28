# ArcMVC Audit Notes

## Architecture & Design
- **Service Locator / DI**: The `Application` class acts as a rudimentary DI container. It lacks true dependency injection (most things are resolved via `make` or stored as singletons), making it hard to unit test without booting the whole app.
- **Singleton Pattern**: `Application::getInstance()` is used, which creates a global state dependency throughout the framework.
- **Response Handling**: `normalizeResponse` in `Router` is a good touch, allowing controllers to return strings or arrays.

## Potential Bugs & Stability
- **HTTP Method Handling**: `Router::dispatch` treats `HEAD` as `GET`, but it doesn't properly handle the response body for `HEAD` (it will send the body if the GET route provides one, which is technically allowed but often wasteful).
- **Route Compilation**: `preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path)` is simple but vulnerable if the path contains characters that break the regex delimiter `#`.
- **Error Handling**: `Application::handle` catches `\Throwable` and passes it to the `Handler`. If the `Handler` itself throws, you get a white screen (unhandled exception).
- **Input Parsing**: `Request::createFromGlobals` uses `json_decode($rawInput, true)`. If the JSON is malformed, it defaults to `[]`, hiding the syntax error from the developer.

## Security
- **Database**: `Connection` uses PDO with `ATTR_EMULATE_PREPARES => false`, which is the secure way to prevent SQL Injection.
- **View Rendering**: The `Renderer` uses `.phtml` files. There's no mention of auto-escaping in the `Template` class (which I haven't read yet, but the `Renderer` doesn't seem to pass a filter), potentially leaving it open to XSS.
- **Routing**: Regex anchors `^` and `$` are used, preventing partial match bypasses.

## Performance & Maintainability
- **Dynamic Instantiation**: `new $concrete()` and `new $controllerClass()` are used throughout. This prevents the use of interfaces for controllers/bindings unless the binding is a closure.
- **Middleware Pipeline**: The closure-based pipeline in `Application::runMiddleware` is elegant and efficient.
- **Config**: `glob($path . '/*.php')` is used to load config files. This is fine for small apps but could be slow if the config directory grows.

## Recommendations
1. **DI Container**: Transition from a basic map to a proper PSR-11 container.
2. **Response Objects**: Move the `send()` logic out of `Response` and into a `SapiEmitter` to decouple the response object from the global output buffer.
3. **XSS Protection**: Implement an automatic escaping mechanism in the View layer.
4. **Request Validation**: The `Validator` exists but isn't integrated into the `Request` or `Router` flow (e.g., via middleware).
