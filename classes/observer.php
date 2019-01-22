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
use context_course;

defined('MOODLE_INTERNAL') || die;

class observer {

    /**
     * Attempt submitted
     *
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

        // Get records.
        $attempt = $DB->get_record('quiz_attempts', ['id' => $event->objectid]);
        $quiz = $event->get_record_snapshot('quiz', $attempt->quiz);

        if (empty($attempt->sumgrades) &&
            self::has_essay_questions($quiz)) {

            // Set a 0 grade.
            $grade = new \stdClass();
            $grade->userid = $event->relateduserid;
            $grade->rawgrade = 0;
            quiz_grade_item_update($quiz, $grade);

            // Update all.
            quiz_update_all_attempt_sumgrades($quiz);
            quiz_update_all_final_grades($quiz);

            // Make sure completion is visible.
            context_course::instance($event->courseid)->mark_dirty(); // reset caches

            // Make sure there is a 0 grade.
            $DB->update_record('quiz_attempts', (object)['id' => $event->objectid, 'sumgrades' => 0]);

            $row = $DB->get_record('quiz_grades', [
                'userid' => $event->relateduserid,
                'quiz' => $attempt->quiz,
            ]);
            if (!$row) {
                $DB->insert_record('quiz_grades', (object)[
                    'userid' => $event->relateduserid,
                    'quiz' => $attempt->quiz,
                    'grade' => 0,
                    'timemodified' => time(),
                ]);
            }

            // Make sure completion is also set.
//            $cm = get_coursemodule_from_id('quiz', $event->get_context()->instanceid, $event->courseid);
//            $course = $DB->get_record('course', ['id' => $event->courseid]);
//            $completion = new completion_info($course);
//            if ($completion->is_enabled($cm)) {
//                $completion->update_state($cm, COMPLETION_COMPLETE, $event->relateduserid);
//            }
        }
    }

    /**
     * has_essay_questions
     *
     * @param \stdClass $quiz
     *
     * @return bool
     * @throws \dml_exception
     */
    private static function has_essay_questions(\stdClass $quiz) : bool {
        global $DB;
        $sql = 'select s.id from {quiz_slots} s
                join {question} q on (q.id = s.questionid)
                where q.qtype = "essay" and s.quizid = :quizid';
        $row = $DB->get_record_sql($sql, ['quizid' => $quiz->id], IGNORE_MULTIPLE);
        if (!empty($row)) {
            return true;
        }

        return false;
    }

}