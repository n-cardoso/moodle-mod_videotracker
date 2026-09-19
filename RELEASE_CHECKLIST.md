# Video Tracker Starter release checklist

Use this checklist before every Moodle Marketplace release.

## Versioning

- Confirm the new Starter `$plugin->version` is greater than the previous
  Starter build accepted by Moodle Marketplace.
- Confirm the current Pro `$plugin->version` is greater than the proposed
  Starter build. If it is not, release a newer Pro build first.
- Update `$plugin->release`, `RELEASE_NOTES.md`, `STABLE_VERSION.md`, and
  `EDITION.md` together.
- Add a matching `upgrade_mod_savepoint()` in `db/upgrade.php`.

## Edition boundary

- Confirm there are no licence, trial, entitlement, customer-account, or
  remote-update flows in Starter.
- Confirm CSV export, subtitles, learning objectives, and playback policies
  remain unavailable in Starter.
- Preserve the compatible database columns documented in `EDITION.md` so an
  in-place upgrade from Starter to Pro remains safe.
- Keep Starter useful on its own; do not add forced promotional screens.

## Quality

- Run Moodle Plugin CI and require PHP lint, Code Checker, PHPDoc, metadata,
  savepoint, Mustache, and AMD build checks to pass.
- Verify every literal `get_string()` identifier for `videotracker` exists in
  `lang/en/videotracker.php`.
- Test creation and editing with uploaded, YouTube, Vimeo, and direct videos.
- Test learner progress, resume, completion, grade creation, individual reset,
  bulk reset, backup, and restore.
- Test an upgrade from the previous Marketplace build on Moodle 4.5 or later.
- Confirm the administrator gradebook repair page works without per-item course
  queries.

## Package

- Build a ZIP containing exactly one top-level `videotracker` directory.
- Exclude `.git`, `.DS_Store`, `__MACOSX`, temporary files, and development
  caches.
- Confirm `LICENSE` is present at the plugin root.
- Install the final ZIP on a clean Moodle test site before submission.
