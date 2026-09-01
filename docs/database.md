# Database

The bundled `database/kiyosaki.sqlite` is a committed, integrity-checked
snapshot with two lookup indexes.

Draw, prize and payment values were imported from the public results service
operated by Državna lutrija Srbije. Kiyosaki is not affiliated with or endorsed
by the operator. See the repository's `THIRD_PARTY_NOTICES.md` for the data
source and rights notice.

The database contains:

- 1,477 draws spanning 2012 through draw 21 of 2026;
- 140 combinations, including combinations awaiting draw 22 of 2026;
- valid JSON in every numbers field;
- exactly seven numbers in every draw and combination;
- a successful SQLite `PRAGMA integrity_check`.

`draws` stores draw identity, date, seven numbers, prize breakdown and payment
metadata. `combinations` stores seven-number selections keyed to the draw and
year they target. Arrays and nested metadata use JSON text for compatibility
with the source database.

The bundled snapshot ends on 13 March 2026. Use `kiyosaki scrape` to add later
results; do not infer that the committed file is current merely from its year.
