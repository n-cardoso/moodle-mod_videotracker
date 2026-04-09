// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Admin settings helper for the license screen advanced settings toggle.
 *
 * @module     mod_videotracker/license_settings_toggle
 * @copyright  2026 LearnPlug
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const run = () => {
    const toggle = document.querySelector('[data-vt-license-toggle="advanced"]');
    if (!toggle) {
        return;
    }

    document.body.classList.add('vt-license-advanced-ready');

    const advancedsettings = Array.from(document.querySelectorAll('.vt-license-advanced-setting'));
    let expanded = false;
    const labelnode = toggle.querySelector('[data-vt-license-toggle-label]');
    const showlabel = toggle.getAttribute('data-show-label') || '';
    const hidelabel = toggle.getAttribute('data-hide-label') || '';

    const sync = () => {
        document.body.classList.toggle('vt-license-advanced-open', expanded);
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        if (labelnode) {
            labelnode.textContent = expanded ? hidelabel : showlabel;
        }
        advancedsettings.forEach((node) => {
            node.setAttribute('aria-hidden', expanded ? 'false' : 'true');
        });
    };

    toggle.addEventListener('click', (event) => {
        event.preventDefault();
        expanded = !expanded;
        sync();
    });

    sync();
};

export const init = () => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', run, {once: true});
        return;
    }

    run();
};
