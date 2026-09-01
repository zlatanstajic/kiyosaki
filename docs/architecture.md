# Architecture

Kiyosaki separates public use cases, infrastructure and command-line concerns.

## Core

`src/Core` is the public, infrastructure-light surface:

- immutable `DrawRecord` and `StoredCombination` domain values;
- bounded `CombinationGenerator`;
- `FrequencyAnalyzer` and `SuccessRateAnalyzer`;
- `DrawImporter`, which coordinates ports supplied by the system layer.

## System

`src/System` owns volatile details:

- `Database` is the single owner of SQLite location, schema and queries;
- `ResultsClient` isolates HTTP retrieval and year-level response caching;
- `LotteryResultsParser` owns the current official JSON contract.

`src/Console/Application` translates command options and exceptions into text
and exit codes.

This separation makes the generator and analyzers deterministic to test, keeps
network access replaceable, and prevents tests from writing to committed data.
