# CHANGELOG_AI.md - Work & Change History

## [Unreleased] - 2026-10-03

### Fixed
- **Admin Settings Tab Routing**: Resolved "Sorry, you are not allowed to access this page." error when switching settings tabs (`behaviour`, `display`, `coupons_sales`, `rules`, `license`, `support`).
  - Added dynamic slug detection in `SettingsPage.php` to maintain current `page` context (`wpcalibrate` vs `wpcalibrate-tiered-pricing`).
  - Registered `wpcalibrate-tiered-pricing` as a valid submenu target across all registration branches in `AdminMenu.php`.
  - Added explicit form action URL preserving both current `page` and active `tab`.

### Added
- Automated FTP deployment pipeline (`sync-ftp.ps1`, `deploy.bat`, `watch.bat`).
- Credential management with Git protection (`.gitignore`, `ftp-config.json`).
- Core AI developer documentation: `CLAUDE.md`, `PROJECT_MEMORY.md`, `TROUBLESHOOTING.md`, `CHANGELOG_AI.md`, `TODO_AI.md`.
