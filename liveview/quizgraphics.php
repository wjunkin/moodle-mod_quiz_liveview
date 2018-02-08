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
 * Displays the quiz HIstogram or text resposes.
 *
 * An indicaton of # of responses to this question/# of student responding to this quiz instance is printed.
 * After that the histogram or the text responses are printed, depending on the question type.
 * @package    mod_ipal
 * @copyright  2018 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
require_once($CFG->dirroot.'/lib/graphlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');// Needed for class question_engine.
$quizid = optional_param('quizid', 0, PARAM_INT);
$question_id = optional_param('question_id', 0, PARAM_INT);
$quiz = $DB->get_record('quiz', array('id' => $quizid));
$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
if (!(has_capability('mod/quiz:manage', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}

/**
 * Return the id for the desired question.
 * If no question is specified in the URL, then the active question (if there is one) will be returned.
 *
 * @param int $quizid The ID for the quiz instance
 * @return int The ID for the current question.
 */
function quiz_show_current_question_id_graphics($quizid) {
    global $DB;
    $question_id = optional_param('question_id', 0, PARAM_INT);// Used if we want a specific question.
	if ($question_id > 0) {
		return($question_id);
	} else if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        $question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
        return($question->question_id);
    }
    return(0);
}

/**
 * Return the message about the number of users answering a question.
 * If the question_id is set, then all responses to this question will be given.
 * Otherwise the question_id is taken from the quiz_active_questions and only those responses sent after the active question was modified will provided.
 * The responses will be returned indexed by userid.
 * The message states who have responded this time compared to the total number of users who have responded to this question.
 *
 * @return int The number of students submitting answers to this question.
 */
function quiz_question_answers($quizid, $question_id) {
	global $DB;
	
	$message = '';
	if ($question_id) {
		$timesent = 0;
		$questionid = $question_id;
		$questiontext = $DB->get_record('question', array('id' => $questionid));
        $message .= "The current question is -> ".strip_tags($questiontext->questiontext)."\n<br />";

	} else {
		$question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
		$questionid = $question->question_id;
		$questiontext = $DB->get_record('question', array('id' => $questionid));
		$timesent = $question->timemodified;
	}
	$quizattempts = $DB->get_records('quiz_attempts', array('quiz' => $quizid));
	// An array, quizattpt, that has all rows from the quiz_attempts table indexed by userid and value of the quizattemptid.
	$quizattpt = array();
	// An array, quizattptnow, that has the last uniqueid of an attempt sent after the question was sent, indexed by user.
	// If they answered after the last question was sent, they must have answered the last question, so we don't have to check questionid.
	$quizattptnow = array();
	// An array of the last answer that each student has given, indexed by the student id.
	$answerdata = array();
	foreach ($quizattempts as $quizattempt) {
		$quizattpt[$quizattempt->userid] = $quizattempt->id;
		if ($quizattempt->timemodified > $timesent) {
			$quizattptnow[$quizattempt->userid] = $quizattempt->uniqueid;
		}
	}

	foreach ($quizattptnow as $userkey => $attptnow) {
		if ($question_attempt = $DB->get_record('question_attempts', array('questionusageid' => $attptnow, 'questionid' => $questionid))) {
			// Only consider data submitted after the $timesent variable.
			if ($question_attempt->timemodified > $timesent) {
			$qattempt[$userkey] = $question_attempt->id;
				if ($question_step = $DB->get_records('question_attempt_steps', array('questionattemptid' => $question_attempt->id))) {
					$stepid = $orderid = 0;// We don't want results from other attempts.
					foreach ($question_step as $step) {
						if ($step->sequencenumber == 0) {
							// Might be a step that sets the order of the question.
							$orderid = $step->id;
						} else {
							$stepid = $step->id;
						}
					}
					if ($question_data = $DB->get_records('question_attempt_step_data', array('attemptstepid' => $stepid, 'name' => 'answer'))) {
						if ($questiontext->qtype == 'essay') {
							foreach ($question_data as $data) {
								$answerdata[$userkey] = $data->value;
							}
						} else if ($questiontext->qtype == 'truefalse') {
							$question_answers = $DB->get_records('question_answers', array('question' => $questionid));
							foreach ($question_answers as $question_answer) {
								if ($question_answer->answer == 'True') {
									$myanswer[1] = $question_answer->id;
								} else {
									$myanswer[0] = $question_answer->id;
								}
							}
							foreach ($question_data as $data) {
								$answerdata[$userkey] = $myanswer[$data->value];
							}
						} else {
							$order = array();
							$answer = '';
							foreach ($question_data as $data) {
								$answer = intval($data->value);
							}
							
							if ($answer > -1) {// An answer to a multichoice question has been submitted.
								if ($step_order = $DB->get_record('question_attempt_step_data', array('attemptstepid' => $orderid, 'name' => '_order'))) {
									$order = explode(',', $step_order->value);
									$answerdata[$userkey] = $order[$answer];
								} else {								
									echo "\n<br />Unable to get the answer from question_attempt_step_data table for user $userkey";exit;
								}
							}
						}
					} else if ($question_data = $DB->get_records_sql('SELECT * FROM {question_attempt_step_data} 
						WHERE attemptstepid = ? AND name LIKE ?', array( $stepid , 'choice%' ))) {
						if ($step_order = $DB->get_record('question_attempt_step_data', array('attemptstepid' => $orderid, 'name' => '_order'))) {
							unset($answerdata[$userkey]);
							$order = explode(',', $step_order->value);
							foreach ($question_data as $data) {
								if (($data->value) && preg_match("/choice(\d+)/", $data->name, $matches)) {
									if (isset($answerdata[$userkey])) {
										$answerdata[$userkey] .= "&q&".$order[$matches[1]];
									} else {
										$answerdata[$userkey] = $order[$matches[1]];
									}
								}
									
							}
						}
					}
					 
				}
			}
		}
	}
	$message .= "Total responses --> ".count($answerdata).'/'.count($quizattpt);
	$result = array($message, $answerdata);
	return $result;
}

/**
 * Return a string = number of responses to each question and labels for questions.
 *
 * @param int $questioncode The id in the active question table for the active question.
 * @return string The number of responses to each question and labels for questions.
 */
function quiz_count_question_codes($quizid, $question_id) {
    global $DB;

    
	if ($question_id) {
		$questionid = $question_id;
	} else {
		$question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
		$questionid = $question->question_id;
	}
	if ($answers = $DB->get_records('question_answers', array('question' => $questionid))) {
		// This is not an essay question.
		$labels = '';
		$n = 0;
		foreach ($answers as $answer) {
			// Labels ae indexed by the order that they appear in the database. Data on student responses is indexed by the answerid.
			$answerid = $answer->id;
			$labels .= "&x[$n]=".urlencode(substr(strip_tags($answer->answer), 0, 15));
			$data[$answerid] = 0;
			$n ++;
		}
	// Now find out how many students have given each answer.
		$myresult = quiz_question_answers($quizid, $question_id);
		$message = $myresult[0];
		$stdata = $myresult[1];
		if (count($stdata) > 0) {
			foreach ($stdata as $answersent) {
				if (preg_match("/\&q\&/", $answersent)) {
					// This is a multichoice question with more than one selection.
					$multichoiceanswers = explode('&q&', $answersent);
					foreach ($multichoiceanswers as $mcanswer) {
						$data[$mcanswer] ++;
					}
				} else {
					$data[$answersent] ++;
				}
			}
		}
		
		$graphinfo = "?data=".implode(",", $data).$labels."&total=10";
		$result = array($message, $graphinfo);
		return $result;
	} else {
		// Might be an essay question.
		$myresult = quiz_question_answers($quizid, $questionid);
		return $myresult;
	}
}


echo "\n<html>\n<head><title>Quiz Graph</title>\n</head>\n<body>";

$result = quiz_count_question_codes($quizid, $question_id);
// The first member of the result array is a statement about how many have responded.
echo $result[0];

/**
 * A function to optain the question type of a given question.
 *
 *
 * Modified by Junkin
 * @param int $questionid The id of the question
 * @return int question type
 */
function quiz_get_question_type($questionid) {
    global $DB;
    $questiontype = $DB->get_record('question', array('id' => $questionid));
    return($questiontype->qtype);
}


$qtype = quiz_get_question_type(quiz_show_current_question_id_graphics($quizid));
if ($qtype == 'essay') {
    $answers = $result[1];
    foreach ($answers as $answer) {
        echo "\n<br />".strip_tags($answer);
    }
} else {// Only show graph if question is not an essay question.
    echo "\n<br /><img src=\"graph.php".$result[1]."\"></img>";
}
echo "\n</body>\n</html>";