# Repository Instructions

## Before editing

- Read a file before changing it.
- Search every caller before changing a function, CLI contract or shared type.
- Read `README.md`, `CONTRIBUTING.md` and the relevant page under `docs/` before
  non-trivial behavior changes.
- Preserve unrelated user changes.

## Source ownership

- `src/Core/` owns public domain records and use cases.
- `src/System/` owns SQLite, network and JSON parsing infrastructure.
- `src/Console/` owns command-line parsing and output.
- `database/` is committed application data. Never replace it to make code or
  tests pass; write to a temporary path instead.
- `tests/` mirrors the source concerns and must not use the real network.
- `docs/` contains Sphinx/MyST documentation.

## PHP conventions

- Support PHP 8.5.
- Use strict types, native type declarations, precise collection PHPDoc and the
  default Laravel Pint style.
- Exceptions are the error contract for invalid library state; the CLI catches
  them and returns a non-zero exit code.
- Do not add runtime dependencies without explicit approval.

## Verification

Before handing off production changes, run:

```bash
composer run check
sphinx-build --fail-on-warning --builder html docs docs/_build/html
```

Keep coverage at or above 80% and documentation synchronized with behavior.

## Git and secrets

- Do not stage or commit changes unless explicitly asked.
- Do not discard unrelated changes.
- Never read or write `.env` files or expose secrets.
- Use environment variables for sensitive settings.
