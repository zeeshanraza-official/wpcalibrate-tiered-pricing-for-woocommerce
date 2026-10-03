# TODO_AI.md - Active Task Checklist

## Active Issues
- [x] **Bug 1**: Fix admin settings tabs throwing "Sorry, you are not allowed to access this page."
  - [x] Identify root cause in `AdminMenu.php` and `SettingsPage.php`.
  - [x] Implement fix in `src/Admin/AdminMenu.php`.
  - [x] Implement fix in `src/Admin/SettingsPage.php`.
  - [x] Update mirror files in `wpcalibrate-tiered-pricing-for-woocommerce/`.
  - [x] Deploy fixes to live testing FTP server (`169.58.213.1`).
  - [x] Rebuild distribution zip package.
  - [x] Update documentation.

- [x] **GitHub Repository & In-Dashboard Auto-Updater**:
  - [x] Build in-dashboard GitHub updater service (`src/Updater/GitHubUpdater.php`).
  - [x] Connect `GitHubUpdater` into `src/Plugin.php`.
  - [x] Create comprehensive `README.md` with About, Features, Docs, and Changelog.
  - [x] Add GPL-2.0 `LICENSE`.
  - [x] Configure `.gitignore` to protect sensitive FTP credentials.
  - [x] Create public GitHub repository `zeeshanraza-official/wpcalibrate-tiered-pricing-for-woocommerce`.
  - [x] Push all plugin files to `main` branch.
  - [x] Publish GitHub release `v1.0.1` with downloadable asset zip.
  - [x] Upload all changes to live testing FTP server (`169.58.213.1`).
