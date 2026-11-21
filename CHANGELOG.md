# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

### Planned
- Route caching improvements
- Additional routing algorithms
- Route rate limiting support

---

## [2.0.7] - 2026-11-21

### Fixed
- Declare nullability for callbacks and default-null parameters to avoid PHP 8.4 deprecations.
- Skip an omitted callback when creating a child router.

### Tests
- Cover child routers without callbacks and fail PHPUnit on deprecations.

---

## [2.0.6] - 2026-11-20

### Changed
- Support Triangle Engine `^8.1 || ^9.0`, keeping Engine 8 compatibility while allowing Engine 9.

### Added
- Add route regression coverage for the Engine 9 compatibility line.

### Compatibility
- No Router runtime API changes. PHP `^8.1` remains required.

---

## [1.0.0] - 2024-07-29 - LTS Release

### Added
- Initial LTS release
- Full documentation
- Multiple routing algorithms (GroupCountBased, CharCountBased, MarkBased)
- Route collection system
- Route parameter binding
- Resource routing
- Route middleware support
- Route naming
- Route groups

### Changed
- Improved route matching performance
- Enhanced route parameter extraction

### Fixed
- Fixed route caching issues
- Improved route parameter validation
