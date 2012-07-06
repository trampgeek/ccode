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
require_once($CFG->dirroot . '/question/type/pycode/progcode/question.php');

/**
 * Represents a 'ccode' question.
 */
class qtype_ccode_question extends qtype_progcode_question {
    
    const SEPARATOR = '====+=+=+=+====';
    
    // Check the correctness of a student's C code given the
    // response and and a set of testCases.
    // Return value is an array of test-result objects.
    // If an error occurs, all further tests are aborted so the returned array may be shorter
    // than the input array.
    // To reduce the number of separate compilations where there are
    // multiple test cases, each with different test code, an attempt is made
    // to bundle all tests into one run. If this fails with a runtime exception,
    // the tests are run separately. Otherwise, the results are expanded
    // into a set of individual pseudo test runs.
    protected function run_tests($code, $testCases) {      
        $testResults = array();
        list ($merged, $pseudoTestCase) = $this->merge_tests_if_possible($testCases);
        
        if ($merged) {
            list ($outcome, $testResult) = $this->run_one_test($code, $pseudoTestCase);
            if ($outcome == 0) {
                $testResults = $this->split_results($testResult, $testCases);
            }
            elseif ($outcome == 1) { // Compilation error. No point in trying again unmerged
                $testResults[] = $testResult;
            }
            else {
                $merged = False;  // If runtime error, force a retry on each individual test
            }

        }
        if (!$merged) {  // Either we didn't merge the tests or we did but got a runtime error
            foreach ($testCases as $testCase) {
                list ($outcome, $testResult) = $this->run_one_test($code, $testCase);
                $testResults[] = $testResult;
                if ($outcome != 0) {
                    break;
                }
            }
        }

    	return $testResults;
    }
    
    
    private function run_one_test($studentCode, $testCase) {
        // Run one test through the online judge. The result is a 2-element
        // array containing an outcome and a standard testResult
        // object which has attributes isCorrect and output where
        // output is the actual output.
        // The outcome is either 0 for a normal run, 1 for a compilation error
        // or 2 for a runtime error.
        $cmid = 0;      // AFAIK, the only thing that matters is that this
                        // number doesn't match the module ID of an active
                        // on-line assignment module.
        $userid = 9999; // Seems relevant only to the onlinejudge asst module
        $nonAbortStatuses = array(
            ONLINEJUDGE_STATUS_ACCEPTED,
            ONLINEJUDGE_STATUS_WRONG_ANSWER,
            ONLINEJUDGE_STATUS_PRESENTATION_ERROR);
        $is_abort = FALSE;
        $options = new stdClass();
        $options->input = isset($testCase->stdin) ? $testCase->stdin : '';
        $options->output = $testCase->output;
        $test_prog = $this->make_test($studentCode, $testCase->testcode);
        $taskId = onlinejudge_submit_task($cmid, $userid, 'c_warn2err_sandbox',
            array('main.c' => $test_prog),
            'questiontype_ccode',
            $options);
        $task = onlinejudge_judge($taskId);

        $testResult = new stdClass;
        $testResult->isCorrect = $task->status == ONLINEJUDGE_STATUS_ACCEPTED;
        if (in_array($task->status, $nonAbortStatuses)) {
            $testResult->output = $task->stdout;
            $outcome = 0;
        }
        else {
            $testResult->output = $this->abortMessage($task);
            $outcome = $task->status == ONLINEJUDGE_STATUS_COMPILATION_ERROR ? 1 : 2;
        }

        if (function_exists('onlinejudge_delete_task_now')) {
            onlinejudge_delete_task_now($taskId);
        }
        else {
            trigger_error("Online Judge's delete_task_now function not implemented. ".
                    " Junk will be left in mdl_files and mdl_onlinejudge_tasks tables.",
                    E_USER_WARNING);
        }
        return array($outcome, $testResult);
    }
    
    
    private function split_results($testResult, $testCases) {
        // Split the result of a run from a set of merged testcases into a 
        // set of individual results. Should only be called if the run
        // did not abort from a syntax error, exception etc.

        $outputs = explode($this::SEPARATOR . "\n", $testResult->output);
        $testResults = array();
        assert(count($testCases) == count($outputs));
        $i = 0;
        foreach($testCases as $testCase) {
            $testResult = new stdClass();
            $testResult->output = $got = $outputs[$i];
            $expected = $testCase->output;
            $cleanGot = $this->clean($got);
            $cleanExpected = $this->clean($expected);
            $testResult->isCorrect = ($this->clean($got) == $this->clean($expected));
            $testResults[] = $testResult;
            $i++;
        }

        return $testResults;
    }
    
  
    
