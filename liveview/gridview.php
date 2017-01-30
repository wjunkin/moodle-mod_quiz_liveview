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
 * This script provides the IPAL spreadsheet view for the teacher.
 *
 * @package    mod_quiz
 * @copyright  2016 W. F. Junkin, Eckerd College, http://www.eckerd.edu
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once('../../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
$id = optional_param('id', 0, PARAM_INT);
$q = optional_param('q', 0, PARAM_INT);
$evaluate = optional_param('evaluate', 0, PARAM_INT);
$mode = optional_param('mode', '', PARAM_ALPHA);

if ($id) {
    if (!$cm = get_coursemodule_from_id('quiz', $id)) {
        print_error('invalidcoursemodule');
    }
    if (!$course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconf');
    }
    if (!$quiz = $DB->get_record('quiz', array('id' => $cm->instance))) {
        print_error('invalidcoursemodule');
    }

} else {
    if (!$quiz = $DB->get_record('quiz', array('id' => $q))) {
        print_error('invalidquizid', 'quiz');
    }
    if (!$course = $DB->get_record('course', array('id' => $quiz->course))) {
        print_error('invalidcourseid');
    }
    if (!$cm = get_coursemodule_from_instance("quiz", $quiz->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

$url = new moodle_url('/mod/quiz/liveview/gridview.php', array('id' => $cm->id));
if ($mode !== '') {
    $url->param('mode', $mode);
}
$PAGE->set_url($url);
$quizid = $quiz->id;

require_login($course, false, $cm);

$contextinstance = context_module::instance($cm->id);
$quizcontextid = $contextinstance->id;
if (!(has_capability('mod/quiz:viewreports', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}
$debug = 0;
if ($debug) {echo "\n<br />The cm id for this quiz is ".$cm->id;}

/**
 * Return the number of users who have submitted answers to this quiz instance.
 *
 * @param int $quizid The ID for the quiz instance
 * @return array The userids for all the students submitting answers.
 */
function liveview_who_sofar_gridview($quizid) {
    global $DB;

    $records = $DB->get_records('quiz_attempts', array('quiz' => $quizid));

    foreach ($records as $records) {
        $answer[] = $records->userid;
    }
    if (isset($answer)) {
        return(array_unique($answer));
    } else {
        return(null);
    }
}

/**
 * Return the first and last name of a student.
 *
 * @param int $userid The ID for the student.
 * @return string The last name, first name of the student.
 */
function liveview_find_student_gridview($userid) {
     global $DB;
     $user = $DB->get_record('user', array('id' => $userid));
     $name = $user->lastname.", ".$user->firstname;
     return($name);
}

echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"gridviewstyle.css\" />";
$slots = liveviewslots($quizid);
$question = liveviewquestion($slots);

echo "<table border=\"1\" width=\"100%\">\n";
echo "<thead><tr>";

// If anonymous, exclude the column "name" from the table.
$quiz->anonymous = 0;
if (!$quiz->anonymous) {
    echo "<th>Name</th>\n";
}

foreach ($slots as $key => $slotvalue) {
    echo "<th style=\"word-wrap: break-word;\">";
    if (isset($question['name'][$key])) {
        echo substr(trim(strip_tags($question['name'][$key])), 0, 80);
    }
    echo "</th>\n";
}
echo "</tr>\n</thead>\n";

$users = liveview_who_sofar_gridview($quizid);
// Get the questionids as the keys to the $slots array so we know all the questions in the quiz.
function liveviewslots($quizid) {
    global $DB;
    $slots = array();
    $myslots = $DB->get_records('quiz_slots', array('quizid' => $quizid));
    foreach ($myslots as $key => $value) {
        $slots[$value->questionid] = $value->slot;
    }
    return $slots;
}
// Get the qtype, name, questiontext for each question.
function liveviewquestion($slots) {
    global $DB;
    $question = array();
    foreach ($slots as $questionid => $slotvalue) {
        $myquestion = $DB->get_record('question', array('id' => $questionid));
        $question['qtype'][$questionid] = $myquestion->qtype;
        $question['name'][$questionid] = $myquestion->name;
        $question['questiontext'][$questionid] = $myquestion->questiontext;
    }
    return $question;
}
// Get a table of student answers so far.
function liveview_student_answers($quizcontextid, $question) {
    global $DB;
    $stanswers = array();// Student answers by questionid by studentid.
    $answers = array();// Correct answers by questionid and question_usage id.
    $qtypevalue['truefalse'][0] = get_string('false', 'quiz');
    $qtypevalue['truefalse'][1] = get_string('true', 'quiz');
    // Question_usages: Every time anyone attempts a quiz the attempt gets a new questionusageid.
    $questionusageids = $DB->get_records('question_usages', array('contextid' => $quizcontextid));
    foreach ($questionusageids as $key => $qusage) {
        // Question_attempts: Info about each question attempted: questionid, slot, questionsummary, and rightanswer.
        $questionattempts = $DB->get_records('question_attempts', array('questionusageid' => $key));
        foreach($questionattempts as $attemptid => $qvalue) {
            // Question: Gives the qtype. Difference qtypes are handled differently.
            //$question = $DB->get_record('question', array('id' => $qvalue->questionid));
            //$qtype = $question->qtype;
            $qtype = $question['qtype'][$qvalue->questionid];
            $rightanswers[$qvalue->questionid] = $qvalue->rightanswer;
            $studentanswers = $DB->get_records('question_attempt_steps', array('questionattemptid' => $qvalue->id));
            foreach ($studentanswers as $skey => $sanswer) {
/** I need to get all answers even when the answer has not been submitted completely.
                if ($sanswer->state == 'todo') {
                    $todoid = $sanswer->id; 
                } else 
*/
                if ($sanswer_data = $DB->get_records('question_attempt_step_data', 
                    array('attemptstepid' => $sanswer->id, 'name' => 'answer'))) {
                    foreach ($sanswer_data as $akey => $sanswerdata) {
                        if ($sanswerdata->name == 'answer') {// One of the qtypes where the value is the answer or the answer index.
                            $stans[$sanswer->userid][$qvalue->questionid] = $sanswerdata->value;// The basic answer.
                            if ($qtype == 'essay' || $qtype == 'shortanswer' || $qtype == 'calculated' || $qtype == 'calculatedsimple' || $qtype == 'numerical') {
                                $stanswers[$sanswer->userid][$qvalue->questionid] = $sanswerdata->value;
                            } else if ($qtype == 'truefalse') {
                                $stanswers[$sanswer->userid][$qvalue->questionid] = $qtypevalue['truefalse'][$sanswerdata->value];
                            } else if ($qtype == 'multichoice' || $qtype == 'calculatedmulti') {
                                $query = "SELECT qasd.value FROM {question_attempt_step_data} qasd, {question_attempt_steps} qas
                                    WHERE qasd.name = '_order' AND qasd.attemptstepid = qas.id AND qas.questionattemptid = ?";
                                $myorder = $DB->get_record_sql($query, array($qvalue->id));
                                $myanswers = explode(',', $myorder->value);
                                //echo "\n<br />debug and qvalue->id is ".$qvalue->id." and myorder is ".print_r($myorder);
                                // Get the array of various answers for this question type if it has various answers.
/**                                if ($qorder = $DB->get_record('question_attempt_step_data', array('attemptstepid' => $todoid, 'name' => '_order'))) {
                                    $myanswers = explode(',', $qorder->value);
                                    $myanswer = $DB->get_record('question_answers', array('id' =>$myanswers[$sanswerdata->value]));
                                    $stanswers[$sanswer->userid][$qvalue->questionid] = $myanswer->answer;
                                    
                                } else {
                                    $myanswers = explode('; ', $qvalue->questionsummary);
                                    if (isset($myanswers[$sanswerdata->value])) {
                                        $stanswers[$sanswer->userid][$qvalue->questionid] = $myanswers[$sanswerdata->value];
                                    } else {
                                        $stanswers[$sanswer->userid][$qvalue->questionid] = $sanswerdata->value."NA qtype=".$qtype;
                                    }
*/
                                if (isset($myanswers[$sanswerdata->value])) {
                                    $myanswer = $DB->get_record('question_answers', array('id' =>$myanswers[$sanswerdata->value]));                                    
                                    $stanswers[$sanswer->userid][$qvalue->questionid] = $myanswer->answer;
                                } else {
                                    $stanswers[$sanswer->userid][$qvalue->questionid] = $sanswerdata->value."NA qtype=".$qtype;
                                }                                    
                            }
                        } else {
                            $stanswers[$sanswer->userid][$qvalue->questionid] = "N//A qtype=".$qtype." and the first two characters are ".substr($qtype, 0, 2);
                        }
                    }
                } else if ($sanswer_data = $DB->get_records('question_attempt_step_data', 
                    array('attemptstepid' => $sanswer->id))) {
                    foreach ($sanswer_data as $sanswerdata) {
                        if (substr($qtype, 0, 2) == 'dd') {
                            if (preg_match("/^[pc](\d+)/",$sanswerdata->name,$matches)) {
                                    $stanswers[$sanswer->userid][$qvalue->questionid][$matches[1]] = $sanswerdata->value;
                            }
                        }
                    }

                } else {
                    if (!(isset($stanswers[$sanswer->userid][$qvalue->questionid]))) {
                        $stanswers[$sanswer->userid][$qvalue->questionid] = "N/A qtype=".$qtype;
                    }
                }
            }
        }
    }
        $result = array($stanswers, $rightanswers);
        return $result;
}
class liveview_fraction {
    public $dm;
    public $id;
    public function __construct($qubaid) {
        $this->dm = question_engine::load_questions_usage_by_activity($qubaid);
    }

    public function get_id() {
        $id = $this->dm->get_id();
        return $id;
    }
    public function get_question($slot) {
        return $this->dm->get_question($slot);
    }
    public function get_fraction ($slot, $answer) {
        $myquestion = $this->dm->get_question($slot);
        //$myresult = array('answer' => $answer);
        $mygrade = $myquestion->grade_response(array('answer' => $answer));
        return $mygrade[0];
    }
}
function liveview_answer_fraction () {
    return $fraction[$userid][$questionid];
}
$result = liveview_student_answers($quizcontextid, $question);
$stanswers = $result[0];
if ($evaluate) {
    /* I have to get the 
        student response (which comes from the question_attempt_step_data table,
        It is $stans[$userid][$questionid] determined above.
        userid (this can come from the question_attempt_step table)
        the questionid.
    **/
    // Get the background color for all the questions and answers.
    $qubaid = 4;
    $mydm = new liveview_fraction($qubaid);
    $myqas = $DB->get_records('question_attempts', array('questionusageid' => $qubaid));
    foreach ($myqas as $myqa) {
        $myslot = $myqa->slot;echo "\n<br />debug268 in gridview and myslot is $myslot";
        $myquestionid = $myqa->questionid;
        $mystudentid = 3;
        $fraction[$mystudentid][$myquestionid] = null;
    }
    $rightanswer = $result[1];
    function backgroundcolor ($slots, $stanswers, $users, $rightanswer, $question) {
        global $DB;
        $backgroundcolor = array();
        $csscolor = array();
        $csscolor['1'] = "style='background-color: #99ff99'";// Color for right answer.
        $csscolor['0'] = "style='background-color: #ff9999'";// Color for wrong answer.
        foreach ($slots as $questionid => $slot) {
            foreach ($users as $userid) {
                if (isset($stanswers[$userid][$questionid])) {// Only evalute answered questions.
/** Get the fraction for graded question if grading is requested ($evaluate = 1).
                switch($question['qtype'][$questionid]) {
                        case 'essay':
                            $backgroundcolor[$userid][$questionid] = "style='background-color: rgb(255,209,126)'";
                            //$backgroundcolor[$userid][$questionid] = "style='background-color: #ffcc99'";
                            break;
                        case 'truefalse':
                            if ($stanswers[$userid][$questionid] == $rightanswer[$questionid]) {
                                $backgroundcolor[$userid][$questionid] = $csscolor['1'];
                            } else {
                                $backgroundcolor[$userid][$questionid] = $csscolor['0'];
                            }
                            break;
                        case 'multichoice':
                            $mystanswer = $stanswers[$userid][$questionid];
                            $fractionquery = "SELECT fraction FROM {question_answers} WHERE question = $questionid AND ".$DB->sql_compare_text('answer')." = ?";
                            if ($fractions = $DB->get_record_sql($fractionquery, array($mystanswer))) {
                                $myfraction = $fractions->fraction;
                                if (isset($csscolor["$myfraction"])) {
                                    $backgroundcolor[$userid][$questionid] = $csscolor["$myfraction"];
                                } else {
                                    $greenpart = intval(127*$myfraction + 128);// Add in as much green as the answer is correct.
                                    $redpart = 383 - $greenpart;// This is 255 - myfraction*127.
                                    $backgroundcolor[$userid][$questionid] = "style='background-color: rgb($redpart,$greenpart,126)'";
                                }
                            } else {
                                $backgroundcolor[$userid][$questionid] = '';
                            }
                            break;
                        default:
                            $backgroundcolor[$userid][$questionid] = '';
                            break;
                    }
**/
                    if (isset($fraction[$userid][$questionid])) {
                        $myfraction = $fraction[$userid][$questionid];
                        $greenpart = intval(127*$myfraction + 128);// Add in as much green as the answer is correct.
                        $redpart = 383 - $greenpart;// This is 255 - myfraction*127.
                        $backgroundcolor[$userid][$questionid] = "style='background-color: rgb($redpart,$greenpart,126)'";
                    } else {
                        $backgroundcolor[$userid][$questionid] = '';
                    }
                }
            }
        }
    return $backgroundcolor;
    }
    $backgroundcolor = backgroundcolor($slots, $stanswers, $users, $rightanswer, $question);
}
if (isset($users)) {
    foreach ($users as $user) {
        echo "<tbody><tr>";

        // If anonymous, exlude the student name data from the table.
        if (!$quiz->anonymous) {
            echo "<td>".liveview_find_student_gridview($user)."</td>\n";
        }
        //foreach ($questions as $question) {
        foreach ($slots as $questionid => $slotvalue) {
            if (($questionid != "") and ($questionid != 0)) {
                if (isset($stanswers[$user][$questionid])) {
                    if (count($stanswers[$user][$questionid]) == 1) {
                        $answer = $stanswers[$user][$questionid];
                    } else {
                        $answer = '';
                        foreach ($stanswers[$user][$questionid] as $key => $value) {
                            $answer .= $key."=".$value."; ";
                        }
                    }
                } else {
                    $answer = '&nbsp;';
                }
            }
//            echo "<td style='background-color: #ffcc99'>$answer</td>";
            echo "<td ";
            if ($evaluate) {
                echo $backgroundcolor[$user][$questionid];
            }
            echo ">$answer</td>";
        }
        echo "</tr></tbody>\n";
    }
}

echo "</table>\n";
echo "\n<br />finished";