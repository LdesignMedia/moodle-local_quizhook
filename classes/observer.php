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
 * Observer
 *
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @package   moodle-local_quizhook
 * @copyright 2019-01-18 MFreak.nl
 * @author    Luuk Verhoeven
 **/

namespace local_quizhook;

use completion_info;

defined('MOODLE_INTERNAL') || die;

class observer {

    /**
     * @param \mod_quiz\event\attempt_submitted $event
     *
     * @throws \dml_exception
     * @throws \coding_exception
     * @throws \moodle_exception
     */
    public static function attempt_submitted(\mod_quiz\event\attempt_submitted $event) {
        global $DB;

        if ($event->get_context()->contextlevel != CONTEXT_MODULE) {
            return;
        }
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid]);
        if (empty($attempt->sumgrades)) {

            // Set a 0 grade.
            $DB->update_record('quiz_attempts', (object)[
                'id' => $attempt->id,
                'sumgrades' => 0,
            ]);
            $quiz = $event->get_record_snapshot('quiz', $attempt->quiz);

            quiz_update_grades($quiz, $event->relateduserid);

            // Make sure completion is also set.
//            $cm = get_coursemodule_from_id('quiz', $event->get_context()->instanceid, $event->courseid);
//            $course = $DB->get_record('course', ['id' => $event->courseid]);
//            $completion = new completion_info($course);
//            if ($completion->is_enabled($cm)) {
//                $completion->update_state($cm, COMPLETION_COMPLETE, $event->relateduserid);
//            }
        }
    }

}