    // Built pseudo output to describe the particular error message from
    // the judge server.
    private function abortMessage($task) {
        $messages = array(
            ONLINEJUDGE_STATUS_COMPILATION_ERROR     => 'Compile error.',
            ONLINEJUDGE_STATUS_MEMORY_LIMIT_EXCEED   => 'Memory limit exceeded.',
            ONLINEJUDGE_STATUS_OUTPUT_LIMIT_EXCEED   => 'Excessive output.',
            ONLINEJUDGE_STATUS_RESTRICTED_FUNCTIONS  => 'Call to a restricted library function.',
            ONLINEJUDGE_STATUS_ABNORMAL_TERMINATION  => 'Bad returncode from main function.',
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
    // There are two types of tests:
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
    // Type 2 tests all into two subclasses:
    // (a) Each testcase code is a full program, lacking only the student's
    //     function declarations. In this case, the student's code is inserted
    //     into each of the test programs after any preprocessor commands.
    // (b) The testcase code is code fragments (including at least one output
    //     statement) to be included within a generic main function. Here the
    //     test program built from:
    //        #include <stdio.h>
    //        all preprocessor statements
    //        the student's code
    //        a main function with the body being all the non-preprocessor test
    //            statements.
    //
    // Note that this function may be called for each individual test case
    // or for the merged set of testcases if merge_tests_if_possible succeeded.

    private function make_test($studentCode, $testCode) {
        if (trim($testCode) == '') {
            $testMain = $studentCode;
        }
        else {  
            list ($preprocessorLines, $rest) = $this->separate_preprocessor_lines($testCode);
            $stdio = "#include <stdio.h>"; // Every home should have one
            if (!in_array($stdio, $preprocessorLines)) {
                array_unshift($preprocessorLines, $stdio);
            }
            
            $preprocString = implode("\n", $preprocessorLines) . "\n";
            $restAsString = implode("\n", $rest);
            if ($this->contains_main($restAsString)) {
                $testMain = $preprocString . $studentCode . "\n" . $restAsString;
            }
            else {
                $testMain = $preprocString . $studentCode . 
                                "\nint main() {\n$restAsString\nreturn 0;\n}\n";
            }
        }
        
        return $testMain;
    }
    
    
    private function merge_tests_if_possible($testCases) {
        // If all testcases are non-empty and no main function is found
        // in any of the tests, merge all the tests into
        // a single pseudo testcase in which a special separator line
        // is printed between each actual test.
        // Preprocessor lines are pulled out to the start, and each
        // original testcase is made into a block to isolate variable
        // declarations.

        $mergable = True;
        $tests = array();
        $expecteds = array();
        $preprocessorLines = array();
        foreach ($testCases as $testCase) {
            if ($testCase->testcode == "" ||
                    $this->contains_main($testCase->testcode)) {
                $mergable = False;
            }
            else {
                // Add a semicolon to the test code if necessary and make 
                // it a separate block to isolate any variable declarations 
                $code = $this->addSemicolon($testCase->testcode);
                list($preproc, $rest) = $this->separate_preprocessor_lines($code);
                $preprocessorLines = array_merge($preprocessorLines, $preproc);
                $tests[] = "{\n" . implode("\n", $rest) ."\n}\n";
                $expecteds[] = $testCase->output;
            }
        }
        
        if ($mergable && count($testCases) > 0) {
            $test = new stdClass();
            $test->testcode = implode("\n", array_unique($preprocessorLines)) . "\n";
            $test->testcode .= implode("\nputs(\"" . $this::SEPARATOR . "\");\n", $tests);
            $test->output = implode($this::SEPARATOR . "\n", $expecteds);
            return array(True, $test);
        }
        else {
            return array(False, NULL);
        }
    }
    
    
    private function separate_preprocessor_lines($code) {
        // Extract all preprocessor lines into a separate string.
        // Return an array of two arrays, the first containing all preprocessor lines
        // and the second all other lines.
        $preprocessor = array();
        $rest = array();
        $lines = explode("\n", $code);
        foreach ($lines as $line) {
            if (substr(trim($line), 0, 1) == '#') {
                $preprocessor[] = $line;
            }
            else {
                $rest[] = $line;
            }
        }
        return array($preprocessor, $rest);
    }
   
    
    private function contains_main($s) {
        // True iff the string s contains the declaration of a main function
        return preg_match('|int main\(.*\) *{|', $s);
    }
    
    private function clean($s) {
        // A copy of $s with trailing lines removed and trailing white space
        // from each li untionne removed.
        $bits = explode("\n", $s);
        while (count($bits) > 0) {
            if (trim($bits[count($bits)-1]) == '') {
                array_pop($bits);
            }
            else {
                break;
            }
        }
        $new_s = '';
        foreach ($bits as $bit) {
            while (strlen($bit) > 0 && substr($bit, strlen($bit) - 1, 1) == ' ') {
                $bit = substr($bit, 0, strlen($bit) - 1);
            }
            $new_s .= $bit . "\n";
        }
        
        return $new_s;
    }
    
    
    private function addSemicolon($test) {
        // Return $test with a semicolon appended if it doesn't already have one
        $trimmed = trim($test);
        if ($trimmed[strlen($trimmed) - 1] != ';') {
            return $test . ';';
        }
        else {
            return $test;
        }
    }
        
}
