<?php
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
 * Wrapped config password admin setting for Video Tracker.
 *
 * @package     mod_videotracker
 * @copyright   2026 LearnPlug
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_videotracker\local;

/**
 * Password setting with a custom wrapper class.
 */
class admin_setting_configpasswordunmask_wrapped extends \admin_setting_configpasswordunmask {
    /** @var string */
    private $wrapperclass;
    /** @var mixed */
    private $fallbacksetting;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $visiblename
     * @param string $description
     * @param mixed $defaultsetting
     * @param string $wrapperclass
     */
    public function __construct($name, $visiblename, $description, $defaultsetting, string $wrapperclass = '') {
        parent::__construct($name, $visiblename, $description, $defaultsetting);
        $this->wrapperclass = $wrapperclass;
        $this->fallbacksetting = $defaultsetting;
    }

    /**
     * Return a fallback value when Moodle has not saved this optional setting yet.
     *
     * @return mixed
     */
    public function get_setting() {
        $setting = parent::get_setting();
        return $setting === null ? $this->fallbacksetting : $setting;
    }

    /**
     * Output setting HTML.
     *
     * @param mixed $data
     * @param string $query
     * @return string
     */
    public function output_html($data, $query = ''): string {
        return \html_writer::div(parent::output_html($data, $query), $this->wrapperclass);
    }
}
