# Changelog

All notable changes to `:package_name` will be documented in this file.

## v1.1.0 - 2026-06-25

### Added

- **Clearer failures** — when the acting user, a record, or a tenant can't be created, the real exception is now shown in the skip reason (e.g. `could not create an authorized user for panel [admin]: Could not verify the hashed value's configuration.`) instead of a generic "no authorized user" / "no factory" skip. (#4)

### Changed (CI / security)

- All workflow actions are pinned to full-length commit SHAs, workflows declare least-privilege `permissions`, and a `SECURITY.md` disclosure policy was added. (#5)

### Docs

- Why a runtime sweep beats AI-generated tests, and why it's not "AI vs. Canary".
- How to support non-standard auth (custom API/session guards) by binding your own `Requester`. (#3)
- Banner and a code-coverage badge.

**Full changelog:** https://github.com/Baspa/filament-canary/compare/v1.0.0...v1.1.0
