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
 * Unit tests for the ccode question definition class.
 *
 * @package    qtype
 * @subpackage ccode
 * @copyright  2011 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');
require_once($CFG->dirroot . '/question/type/ccode/question.php');

require_once($CFG->dirroot . '/local/onlinejudge/judgelib.php');

/**
 * Unit tests for the matching question definition class.
 */
class qtype_ccode_question_test extends UnitTestCase {
    public function setUp() {
        $this->qtype = new qtype_ccode_question();
        $this->goodcode = "int sqr(int n) ( return n * n; }\n";
    }
    

    public function tearDown() {
        $this->qtype = null;
    }
    
    
    public function test_get_question_summary() {
        $q = test_question_maker::make_question('ccode', 'sqr');
        $this->assertEqual('Write a function int sqr(int n) that returns n squared.',
                $q->get_question_summary());
    }
    

    public function test_summarise_response() {
        $s = $this->goodcode;
        $q = test_question_maker::make_question('ccode', 'sqr');
        $this->assertEqual($s,
               $q->summarise_response(array('answer' => $s)));
    }
    
    // Now test sandbox
    
    public function test_supported_langs() {
        $langs = judge_sandbox::get_languages();
        $this->assertTrue(isset($langs['c_sandbox']));
        $this->assertTrue(isset($langs['cpp_sandbox']));
    }
    
    public function test_compile_error() {
        $taskId = onlinejudge_submit_task(1, 99, 'c_sandbox', 
                array('main.c'=>"int main() { return 0; /* No closing brace */"),
                'questiontype_ccode', array());
        $task = onlinejudge_judge($taskId);
        $this->assertEqual($task->status, ONLINEJUDGE_STATUS_COMPILATION_ERROR);
    }
    
    public function test_good_hello_world() {
         $taskId = onlinejudge_submit_task(1, 99, 'c_sandbox', 
                array('main.c'=>"#include <stdio.h>\nint main() { printf(\"Hello world!\\n\");return 0;}\n"),
                'questiontype_ccode', array('output' => 'Hello world!'));
        $task = onlinejudge_judge($taskId);
        $this->assertEqual($task->status, ONLINEJUDGE_STATUS_ACCEPTED);
    }
    
    public function test_bad_hello_world() {
         $taskId = onlinejudge_submit_task(1, 99, 'c_sandbox', 
                array('main.c'=>"#include <stdio.h>\nint main() { printf(\"Hello world\\n\");return 0;}\n"),
                'questiontype_ccode', array('output' => 'Hello world!'));
        $task = onlinejudge_judge($taskId);
        $this->assertEqual($task->status, ONLINEJUDGE_STATUS_WRONG_ANSWER);
        $this->assertEqual($task->stdout, "Hello world");
        $this->assertEqual($task->output, "Hello world!");
    } 
    
    public function test_timelimit_exceeded() {
         $taskId = onlinejudge_submit_task(1, 99, 'c_sandbox', 
                array('main.c'=>"int main() { while(1) {} }\n"),
                'questiontype_ccode', array('output' => 'Hello world!'));
        $task = onlinejudge_judge($taskId);
        $this->assertEqual($task->status, ONLINEJUDGE_STATUS_TIME_LIMIT_EXCEED);
    }
    
    public function test_runtime_error() {
         $taskId = onlinejudge_submit_task(1, 99, 'c_sandbox', 
                array('main.c'=>"int main() { int buff[1]; int *p = buff; while (1) { *p++ = 0; }}\n"),
                'questiontype_ccode', array('output' => 'Hello world!'));
        $task = onlinejudge_judge($taskId);
        $this->assertEqual($task->status, ONLINEJUDGE_STATUS_RUNTIME_ERROR);
    }
   
    // Now test ccode's judging of questions (via sandbox of course)
    
