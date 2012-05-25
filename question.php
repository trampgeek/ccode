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
 * ccode question definition classes.
 * A subclass of progcode question for quiz questions in C.
 *
 * @package    qtype
 * @subpackage ccode
 * @copyright  Richard Lobb, 2011, The University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/* Use the onlinejudge assignment module
 * (http://code.google.com/p/sunner-projects/wiki/OnlineJudgeAssignmentType)
 * to provide the judge class and the sandboxing (thought the latter
 * is a separate project -- see http://sourceforge.net/projects/libsandbox/).
 */
require_once($CFG->dirroot . '/local/onlinejudge/judgelib.php');
require_once($CFG->dirroot . '/question/type/progcode/question.php');

/**
 * Represents a 'ccode' question.
 */
class qtype_ccode_question extends qtype_progcode_question {
    
    // Check the correctness of a student's C code given the
    // response and and a set of testCases.
    // Return value is an array of test-result objects.
    // If an error occurs, all further tests are aborted so the returned array may be shorter
    // than the input array
    protected function run_tests($code, $testcases) {
        $nonAbortStatuses = array(
            ONLINEJUDGE_STATUS_ACCEPTED,
            ONLINEJUDGE_STATUS_WRONG_ANSWER,
            ONLINEJUDGE_STATUS_PRESENTATION_ERROR);
            
        $cmid = -1;     // AFAIK, the only thing that matters is that this
                        // number doesn't match the module ID of an active
                        // on-line assignment module.
        $userid = 9999; // Seems relevant only to the onlinejudge asst module
        
        $testResults = array();
        $isAbort = FALSE;
        foreach ($testcases as $testcase) {
            if ($isAbort) {
                break;
            }
            $options = new stdClass();
            $options->input = isset($testcase->stdin) ? $testcase->stdin : '';
            $options->output = $testcase->output;
            $taskId = onlinejudge_submit_task($cmid, $userid, 'c_sandbox',
                array('main.c' => $this->make_test($code, $testcase->testcode)),
                'questiontype_ccode',
                $options);
            $task = onlinejudge_judge($taskId);

            $testresult = new stdClass;
            $testresult->outcome = $task->status;
            $testresult->testcode = $testcase->testcode;
            $testresult->expected = $testcase->output;
            $testresult->mark = $testresult->outcome == ONLINEJUDGE_STATUS_ACCEPTED ? 1.0 : 0.0;
            $testresult->hidden = $testcase->hidden;
            if (in_array($testresult->outcome, $nonAbortStatuses)) {
                $testresult->output = $task->stdout;
            }
            else {
                $isAbort = TRUE;
                $testresult->output = $this->abortMessage($task);
            }
            $testResults[] = $testresult;
            $DB->delete_records('onlinejudge_tasks', array('id'=>$taskId));
            //echo "Result: " . $task->status . "<br>" . $task->stdout . "<br>";
        }

    	return $testResults;
    }
    
    
    // Count the number of errors in the given array of test results.
    // If $hiddenonly is true, count only the errors in the hidden tests
    protected function count_errors($testResults, $hiddenonly = False) {
    	$cnt = 0;
    	foreach ($testResults as $test) {
            if ($test->outcome != ONLINEJUDGE_STATUS_ACCEPTED && (!$hiddenonly || $test->hidden)) {
                $cnt++;
            }
    	}
    	return $cnt;
    }
    
    // Built pseudo output to describe the particular error message from
    // the judge server.
    private function abortMessage($task) {
        $messages = array(
            ONLINEJUDGE_STATUS_COMPILATION_ERROR     => 'Compile error.',
            ONLINEJUDGE_STATUS_MEMORY_LIMIT_EXCEED   => 'Memory limit exceeded.',
            ONLINEJUDGE_STATUS_OUTPUT_LIMIT_EXCEED   => 'Excessive output.',
            ONLINEJUDGE_STATUS_RESTRICTED_FUNCTIONS  => 'Call to a restricted library function.',
            ONLINEJUDGE_STATUS_RUNTIME_ERROR         => 'Runtime error.',
            ONLINEJUDGE_STATUS_TIME_LIMIT_EXCEED     => 'Time limit exceeded.');
        
        if (isset($messages[$task->status])) {
            $message = strtoupper($messages[$task->status]);
            if ($task->status == ONLINEJUDGE_STATUS_COMPILATION_ERROR) {
                $message .= "\n" . $task->compileroutput;
            }
        }
        else {
            $message = "Internal error ({$task->status}), please tell a tutor";
        }
        return $message . "\nFurther testing aborted.";
    }
        
    
    
    // Construct a C test program from the given student code plus the 
    // testcase's test code.
    // There are two basic types of tests:
    // 1. Tests where the student writes the entire program and the test
    //    simply involves running that program with the stdin specified by
    //    the testcase.
    // 2. Tests where the student writes support functions, which are then
    //    tested by a main function defined by, or generated from, the
    //    testcase code.
    //
    // Type 1 tests are identified by the fact that the testcase test code is
    // blank. The code to run is then just the student's code.
    // 
    // Type 2 testing can be further broken down into:
    // (a) Tests where the entire main testing function, plus any support
    //     functions and #includes is supplied within the testcase. In this case
    //     the program to run consists of any #include statements (stripped from
    //     the start of the testcase code) followed by the student's code followed
    //     by the rest of the testcase code. This subcase is identified by a
    //     regular expression pattern match with int main() { ... }.
    // (b) Tests where the testcase code is statements (including at least
    //     one printf) to be included within a generic main function. Here the
    //     test program to run is a single #include <stdio.h>, the student's code
    //     and a main function with the body taken from the testcase.
    //
    private function make_test($studentCode, $testCode) {
        $testLines = explode("\n", $testCode);
        assert (count($testLines) == 1);  // Until I've implemented multiline tests
        $testMain = <<<EOD
#include <stdio.h>
        
$studentCode
        
int main() {
   $testCode;
   return 0;
}
EOD;
        $code = htmlspecialchars(print_r ($testMain, TRUE));
        //echo "<pre>$code</pre>";
        return $testMain;
    }
}
