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
 * Form helper for completion-related settings.
 *
 * @module     mod_videotracker/form_completion_toggle
 * @copyright  2026 LearnPlug
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

export const init = () => {
    const form = document.querySelector('form.mform');
    if (!form) {
        return;
    }

    const gradePass = document.getElementById('id_gradepass');
    const required = document.getElementById('id_completionminpercent');
    if (!gradePass || !required) {
        return;
    }

    const show = (el) => {
        const row = el.closest('.form-group') || el.closest('.fitem') || el.parentNode;
        if (row) {
            row.style.display = '';
            row.classList.remove('d-none');
        }
    };

    const hide = (el) => {
        const row = el.closest('.form-group') || el.closest('.fitem') || el.parentNode;
        if (row) {
            row.style.display = 'none';
            row.classList.add('d-none');
        }
    };

    const isPassingGradeSelected = () => {
        const labels = Array.from(form.querySelectorAll('label'));
        const passingLabel = labels.find(l => (l.textContent || '').trim().toLowerCase() === 'passing grade');
        if (passingLabel && passingLabel.htmlFor) {
            const input = document.getElementById(passingLabel.htmlFor);
            if (input && (input.type === 'radio' || input.type === 'checkbox')) {
                return !!input.checked;
            }
        }
        const inputs = Array.from(form.querySelectorAll('input[type="radio"], input[type="checkbox"]'));
        const passCandidates = inputs.filter(i =>
            /completion.*pass/i.test(i.name || '') || /completion.*pass/i.test(i.id || '')
        );
        if (passCandidates.length) {
            return passCandidates.some(i => i.checked);
        }

        return true;
    };

    const apply = () => {
        if (isPassingGradeSelected()) {
            show(gradePass);
            show(required);
        } else {
            hide(gradePass);
            hide(required);
        }
    };

    apply();
    form.addEventListener('change', apply, true);
};
