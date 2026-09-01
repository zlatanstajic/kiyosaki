# Command-line guide

## Inspect

```bash
php bin/kiyosaki database:stats
```

## Generate

```bash
php bin/kiyosaki generate --combinations 5 \
  --disable-most 3 --enable-least 2 --favorites 7,13,21
```

Generated combinations contain seven unique values from 1 through 39. The
generator rejects prior draws, duplicates, runs of three consecutive numbers
and arithmetic patterns with three equal adjacent differences. Generation is
iterative and bounded; impossible rules fail instead of recursing forever.

`--draw` and `--year` explicitly select the result the combinations will be
scored against. Without them, the target is the draw after the latest stored
result.

## Analyse

```bash
php bin/kiyosaki analyse --year 2026
```

Rates use all combinations with an available target draw as the denominator.
Combinations whose target result is not stored are reported as pending.

## Scrape

```bash
php bin/kiyosaki scrape --year 2026 --start 22 --end 30
```

The scraper reads the official `lutrija.rs/api/results` JSON service. Existing
draws are skipped before data is inserted, and one year response is cached
during a run. An API contract change or network error returns exit code 1 and
leaves already inserted rows intact.
