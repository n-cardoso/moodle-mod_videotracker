# Video Tracker Free changes

## 1.2 — 2026-07-24

- Adds Vimeo, YouTube, direct HTML5 URL, and Moodle-hosted video support.
- Tracks learner viewing progress, resume position, grades, and completion.
- Adds teacher engagement reports, filters, and progress reset tools.
- Adds administrator gradebook recovery with direct links to broken formulas.
- Adds Moodle App, Privacy API, backup, and restore support.
- Removes commercial licensing, trials, CSV export, and premium-only controls.

## 1.0.11 — 2026-07-24

- Shows administrators affected courses and direct Edit calculation links when
  a broken formula cannot be repaired automatically.
- Includes courses containing deleted Video Tracker grade items in diagnostics.

## 1.0.10 — 2026-07-24

- Repairs missing Course total formula references when Moodle's grade history
  proves that the referenced item was an earlier Video Tracker grade item.

## 1.0.9 — 2026-07-24

- Adds an administrator web action for repairing Video Tracker grades without
  requiring terminal or SSH access.

## 1.0.8 — 2026-07-24

- Moves gradebook repair to a post-upgrade ad-hoc task because Moodle does not
  permit grade APIs to load course-module information during plugin upgrades.

## 1.0.7 — 2026-07-24

- Republishes all Video Tracker source grades during upgrade before regrading.
- Forces Moodle to rebuild the complete course grade dependency tree.

## 1.0.6 — 2026-07-24

- Clears reset grades through `grade_update()` before the full course regrade,
  avoiding persistent dirty totals caused by deleting grade records.

## 1.0.5 — 2026-07-24

- Uses a full synchronous course regrade after individual grade deletion.
  Moodle cannot use its per-user fast regrade once the course total is dirty.

## 1.0.4 — 2026-07-24

- Completes Moodle gradebook regrading immediately after deleting reset grades,
  preventing the `gradesneedregrading` error in grade reports.

## 1.0.3 — 2026-07-24

- Vimeo completion now treats a genuine ended event near the video duration as
  100%, allowing a 100% completion threshold to complete reliably.
- Reset now deletes the learner grade through Moodle's grade API and refreshes
  completion, so Receive a grade returns to incomplete.

## 1.0.2 — 2026-07-24

- Added Moodle grade report integration through `grade.php`, enabling the
  Grade analysis action for Video Tracker grade items.
- Grade analysis opens the selected learner's filtered engagement report.

## 1.0.1 — 2026-07-24

- Incremented the Moodle plugin build so updated language strings are loaded
  when upgrading an existing Free test installation.
- Added the complete GNU GPL v3 text as the root-level `LICENSE` file required
  for Marketplace licensing review.

## 1.0.0 — 2026-07-24

- First permanent Free edition for Moodle Marketplace.
- Removed commercial licence, trial, activation and remote-update flows.
- Removed AI subtitle processing, learning objectives and advanced playback
  restrictions.
- Retained video playback, progress, resume, completion, grading, reporting,
  reset tools, backup/restore, privacy support and Moodle App integration.
- Reserved CSV report export for the Pro edition.
- Added Vimeo playback and progress tracking, including unlisted Vimeo links.
- Moved remaining user-visible report placeholders and percentages into the
  Moodle language pack.
