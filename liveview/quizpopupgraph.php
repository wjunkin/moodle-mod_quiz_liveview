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
 * Page to display student answers to quiz questions
 *
 * @package    mod_quiz_liveview
 * @copyright 2018 W. F. Junkin, Eckerd College (http://www.eckerd.edu)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');
$quizid = optional_param('quizid', 0, PARAM_INT);// The id of this quiz instance.
?>
<html>
<head>
<?php
echo "<meta http-equiv=\"refresh\" content=\"6;url=?quizid=".$quizid."\">";
?>
</head>
<body>
<?php
$quiz = $DB->get_record('quiz', array('id' => $quizid));
$course = $DB->get_record('course', array('id' => $quiz->course), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id, false, MUST_EXIST);
require_login($course, true, $cm);
$contextinstance = context_module::instance($cm->id);
if (!(has_capability('mod/quiz:manage', $contextinstance))) {
    echo "\n<br />You must be authorized to access this site";
    exit;
}
echo "<iframe id= \"graphIframe\" src=\"quizgraphics.php?quizid=".$quizid."\" height=\"540\" width=\"723\"></iframe>";
echo "</body></html>";

/**
 * Return the id for the current question
 *
 * @param obj $state The object giving information about the quiz instance.
 * @return int The ID for the current question.
 */
/***function quiz_show_current_question_bool($state) {
    global $DB;

    $quiz = $state;
    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quiz->id))) {
        $question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quiz->id));
        $questiontext = $DB->get_record('question', array('id' => $question->question_id));
        // Removing any EJS from the quiz/view.php page. Note: A dot does not match a new line without the s option.
        $questiontext->questiontext = preg_replace("/EJS<ejsquiz>.+<\/ejsquiz>/s", "EJS ", $questiontext->questiontext);
        echo "The current question is -> ".strip_tags($questiontext->questiontext);
        return(1);
    } else {
        return(0);
    }
}

require_once('graphiframe.php');
// Print out the graph.
quiz_print_graph($quizid);
?>
</body>
</html>
**/