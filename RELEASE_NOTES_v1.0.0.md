# LearnPlug Video Tracker - Release Notes v1.0.0

- Release: `1.0.0`
- Build: `2026040303`
- Compatibility: Moodle `4.5+`
- Component: `mod_videotracker`

## Release scope

This version marks the official start of the stable `v1.0` line for the Video Tracker Moodle activity plugin.

## Reviewer follow-up in this build

- License validation no longer disposes Moodle's global DB handle after remote HTTP calls, preventing `mysqli object is already closed` and follow-on `is_temptable() on null` failures during `Validate now` on affected hosts.
- License validation responses now preserve explicit `site_activated` and `activation_allowed` flags from the WordPress server, so Moodle correctly shows `Activation required` and keeps premium features locked until the current site is actually activated.
- Uninstall cleanup now removes `videotracker` gradebook rows directly before Moodle core runs generic module-grade cleanup, avoiding repeated "The instance of this module does not exist" debugging output when uninstalling the plugin from sites with historical grade items.
- YouTube activities now render a direct iframe player in deactivated/restricted mode so playback remains available when premium tracking is disabled.
- Fixed low-contrast text in premium-status badges and primary license action buttons so alerts and actions remain readable on Boost/Moodle standard palettes.
- License status panels on activity/report pages now use Moodle-standard alert styling instead of custom state gradients, improving consistency with Boost and core UI patterns.
- Final reviewer-follow-up packaging build with accessibility, language, privacy, capability, event, DML, CSRF, boilerplate, and admin-AMD cleanup consolidated for resubmission.
- Final Moodle Plugins reviewer cleanup for language-string ordering.
- `tracker.js` return-path cleanup to remove remaining ESLint `consistent-return` warnings.
- `amd/build/*.min.js` refreshed to match the current `amd/src` sources.
- Mobile app mustache template docs normalized so the `@template` example context is detected correctly.
- Automatic license refresh on admin access is now skipped during upgrade/plugin-management contexts and only runs on the actual Video Tracker license settings page, avoiding unsafe config writes while Moodle builds admin trees.
- License requests now send explicit `site_url`, `siteurl`, and `moodle_url` aliases so the WordPress license server can identify the calling Moodle URL more reliably in API logs.
- Language-string ordering refreshed again to clear the remaining Moodle Plugins reviewer PHPCS warnings in `lang/en/videotracker.php`.
- Build number bumped to create a clean installable package from the current reviewer-ready codebase.
- AMD build files were regenerated so `amd/build/*.min.js` match the current `amd/src` sources and no longer trigger Moodle Plugins `grunt changes`.
- Final PHPCS follow-up for `foreach` spacing and language-key ordering.
- Final language-string ordering adjustment for `licensegetstartedwebsite`.
- Final PHPCS cleanup for reviewer-reported multiline `foreach` formatting and key ordering.
- Restored the last known-good AMD build files to recover stable YouTube progress persistence in runtime usage.
- AMD build artifacts regenerated into true minified Moodle AMD output with source maps, ready for controlled runtime retest.

## Functionalities included in v1.0.0

### 1) Activity creation and video delivery

- New Moodle activity type: **Video Tracker**.
- Supports uploaded Moodle-hosted video files.
- Supports YouTube URLs.
- Supports external direct video URLs (for example MP4/WebM/HLS).
- Optional poster/preview image before playback starts.
- Configurable embed ratio for external providers (`16:9`, `21:9`, `4:3`, `1:1`).

### 2) Playback controls and anti-skip behavior

- Fast-forward restriction mode (anti-skip).
- Optional browser-level hint to hide download control (`controlslist=nodownload`).
- Optional picture-in-picture disable.
- Optional right-click context menu disable.
- Maximum playback rate limit (`1.0x`, `1.25x`, `1.5x`, `2.0x`, or no limit).
- Server-side sanity checks for progress packets to reduce seek/skip abuse.

### 3) Learner tracking engine

- Real-time progress updates from player to Moodle via secure external services.
- Tracks percent watched per learner.
- Tracks cumulative watched time.
- Tracks last resume position.
- Tracks playback session sequence and timing metadata.
- Resume playback support from saved position.
- Monotonic progress logic to prevent regressions from stale/out-of-order events.

### 4) Learning objectives

- Up to 3 configurable objectives per activity.
- Objectives can be marked by learners after watching.
- Completion can require minimum watched percentage.
- Completion can require all configured objectives to be checked.

### 5) Gradebook and completion integration

- Fixed grade scale (`0-100`) mapped to watched percentage.
- Grade item auto-created/updated in Moodle gradebook.
- Grade-to-pass and completion threshold integration.
- Custom completion rule support for percentage watched.
- Immediate completion refresh logic after progress/objective updates.

### 6) Teacher report and operations

- Activity report page with learner engagement data.
- Status filters (`all`, `completed`, `in progress`, `not started`).
- Search by learner fields.
- CSV export.
- Per-learner progress reset (with confirmation and acknowledgement).
- Bulk reset for filtered learners.
- Grade reset synchronization when progress is reset.

### 7) Mobile app support

- Moodle App handler integration for Video Tracker activities.
- Mobile-compatible playback context and progress submission.
- Support for provider-specific behavior (HTML5, YouTube, Vimeo).
- Mobile fallback/open-in-activity flow for more reliable playback tracking.

### 8) Commercial licensing integration (server)

