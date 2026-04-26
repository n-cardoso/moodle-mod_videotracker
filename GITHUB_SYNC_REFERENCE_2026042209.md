GitHub sync reference for Video Tracker
======================================

Repository:
- GitHub remote: `https://github.com/n-cardoso/moodle-mod_videotracker.git`

Verified branch mismatch:
- Remote GitHub branch: `main`
- Remote GitHub `main` HEAD: `4b2ee39b615149b8105204ab9c3ca8ef732e1139`
- Local repository branch ref: `main`
- Local repository branch ref SHA: `52202fec5c0fa3235a33ebe3b9a05da25eeef8a3`

Verified file mismatch on GitHub `main`:
- `db/install.php` on GitHub `main` still contains `defined('MOODLE_INTERNAL') || die();`
- `version.php` on GitHub `main` still reports `$plugin->version = 2026042206;`

Verified local working copy state:
- `db/install.php` does not contain the `MOODLE_INTERNAL` guard
- `version.php` reports `$plugin->version = 2026042209;`

Conclusion:
- The GitHub CI warning is coming from an older source state on GitHub `main`.
- The local working copy in this folder is newer than GitHub `main`.

Correct Moodle install package from the local fixed copy:
- `mod_videotracker-1.0.0+2026042209-ci-phpcs-stylelint-fix.zip`

Correct source set to sync to GitHub:
- Use only the files and folders listed in `SOURCE_SET_2026042209.txt`
- Do not sync the nested `videotracker/` folder inside this folder
- Do not sync old zip artifacts in this folder
