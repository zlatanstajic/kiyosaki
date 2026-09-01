# Contributing

Thank you for contributing to Kiyosaki. By submitting a contribution, you
agree that it may be distributed under the project's
[MIT License](LICENSE.md).

1. Open an issue and branch from `master` as
   `issues/<number>-<kebab-case-description>`.
2. Use PHP 8.5 and install dependencies with `composer install`.
3. Keep public domain and use-case code in `src/Core/`; keep database, HTTP and
   parsing infrastructure in `src/System/`; keep argument/output handling in
   `src/Console/`.
4. Add `declare(strict_types=1)`, native property/parameter/return types and
   precise PHPDoc collection shapes. Validate invalid states at the boundary.
5. Treat `database/kiyosaki.sqlite` as committed data. Never truncate or
   replace it to make a test pass. Tests and experiments must use
   `KIYOSAKI_DATABASE_PATH` or a temporary database.
6. Add tests for happy and failure paths. Network requests and the committed
   database must remain isolated from tests that write.
7. Update the README and relevant page under `docs/` when behavior, commands,
   configuration, schema or workflow changes.
8. Run the complete gate before opening a pull request:

   ```bash
   composer run check
   sphinx-build --fail-on-warning --builder html docs docs/_build/html
   ```

`composer run check` runs Pint, Peck, Rector, PHPStan level 8 and Pest with an
80% line-coverage minimum. `composer install` sets `core.hooksPath` to the
committed `.githooks` directory so the same check runs before commits.

Do not stage or commit generated coverage and documentation builds. Do not add
secrets or real `.env` files. Do not claim that generated combinations predict
random lottery results.
