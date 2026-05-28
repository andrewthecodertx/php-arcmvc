# Arc

A lightweight, modern PHP MVC framework. Small core, batteries included, built for PHP 8.4+.

**Full documentation → [andrewthecoder.com/projects/arcmvc](https://andrewthecoder.com/projects/arcmvc)**

## Principles

- No hidden magic. Request flow is traceable from `public/index.php` to response.
- Every subsystem is replaceable.
- Secure defaults everywhere.
- Fast startup, low memory by default.
- One canonical way to do common things.
- First-party modules for the common website stack, each optional and independently replaceable.

## Requirements

- PHP 8.4+
- PDO extension (for database features)
- Composer

## Quick Start

```bash
composer require andrewthecoder/arcmvc
cp -r vendor/andrewthecoder/arcmvc/skeleton/* .
arc serve
```

Visit [http://localhost:8080](http://localhost:8080)

## Learn More

Routing, controllers, views, middleware, database, validation, console commands, configuration — all documented at:

**[andrewthecoder.com/projects/arcmvc](https://andrewthecoder.com/projects/arcmvc)**

## License

MIT