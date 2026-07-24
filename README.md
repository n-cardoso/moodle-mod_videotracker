# Video Tracker

Video Tracker is a Moodle activity module for adding a video to a course,
tracking each learner's viewing progress, resuming playback, and completing
the activity after a configured percentage has been viewed.

This is the permanent Free edition for Moodle Marketplace. It does not require
a licence key, trial registration, external account, or paid service.

## Requirements

- Moodle 4.5 through 5.2
- A supported PHP version for the selected Moodle release
- Moodle cron configured normally

## Free features

- Moodle-hosted video uploads
- YouTube embeds using YouTube's privacy-enhanced domain
- Public and unlisted Vimeo videos
- Direct HTML5 video URLs
- Poster images and configurable embed ratios
- Per-learner progress tracking
- Resume from the last recorded position
- Percentage-based activity completion
- Gradebook integration
- Teacher engagement report with search, status and group filters
- Individual and bulk progress reset
- Moodle backup and restore
- Moodle Privacy API support
- Moodle App integration

## Installation

1. Download the release ZIP.
2. In Moodle, go to **Site administration > Plugins > Install plugins**.
3. Upload the ZIP and complete the standard installation process.

Alternatively, extract the `videotracker` directory into `mod/videotracker`
and visit **Site administration > Notifications**.

## Video sources and privacy

Uploaded video and learner progress remain in Moodle. When a teacher selects
YouTube, Vimeo, or another external URL, the learner's browser connects to that
external provider. Site administrators should reflect those providers in
their privacy documentation where applicable.

Video Tracker Free does not send licence, customer, learner, or activity data
to LearnPlug.

## Upgrade to Video Tracker Pro

Video Tracker Pro is an optional, separately distributed edition with CSV
report export, additional analytics, subtitle workflows, objectives, playback
policies, updates, and commercial support. Pro uses the same Moodle component
and can upgrade the Free edition in place. Existing activities and progress
are preserved.

See the project documentation for edition and upgrade information:
https://loop2learning.pt/

## Support and issues

Use the public project discussions for bugs and support affecting this Free
edition:

https://github.com/n-cardoso/moodle-mod_videotracker/discussions

## Licence

GNU GPL v3 or later.