- Admin license settings panel in Moodle.
- License actions for activate, validate, and deactivate.
- Periodic validation via scheduled task (cron).
- Update-check flow with latest version metadata.
- License snapshot includes current status, runtime state, license type, activations, expiry, and grace window.
- Site-specific activation enforcement so a single-site license must be activated for the current Moodle host, not merely validated as a valid key.
- Failed activation attempts now persist a non-activated site state locally, and the Moodle admin UI shows "Activation required" instead of a misleading green active state for a second host.
- Diagnostics section with recent license error logs.
- License admin UX simplified for production use: activation is shown first when the site still needs a license, the main form now focuses on license key + billing email, product slug is tucked behind an optional disclosure, advanced troubleshooting is collapsed, and technical diagnostics stay separated from the primary activation flow.
- License admin follow-up polish: advanced troubleshooting copy now matches the support workflow wording, and status/type/admin activation badges use stronger contrast for clearer readability in Moodle admin themes.
- Missing local `licensetype` values are now backfilled automatically from the WordPress license server on admin-page refresh, preventing paid licenses from showing as `Not available` after stale local config.
- Final language-pack ordering cleanup applied so the release baseline is clean for Moodle Code-checker review.
- Moodle admin plugin pages no longer trigger a late CSS loading error; license settings styling is now rendered safely inline for admin contexts.

### 9) License runtime modes and feature gating

- Runtime mode `premium` for active paid/trial licenses.
- Runtime mode `grace` for temporary offline tolerance after successful validation.
- Runtime mode `restricted_demo` when premium features are locked.
- In restricted demo, learner video access remains available.
- In restricted demo, premium tracking, reports, objectives, completion enforcement, and advanced playback controls are restricted.
- Teacher/admin notices clearly indicate locked vs available capabilities.

### 10) Security, privacy, and reliability

- Capability checks for view, report access, and reset operations.
- Input sanitization across form and API payload handling.
- Secure pluginfile access checks for video/poster assets.
- Optional signed license requests (HMAC headers) with API secret.
- Local audit logging for remote license calls.
- Moodle privacy API metadata declaration.
- Moodle privacy API user data export.
- Moodle privacy API user/context data deletion support.
- Backup/restore activity support for core activity data.

### 11) Maintenance and data lifecycle

- Uninstall cleanup for plugin data.
- Upgrade path with incremental DB upgrade steps and savepoints.
- Grade-item cleanup hardening for orphaned instances.
- Reset-progress synchronization hardening: when an admin resets a learner, local browser progress cache is invalidated and resume starts from zero, preventing stale client progress from reappearing.
- Progress-report consistency hardening: when server progress exists, learner UI now prioritizes server snapshot and server-acknowledged percentage to prevent local cache drift from showing higher progress than the engagement report.
- Re-entry tracking hardening for anti-seek mode: server-side progress validation now anchors against monotonic `lastpos` as well as `lastct`, preventing resumed sessions from being incorrectly clamped and stuck in engagement reports after reopening the activity.
- Report status consistency fix: when no objective gates are configured, engagement report status now treats learners at/above the required percentage as completed (including CSV and status filters), preventing false "In progress" labels.
- Coding standards cleanup for reviewer readiness: Moodle GPL/file headers were added across PHP/JS/CSS files, missing function/method docblocks were completed in the main procedural and privacy/external layers, and long lines were wrapped to reduce Code-checker noise without changing plugin behaviour.
- Reviewer-facing standards pass extended for Code-checker: language strings were reordered and comment-free cleaned, mixed PHP/HTML output in `view.php` was refactored into a single PHP render path, flagged `else if`/docblock/member-comment/style issues were normalized, and previously empty catch blocks now fail safely without tripping reviewer rules.
- Moodle review follow-up cleanup: additional PHPCS/phpdoc fixes were applied across completion, external services, reporting, settings, and local licensing classes; the license settings helper wrappers were split into dedicated class files; CSS formatting in `styles.css` was normalized for stylelint; and `amd/src/tracker.js` received a non-functional reviewer cleanup pass focused on braces, explicit catch handling, and readability without changing the current production tracking runtime.
- Mobile template review cleanup: the Moodle App mustache template now includes the required `@template` example context, avoids rendering an empty heading during validation, and replaces the inline `<style>` block with safe inline element styling so `grunt` / mobile template validation no longer fails on HTML structure rules.
- License acquisition UX improvement: when no active license is detected, the Moodle admin license screen now shows a clear “Need a license?” panel with a direct website CTA for starting a full-feature 14-day trial or buying a license, followed by the existing activation form for admins who already have a key.
- Restricted-demo playback fix: when the site has no valid license, learner playback now remains available without initializing the premium progress tracker on the activity page, preventing the unlicensed 2-second restart loop caused by server-side zero-progress responses being interpreted as a full reset.
- Local expiry enforcement fix: cached `active` license states no longer keep premium features enabled past the stored `expires_at` timestamp, and the admin summary now shows an explicit expired state instead of a misleading green active badge.
- Language pack ordering cleanup: the new expiry-related string keys were reordered to satisfy Moodle Codechecker lang-file ordering rules without changing runtime behaviour.
- YouTube restricted-demo playback fix: unlicensed/expired YouTube activities now bootstrap through the same API-backed player path used by the premium runtime instead of a raw iframe fallback, preventing the recent embedded anti-bot/login prompt from appearing in normal playback.
- YouTube embed host hardening: all YouTube playback modes now instantiate the Iframe API player against `youtube-nocookie.com`, keeping runtime behavior aligned across premium and restricted states and avoiding the recent embedded login/anti-bot prompt.
- YouTube iframe binding hardening: the activity page now renders a `youtube-nocookie` embed iframe for every YouTube activity and attaches the Iframe API to that existing iframe instead of letting the API create a fresh embed, reducing divergence between licensed and unlicensed playback paths.

## Notes for administrators

- This release starts the official `v1.0` counting line.
- Existing sites upgrading from earlier builds remain compatible through the upgrade path.
