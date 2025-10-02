# Changelog

## Unreleased

### Added
- `VA_CRM_Service` as the central orchestrator for CRM repositories, including helpers such as `ensure_client_for_user()`.
- `VA_CRM_Form_Repository` and related interface to handle entry types and form fields.

### Changed
- REST API handler, appointment manager, and shortcodes now consume `VA_CRM_Service` instead of reaching repositories or the legacy database facade directly.
- Plugin activation hooks call the CRM service for table creation.

### Deprecated
- `Veterinalia_CRM_Database` is now a deprecated shim that redirects calls to `VA_CRM_Service` and triggers `_doing_it_wrong()` notices.
