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
 * @package   mod_quiz_liveview
 * @copyright 2018 W. F. Junkin junkinwf@eckerd.edu Eckerd College
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once("$CFG->libdir/formslib.php");
defined('MOODLE_INTERNAL') || die();


/**
 * Java script for checking to see if the Question has changed and refresh the student screen when polling has stopped or a question has been sent.
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
$attempt = optional_param('attempt', 0, PARAM_INT);
if ($attempt > 0) {
	$success = $DB->update_record('quiz_attempts', array('id' => $attempt, 'timemodified' => time()));
	// Check to be sure the pages are not shuffled.
	$quizattempts = $DB->get_record('quiz_attempts', array('id' => $attempt));
	$layout = $quizattempts->layout;
	$pages = explode(',0', $layout);
	$ordered = true;
	for ($n = 0; $n < count($pages) -1; $n++) {
		$goodpage = $n + 1;
		$pagevalue = trim($pages[$n], ",");
		if ($pagevalue != $goodpage) {
			$ordered = false;
			$pages[$n] = preg_replace("/$pagevalue/", $goodpage, $pages[$n]);
		}
	}
	
	if(!$ordered) {
		$newlayout = implode(',0', $pages);
		$success1 = $DB->update_record('quiz_attempts', array('id' => $attempt, 'layout' => $newlayout));
	}		

}
// Find out if a question has been sent.
$activequestion = $DB->get_record('quiz_active_questions', array('quiz_id' => $attemptobj->get_quizid()));
if ($activequestion) {
	// Send the appropriate question to the student.
	$activepage = $DB->get_record('quiz_slots', array('quizid' => $attemptobj->get_quizid(), 'questionid' => $activequestion->question_id));
	$nextpage = $activepage->page;
	$page = $nextpage - 1;
	$slots = array($activepage->slot);
	$pollingjavascript = quiz_java_questionupdate($attemptobj->get_quizid());
} else {
	// Since no question has been sent or polling has stopped, send the "no active question" question to the student.
	if ($pages = $DB->get_records('quiz_slots', array('quizid' => $attemptobj->get_quizid()))) {
		$mypage = array();
		foreach ($pages as $mypages) {
			$mypage[] = $mypages;
		}
		$lastpage = $mypage[count($mypage) -1];
		$nextpage = $lastpage->page;
		$page = $nextpage-1;
		$slots = array($lastpage->slot);
	} else {
		echo "\n<br />There are no questions in this quiz.";
	}
	
}