# Security Policy

## Reporting a Vulnerability

Do not report security vulnerabilities through public GitHub issues.

Email security concerns to: **andrewerwin73@gmail.com**

Include:
- Description of the vulnerability
- Steps to reproduce
- Affected versions
- Potential impact

## Response Time

You can expect an initial response within 48 hours. We will keep you informed of progress toward a fix.

## Disclosure

We request that you give us a reasonable timeframe to address the vulnerability before any public disclosure. We will credit researchers in our changelog unless you request otherwise.

## Known Security Considerations

- Always set `APP_DEBUG=false` in production
- Use the `SecurityMiddleware` and `CsrfMiddleware` in production applications
- Validate and sanitize all user input before use
- Keep dependencies up to date