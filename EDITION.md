# Video Tracker Starter edition policy

Video Tracker Starter and Video Tracker Pro use the same Moodle component,
`mod_videotracker`. They are editions of one plugin, not two plugins that can be
installed side by side.

## Starter feature set

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

Pro is the development source. A Starter release is cut deliberately from a
tested Pro release by applying the Starter feature policy. Shared security and
compatibility fixes are backported when a Starter release is prepared. Starter
has a deliberately limited roadmap focused on reliability, compatibility,
accessibility, and core teacher and learner usability.

The current Pro build must always have a higher Moodle version number than the
current Starter build. The 1.2.1 Starter baseline is:

- Starter: `2026091800`
- Pro: `2026091900`

This ordering allows Moodle to upgrade Starter to Pro in place. The Starter install
schema intentionally retains compatible instance columns used by Pro, even
though Starter does not expose or act on those settings. Removing those columns
would make an in-place upgrade fragile.

The Marketplace package contains no license key, trial, remote entitlement, or
forced promotional flow. The README may identify the separately available Pro
edition, but Starter remains fully functional within its documented feature set.
