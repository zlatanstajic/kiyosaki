# Getting started

## Install

Kiyosaki requires PHP 8.5, Composer 2 and the cURL, JSON, PDO and PDO SQLite
extensions.

```bash
composer install
php bin/kiyosaki database:stats
```

The default database is `database/kiyosaki.sqlite`. To isolate experiments or
use an application-owned database, set an explicit path:

```bash
KIYOSAKI_DATABASE_PATH=/absolute/path/loto.sqlite php bin/kiyosaki database:stats
```

The schema is created automatically for a new file. Kiyosaki does not load a
`.env` file.

## Development

```bash
composer run check
sphinx-build --fail-on-warning --builder html docs docs/_build/html
```

The test suite uses generated temporary SQLite files for writes and verifies
the bundled database read-only by its record counts.
