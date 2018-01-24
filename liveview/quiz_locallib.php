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
 * Internal library of functions for module quiz
 *
 * All the quiz specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package   mod_ipal_quiz
 * @copyright 2017 Eckerd College
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once(dirname(dirname(dirname(dirname(__FILE__)))).'/config.php');

/**
 * This function puts all the elements together for the instructors interface.
 * This is the last stop before it is displayed.
 * @param int $cmid The id for the course module for this quiz instance.
 * @param int $quizid The id of this quiz instance.
 */
function quiz_display_instructor_interface($cmid, $quizid) { 
    global $DB;
    global $CFG;

    $clearquestion = optional_param('clearQuestion', null, PARAM_TEXT);
    $sendquestionid = optional_param('question', 0, PARAM_INT);

    if (isset($clearquestion)) {
        quiz_clear_question($quizid);
    }
    $state = $DB->get_record('quiz', array('id' => $quizid));
    $state->mobile = 0;// Mobile not implemented for quiz.
	if ($sendquestionid) {
        quiz_send_question($quizid, $state->mobile);
    }

    quiz_java_graphupdate($quizid,$cmid);
	$state->mobile = 0;// Not implemented yet.
    echo "<table><tr><td>".quiz_instructor_buttons($quizid)."</td>";
	echo "<td>&nbsp; &nbsp;<a href='".$CFG->wwwroot."/mod/quiz/edit.php?cmid=$cmid'>Add/Change Questions</a></td>";
	echo "<td>&nbsp; &nbsp;<a href='".$CFG->wwwroot."/mod/quiz/liveview/gridview.php?id=$cmid' target = '_blank'>Quiz spreadsheet</a></td>";
/** Mobile not supported right now
    if ($state->mobile) {
        $timecreated = $state->timecreated;
        $ac = $state->id.substr($timecreated, strlen($timecreated) - 2, 2);
        echo "<td>access code=$ac</td>";
    }
**/
    echo "</tr></table>";
    // Script to make the preview window a popout.
    echo "\n<script language=\"javascript\" type=\"text/javascript\">
    \n function quizpopup(id) {
        \n\t url = '".$CFG->wwwroot."/question/preview.php?id='+id+'&amp;cmid=";
        echo $cmid;
        echo "&amp;behaviour=deferredfeedback&amp;correctness=0&amp;marks=1&amp;markdp=-2";
        echo "&amp;feedback&amp;generalfeedback&amp;rightanswer&amp;history';";
        echo "\n\t newwindow=window.open(url,'Question Preview','height=600,width=800,top=0,left=0,menubar=0,";
        echo "location=0,scrollbars,resizable,toolbar,status,directories=0,fullscreen=0,dependent');";
        echo "\n\t if (window.focus) {newwindow.focus()}
        \n\t return false;
    \n }
    \n </script>\n";

    echo  quiz_make_instructor_form($quizid, $cmid);
    echo "<br><br>";

	if (quiz_show_current_question($quizid) == 1) {
		echo "<br>";
		echo "<br>";
		echo "<iframe id= \"graphIframe\" src=\"quizgraphics.php?quizid=".$quizid."\" height=\"540\" width=\"723\"></iframe>";
		echo "<br><br><a onclick=\"newwindow=window.open('quizpopupgraph.php?quizid=".$quizid."', '',
				'width=750,height=560,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,";
		echo "directories=no,scrollbars=yes,resizable=yes');
				return false;\"
				href=\"quizpopupgraph.php?quizid=".$quizid."\" target=\"_blank\">Open a new window for the graph.</a>";
	}
/**    } else {
        echo "<br>";
        echo "<br>";
        echo "<iframe id= \"graphIframe\" src=\"gridview.php?id=".$ipalid.
            "\" height=\"535\" width=\"723\"></iframe>";
        echo "<br><br><a onclick=\"window.open('popupgraph.php?ipalid=".$ipalid."', '',
                'width=620,height=450,toolbar=no,location=no,menubar=no,copyhistory=no,status=no,
                directories=no,scrollbars=yes,resizable=yes');
                return false;\"
                href=\"popupgraph.php?ipalid=".$ipalid."\" target=\"_blank\">Open a new window for the graph.</a>";
    }
**/
}

