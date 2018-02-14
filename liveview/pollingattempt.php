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
 * Code to make the quiz/attempt.php code work for in-class polling. this code is only to be included in the quiz/attempt.php code.
 *
 * All the ipal specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_quiz
 * @copyright 2017 W. F. Junkin junkinwf@eckerd.edu Eckerd College
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once("$CFG->libdir/formslib.php");
$quiz = $DB->get_record('quiz', array('id' => $attemptobj->get_quizid()));
$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);

if ($attemptobj->has_capability('mod/quiz:manage')) {
    redirect($CFG->wwwroot . "/mod/quiz/liveview/quizview.php?n=".$attemptobj->get_quizid());
    exit;
} else {
    // This person probably is a student.
    echo quiz_java_questionupdate($attemptobj->get_quizid());
}


/**
 * Java script for checking to see if the Question has changed.
 * This scriptrefreshes the student screen when polling has stopped or a question has been sent.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_java_questionupdate($quizid) {
    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";\nvar myCount=0;
        \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
        {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
    echo "\n\nfunction replace() {\nvar t=setTimeout(\"replace()\",3000)";
    echo "\nhttp.open(\"GET\", \"liveview/current_question.php?quizid=".$quizid."\", true);";
    echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {\n\nif(http.responseText != x && myCount > 1){\n";
    echo "window.location = window.location.href+'&x';\n";
    echo "}\nx=http.responseText;}\n}\nhttp.send(null);\nmyCount++;}\n\nreplace();\n</script>";
}
// Update the time of this attempt.
$attemptid = optional_param('attempt', 0, PARAM_INT);
if ($attemptid > 0) {
    $success = $DB->update_record('quiz_attempts', array('id' => $attemptid, 'timemodified' => time()));
    $quizattempts = $DB->get_record('quiz_attempts', array('id' => $attemptid));
    $layout = $quizattempts->layout;
    $pages = explode(',0,', $layout.",");
    foreach ($attemptobj->get_slots() as $slot) {
        $myslot = $DB->get_record('quiz_slots', array('quizid' => $attemptobj->get_quizid(), 'slot' => $slot));
        $myquestionid = $myslot->questionid;
        $questionpage[$myquestionid] = $myslot->page;
        $questionslot[$myquestionid] = $slot;
    }
}
// Find out if a question has been sent.
$activequestion = $DB->get_record('quiz_active_questions', array('quiz_id' => $attemptobj->get_quizid()));
$myattempt = $DB->get_record('quiz_attempts', array('id' => $attemptid));
if ($activequestion) {
    // Getting the page number that will send the correct slot.
    // The slot we want is $questionslot[$activequestion->question_id].
    $myslot = $questionslot[$activequestion->question_id];
    $page = -1;
    for ($n = 0; $n < count($pages); $n++) {
        $layoutpage = intval($pages[$n]);// Using inval because the explode of the layout may have commas.
        if ($myslot == $layoutpage) {
            $page = $n;
        }
    }
    $pollingjavascript = quiz_java_questionupdate($attemptobj->get_quizid());
    $slots = array($myslot);
    if ($page > -1) {
        $nexpage = $page - 1;
    } else {
        echo "\n<br />Something is wrong since there was no page for the desired slot and questionid.";
        exit;
    }
} else {
    // Since no question has been sent or polling has stopped, send the "no active question" question to the student.
    if ($pages = $DB->get_records('quiz_slots', array('quizid' => $attemptobj->get_quizid()))) {
        $mypage = array();
        foreach ($pages as $mypages) {
            $mypage[] = $mypages;
        }
        $lastpage = $mypage[count($mypage) - 1];
        $nextpage = $lastpage->page;
        $page = $nextpage - 1;
        $slots = array($lastpage->slot);
    } else {
        echo "\n<br />There are no questions in this quiz.";
    }
}