    public function test_grade_response_right() {
        // Check grading of a "write-a-function" question with multiple
        // test cases and a correct solution
        $q = test_question_maker::make_question('ccode', 'sqr');
        $response = array('answer' => $this->_good_sqr_code());
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 1); // Mark
        $this->assertEqual($result[1], question_state::$gradedright);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        foreach ($testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }
    
    
    public function test_missing_semicolon() {
        // Check that a missing semicolon in a simple printf test is reinsterted
        // Check grading of a "write-a-function" question with multiple
        // test cases and a correct solution
        $q = test_question_maker::make_question('ccode', 'sqrNoSemicolons');
        $response = array('answer' => $this->_good_sqr_code());
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 1); // Mark
        $this->assertEqual($result[1], question_state::$gradedright);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        foreach ($testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }
    
    
    public function test_grade_response_compile_errors() {
        // Check grading of a "write-a-function" question with multiple
        // test cases and two different bad solutions, one causing a link
        // error and the other a compile error. Both get treated as
        // compile errors.
        $this->checkCompileErrors('int square(int n) { return n * n; }', 'COMPILE ERROR');
        $this->checkCompileErrors('int sqr(int n) { return n * n }', 'COMPILE ERROR');
    }
        
        
    private function checkCompileErrors($code, $expectedError) {
        $q = test_question_maker::make_question('ccode', 'sqr');
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0); // Mark
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $n = count($testResults);
        $this->assertEqual($n, 1);  // Only a single test result should be returned
        $tr = $testResults[0];
        $this->assertFalse($tr->isCorrect);
        $this->assertEqual(substr($tr->output, 0, strlen($expectedError)), $expectedError);
    }
    
    
    public function test_grade_response_wrong_ans() {
        // Check grading of a "write-a-function" question with multiple
        // test cases when the solution is right for some of the tests only.
        $q = test_question_maker::make_question('ccode', 'sqr');
        $code = "int sqr(int x) { return x >= 0 ? x * x : -x * x; }";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);  // Should be zero mark
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $n = count($testResults);
        $this->assertTrue($testResults[0]->isCorrect);
        $this->assertTrue($testResults[1]->isCorrect);
        $this->assertFalse($testResults[2]->isCorrect);
        $this->assertFalse($testResults[3]->isCorrect);
    } 
    

    public function test_grade_runtime_error() {
        // Check grading of a "write-a-function" question with multiple
        // test cases when the solution is right for some of the tests but
        // gives a runtime error for a later one. [Should force a re-run
        // without merging]
        $q = test_question_maker::make_question('ccode', 'sqr');
        $code = "int sqr(int n) { return n != -16 ? n * n : *((int*) 0); }\n";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertTrue($testResults[0]->isCorrect);
        $this->assertEqual($testResults[0]->output, "0");
        $this->assertTrue($testResults[1]->isCorrect);
        $this->assertEqual($testResults[1]->output, "49");
        $this->assertTrue($testResults[2]->isCorrect);
        $this->assertEqual($testResults[2]->output, "121");
        $this->assertFalse($testResults[3]->isCorrect);
        $this->assertEqual(substr($testResults[3]->output, 0, strlen("RUNTIME ERROR")), "RUNTIME ERROR");
    }
    
    
    
    public function test_copy_stdin_to_stdout() {
        // Test of a question that copies standard in to standard out.
        $q = test_question_maker::make_question('ccode', 'copyStdin');
$code =
"#include <stdio.h>
int main() {
    int c;
    while (1) {
       c = getchar();
       if (c == EOF) break;
       putchar(c);
    }
    return 0;
}";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 1);
        $this->assertEqual($result[1], question_state::$gradedright);
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 3);
        foreach ($testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }
    
    
    public function test_str_to_upper() {
        // Check grading of a function with more complicated test code,
        // requiring #includes to be extracted and test cases to be in
        // separate blocks.
        $q = test_question_maker::make_question('ccode', 'strToUpper');
$code =
"void strToUpper(char s[]) {
    int i = 0;
    while (s[i]) {
       s[i] = toupper(s[i]);
       i++;
    }
}
";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 1);
        $this->assertEqual($result[1], question_state::$gradedright);
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 2);
        foreach ($testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }
    
    public function test_str_to_upper_full_main() {
        // This version has a full main function in the test
        $q = test_question_maker::make_question('ccode', 'strToUpperFullMain');
$code =
"void strToUpper(char s[]) {
    int i = 0;
    while (s[i]) {
       s[i] = toupper(s[i]);
       i++;
    }
}
";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 1);
        $this->assertEqual($result[1], question_state::$gradedright);
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 2);
        foreach ($testResults as $tr) {
            $this->assertTrue($tr->isCorrect);
        }
    }
    
    public function test_illegal_function_call() {
        // Check grading of a "write-a-function" question with multiple
        // test cases when the solution tried to do an illegal function call
        // (fork).
        $q = test_question_maker::make_question('ccode', 'sqr');
        $code =
"#include <linux/unistd.h>
#include <unistd.h>
int sqr(int n) {
    if (n == 0) return 0;
    else {
        int i = 0;
        for (i = 0; i < 20; i++) 
            fork();
        return 0;
    }
}";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 2);
        $this->assertTrue($testResults[0]->isCorrect);
        $this->assertEqual($testResults[0]->output, "0");
        $this->assertFalse($testResults[1]->isCorrect);
        $this->assertEqual(substr($testResults[1]->output, 0,
                strlen("CALL TO A RESTRICTED LIBRARY FUNCTION.")),
                "CALL TO A RESTRICTED LIBRARY FUNCTION.");
    } 
    
        
    private function _good_sqr_code() {
        return "int sqr(int n) { return n * n; }\n";
    }
}

