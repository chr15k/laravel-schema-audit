# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/) and this project adheres to [Semantic Versioning](https://semver.org/).

## [0.1.1] - 2026-08-08

### Changed
* Removed Laravel 10 support in favor of addressing compatability issues
* Updated README

## [0.1.0] - 2026-08-08

### Added

* Static reconstruction of Laravel database schemas from migration history.
* Detection of duplicate and redundant indexes.
* Detection of unindexed foreign keys with database-driver awareness.
* Detection of duplicate and dangling foreign keys.
* Detection of mismatched foreign key and referenced key types.
* Detection of invalid foreign key reference keys.
* Detection of missing primary keys.
* Support for table renames, column renames and drops, index drops, and foreign key drops.
* Support for common Laravel Schema Builder conventions and helpers.
* Static resolution of common table-name expressions, including configuration values, model methods, helper methods, and simple variables.
* Conservative handling of conditional migration logic with conditional findings.
* Configurable audit rules and support for custom application-specific rules.
* Styled terminal output and machine-readable JSON output.
* Schema-only output for inspecting the reconstructed schema.
* Configurable migration paths and database drivers.
* CI-friendly exit codes.
* Support for Laravel 10, 11, 12, and 13.
* Support for PHP 8.2+.
