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
 * This script echos back a hash to let the client know if the graph for the question has changed.
 *
 * Since this script sends back no useful information (other than a change in the hash to indicate the
 * graph has changed) no authentication as teacher is required.
 * @package    mod_ipal
 * @copyright  2012 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/lib/graphlib.php');
defined('MOODLE_INTERNAL') || die();

/**
 * Return the id for the current question
 *
 * @param int $quizid The ID for the quiz instance
 * @return int The ID for the current question.
 */
function quiz_show_current_question_id($quizid) {
    global $DB;
    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        $question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
        return($question->question_id);
    }
    return(0);
}

/**
 * A function to optain the question type of a given question.
 *
 *
 * Modified by Junkin
 * @param int $questionid The id of the question
 * @return int question type
 */
function quiz_get_qtype($questionid) {
    global $DB;
    if ($questionid) {
        $questiontype = $DB->get_record('question', array('id' => $questionid));
        return($questiontype->qtype);
    } else {
        return('');
    }
}

/**
 * Return a string = number of responses to each question and labels for questions.
 *
 * @param int $questionid The question id in the active question table for the active question.
 * @param int $quizid The id of this quiz instance.
 * @return string The number of responses to each question and labels for questions.
 */
function quiz_count_questions($questionid, $quizid) {
    global $DB;

    $data = array();
    $labels = array();

    $qtype = quiz_get_qtype($questionid);
    if ($qtype == 'essay') {
        $labels[] = 'Responses';
        $answers = $DB->get_records('quiz_answered', array('question_id' => $questionid));
        $sum = 0;// The sum of the id's for all answers. If any student submits a new answer, this sum must change.
        foreach ($answers as $answers) {
            $sum = $sum + $answers->id;
        }
        $data[] = $sum;
    } else {
        $answers = $DB->get_records('question_answers', array('question' => $questionid));
        foreach ($answers as $answers) {
            $labels[] = $answers->answer;
            $data[] = $DB->count_records('quiz_answered', array('quiz_id' => $quizid, 'answer_id' => $answers->id));

        }
    }
    return( "?data=".implode(",", $data)."&labels=".implode(",", $labels)."&total=10");

}

$quizid = optional_param('quizid', 0, PARAM_INT);
if ($questionid = quiz_show_current_question_id($quizid)) {
    echo md5("graph.php".quiz_count_questions($questionid, $quizid));
}