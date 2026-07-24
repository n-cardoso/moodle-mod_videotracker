# Video Tracker Free edition policy

Video Tracker Free and Video Tracker Pro use the same Moodle component,
`mod_videotracker`. They are editions of one plugin, not two plugins that can be
installed side by side.

## Free feature set

- Uploaded Moodle video files
- YouTube videos
- Public and unlisted Vimeo videos
- Direct external video URLs
- Per-user watch progress and resume position
- Configurable completion percentage
- Moodle activity completion and grade integration
- On-screen teacher progress report
- Moodle App activity support

## Pro-only feature set

- Licensing, trials, and entitlement checks
- Subtitle management and AI subtitle generation
- Learning objectives
- Playback-rate limits and seek restrictions
- CSV report export
- Premium administration and scheduled licensing tasks
- Customer update and support services

## Maintenance rule

Pro is the development source. A Free release is cut deliberately from a tested
Pro release by applying the Free feature policy. Shared security and
compatibility fixes are backported when a Free release is prepared; the Free
edition does not maintain an independent feature roadmap.

Every matching Pro build must have a higher Moodle version number than its Free
build. The first pair is:

- Free: `2026072402`
- Pro: `2026072403`

This ordering allows Moodle to upgrade Free to Pro in place. The Free install
schema intentionally retains compatible instance columns used by Pro, even
though Free does not expose or act on those settings. Removing those columns
would make an in-place upgrade fragile.

The Marketplace package contains no license key, trial, remote entitlement, or
forced promotional flow. The README may identify the separately available Pro
edition, but Free remains fully functional within its documented feature set.