/**
 * This function clears the current question.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_clear_question($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        $mybool = $DB->delete_records('quiz_active_questions', array('quiz_id' => $quizid));
        /** Not implemented yet for the quiz.
		$ipal = $DB->get_record('ipal', array('id' => $ipalid));
        if (($ipal->mobile == 1) || ($ipal->mobile == 3)) {
            ipal_refresh_firebase($ipalid);
        }
		**/
    }
}


/**
 * Modification by Junkin.
 *  A function to optain the question type of a given question.
 * Redundant with function quiz_get_question_type in /mod/ipal/graphics.php.
 * @param int $questionid The id of the question.
 */
function quiz_get_qtype($questionid) {
    global $DB;
    if ($questiontype = $DB->get_record('question', array('id' => $questionid))) {
        return($questiontype->qtype);
    } else {
        return 'multichoice';
    }
}

/**
 * This function finds the current question that is active for the quiz that it was requested from.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_show_current_question_id($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        $question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
        return($question->question_id);
    } else {
        return(0);
    }
}


/**
 * Java script for checking to see if the chart need to be updated.
 *
 * @param int $quizid The id of this quiz instance.
 */
function old_quiz_java_graphupdate($quizid,$cmid) {
    global $DB;
    echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";
        \n\nif(navigator.appName == \"Microsoft Internet Explorer\")
        {\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
    echo "\n\nfunction replace() { ";
    $t = '&t='.time();
    echo "\nvar t=setTimeout(\"replace()\",10000);\nhttp.open(\"GET\", \"graphicshash.php?quizid=".$cmid.$t."\", true);";
    echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {\nif(parseInt(http.responseText) != parseInt(x)){";
	echo "\nx=http.responseText;\n";
//    $state = $DB->get_record('quiz', array('id' => $quizid));
//    if ($state->preferredbehaviour == "Graph") {
        echo "document.getElementById('graphIframe').src=\"quizgraphics.php?quizid=".$quizid."\"";
/** Only showing graphics in this version.
    } else {
        echo "document.getElementById('graphIframe').src=\"quizgridview.php?id=".$quizid."\"";
    }
**/
    echo "}\n}\n}\nhttp.send(null);\n}\nreplace();\n</script>";

}
function quiz_java_graphupdate($quizid,$cmid) {
	echo "\n<div id='timemodified' name='-1'></div>";
	echo "\n\n<script type=\"text/javascript\">\nvar http = false;\nvar x=\"\";
			\n\nif(navigator.appName == \"Microsoft Internet Explorer\")
			{\nhttp = new ActiveXObject(\"Microsoft.XMLHTTP\");\n} else {\nhttp = new XMLHttpRequest();}";
		echo "\n\nfunction replace() { ";
		$t = '&t='.time();
		echo "\n x=document.getElementById('timemodified');";
		echo "\n myname = x.getAttribute('name');";//echo "\n alert('myname is ' + myname)";
		
		echo "\nvar t=setTimeout(\"replace()\",10000);\nhttp.open(\"GET\", \"graphicshash.php?id=".$cmid.$t."\", true);";
		echo "\nhttp.onreadystatechange=function() {\nif(http.readyState == 4) {\n if(parseInt(http.responseText) != parseInt(myname)){";
		echo "\n    document.getElementById('graphIframe').src=\"quizgraphics.php?quizid=".$quizid."\"";
		echo "\n x.setAttribute('name', http.responseText)";
		echo "\n}\n}\n}";
		echo "\n http.send(null);";
		echo "\n}\nreplace();";
	echo "\n</script>";
	
}
/**
 * Make the button controls on the instructor interface.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_instructor_buttons($quizid) {
    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the quiz instance.
    if ($mycmid) {
        $querystring = 'id='.$mycmid;
    } else {
        $querystring = '';
    }
    $disabled = "";
    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    if (!quiz_check_active_question($quizid)) {
        $disabled = "disabled=\"disabled\"";
    }

    $myform .= "<input type=\"submit\" value=\"Stop Polling\" name=\"clearQuestion\" ".$disabled."/>\n</form>\n";

    return($myform);
}

/**
 * The function finds out is there a question active?
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_check_active_question($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        return(1);
    } else {
        return(0);
    }
}

/**
 * Create Compadre button for the quiz edit interface.
 * @param int $cmid The quiz id for this quiz instance.
 */
function quiz_show_compadre($cmid) {
    global $CFG;
	$myform = "<form action=\"".$CFG->wwwroot.'/mod/quiz/edit.php?cmid='.$cmid."\" method=\"post\">\n"; 
    $myform .= "\n";
    $myform .= "<input type=\"submit\" value=\"Add/Change Questions\" />\n</form>\n";
    return($myform);
}

/**
 * Toggles the view between the graph or answers to the spreadsheet view.
 * @param string $newstate Gives the state to be displayed.
 */
function quiz_toggle_view($newstate) {
    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the quiz instance.
    if ($mycmid) {
        $querystring = 'id='.$mycmid;
    } else {
        $querystring = '';
    }

    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    $myform .= "<INPUT TYPE=hidden NAME=quiz_view VALUE=\"changeState\">";
    $myform .= "Change View to <input type=\"submit\" value=\"$newstate\" name=\"gridView\"/>\n</form>\n";

    return($myform);

}

/**
 * This function create the form for the instructors (or anyone higher than a student) to view.
 *
 * @param int $quizid The id of this quiz instance
 * @param int $cmid The id of this quiz course module.
 */
function quiz_make_instructor_form($quizid, $cmid) {
    global $CFG;
    global $PAGE;

    $mycmid = optional_param('id', '0', PARAM_INT);// The cmid of the quiz instance.
    if ($mycmid) {
        $querystring = 'id='.$mycmid;
    } else {
        $querystring = '';
    }

    $myform = "<form action=\"?".$querystring."\" method=\"post\">\n";
    $myform .= "\n";
    foreach (quiz_get_questions($quizid) as $items) {
        $previewurl = $CFG->wwwroot.'/question/preview.php?id='.
            $items['id'].'&cmid='.$cmid.
            '&behaviour=deferredfeedback&correctness=0&marks=1&markdp=-2&feedback&generalfeedback&rightanswer&history';
        $myform .= "\n<input type=\"radio\" name=\"question\" value=\"".$items['id']."\" />";
        $myform .= "\n<a href=\"$previewurl\" onclick=\"return quizpopup('".$items['id']."')\" target=\"_blank\">";
        $myform .= quiz_create_preview_icon()."</a>";
        $myform .= "\n<a href=\"quizgraphics.php?question_id=".$items['id']."&quizid=".$quizid."\" target=\"_blank\">[graph]</a>";
        $myform .= "\n".$items['question']."<br /><br />\n";
    }
    if (quiz_check_active_question($quizid)) {
        $myform .= "<input type=\"submit\" value=\"Send Question\" />\n</form>\n";
    } else {
        $myform .= "<input type=\"submit\" value=\"Start Polling\" />\n</form>\n";
    }

    return($myform);
}

/**
 * Get the questions in any context (like the instructor).
 *
 * @param int $quizid The id for this quiz instance.
 */
function quiz_get_questions($quizid) {
    global $DB;
    global $CFG;
    $q = '';
    $pagearray2 = array();
/** Not used because the quiz table doesn't have a questions field any more.
    $quiz = $DB->get_record('quiz', array('id' => $quizid));

    // Get the question ids.
    $questions = explode(",", $ipal->questions);
**/
	$questions = array();
	if($slots = $DB->get_records('quiz_slots', array('quizid' => $quizid))) {
		foreach($slots as $slot){
			$questions[] = $slot->questionid;
		}
	}
    // Get the questions and stuff them into an array.
    foreach ($questions as $q) {
        if (empty($q)) {
            continue;
        }
        $aquestions = $DB->get_record('question', array('id' => $q));
        if (isset($aquestions->questiontext)) {
            // Removing any EJS from the ipal/view.php page. Note: A dot does not match a new line without the s option.
            $aquestions->questiontext = preg_replace("/EJS<ejsipal>.+<\/ejsipal>/s", "EJS ", $aquestions->questiontext);
            $aquestions->questiontext = strip_tags($aquestions->questiontext);
            /** Not implemented yet.
			if (preg_match("/Attendance question for session (\d+)/", $aquestions->name, $matchs)) {
                // Adding form to allow attendance update through ipal.
                $attendancelink = "<input type='button' onclick=\"location.href='attendancerecorded_ipal.php?";
                $attendancelink .= "ipalid=$ipalid";
                $attendancelink .= "&qid=$q";
                $sessid = $matchs[1];
                $attendancelink .= "&sessid=$sessid";
                $attendancelink .= "&update_record=Update_this_attendance_record';\" ";
                $attendancelink .= "value='Update this attendance record'>\n<br />";
                $aquestions->questiontext = $aquestions->questiontext.$attendancelink;
            }
            **/
			$pagearray2[] = array('id' => $q, 'question' => $aquestions->questiontext,
                'answers' => quiz_get_answers($q));
        }
    }
    return($pagearray2);
}

/**
 * Get Answers For a particular question id.
 * @param int $questionid The id of the question that has been answered in this quiz.
 */
function quiz_get_answers($questionid) {
    global $DB;
    global $CFG;
    $line = "";
    $answers = $DB->get_records('question_answers', array('question' => $questionid));
    foreach ($answers as $answers) {
        $line .= $answers->answer;
        $line .= "&nbsp;";
    }
    return($line);
}


/**
 * This function creates the HTML tag for the preview icon.
 */
function quiz_create_preview_icon() {
    global $CFG;
    global $PAGE;
    $previewimageurl = $CFG->wwwroot.'/theme/image.php/'.$PAGE->theme->name.'/core/'.$CFG->themerev.'/t/preview';
    $imgtag = "<img alt='Preview question' class='smallicon' title='Preview question' src='$previewimageurl' />";
    return $imgtag;
}

/**
 * This function finds the current question that is active for the quiz that it was requested from.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_show_current_question($quizid) {
    global $DB;

    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quizid))) {
        $question = $DB->get_record('quiz_active_questions', array('quiz_id' => $quizid));
        $questiontext = $DB->get_record('question', array('id' => $question->question_id));
        // Removing any EJS from the quiz/quiz_view.php page. Note: A dot does not match a new line without the s option.
/**	EJS and Attendance not supported at this time.
        $questiontext->questiontext = preg_replace("/EJS<ejsipal>.+<\/ejsipal>/s", "EJS ", $questiontext->questiontext);
**/
        echo "The current question is -> ".strip_tags($questiontext->questiontext);
/**
        if (preg_match("/Attendance question for session \d+/", $questiontext->name, $matchs)) {
            $ipal = $DB->get_record('ipal', array('id' => $ipalid));
            $timecreated = $ipal->timecreated;
            echo "\n<br /><br />The attendance code is ".
                $question->question_id.$ipalid.substr($timecreated, strlen($timecreated) - 2, 2);
        }
**/
        return(1);
    } else {
        return(0);
    }
}

/**
 * This function sets the question in the database so the client functions can find what quesiton is active.  And it does it fast.
 *
 * @param int $quizid The id of this quiz instance.
 */
function quiz_send_question($quizid) {
    global $DB;
    global $CFG;

    $myquestionid = optional_param('question', 0, PARAM_INT);// The id of the question being sent.

    $quiz = $DB->get_record('quiz', array('id' => $quizid));
    $record = new stdClass();
    $record->id = '';
    $record->course = $quiz->course;
    $record->ipal_id = 0;
    $record->quiz_id = $quiz->id;
    $record->question_id = $myquestionid;
    $record->timemodified = time();
    if ($DB->record_exists('quiz_active_questions', array('quiz_id' => $quiz->id))) {
        $mybool = $DB->delete_records('quiz_active_questions', array('quiz_id' => $quiz->id));
    }
    $lastinsertid = $DB->insert_record('quiz_active_questions', $record);
/** Firebase is not supported in this version
    if (($ipal->mobile == 1) || ($ipal->mobile == 3)) {
        ipal_refresh_firebase($ipalid);
    }
**/
}
