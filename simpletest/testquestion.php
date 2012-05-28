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
    
    
    public function test_grade_response_compile_errors() {
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
       
    
    private function _good_sqr_code() {
        return "int sqr(int n) { return n * n; }\n";
    }
    
    
    public function test_grade_response_wrong_ans() {
        $q = test_question_maker::make_question('ccode', 'sqr');
        $code = "int sqr(int x) { return x == 0 ? 1 : 3 * x; }";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);  // Should be zero mark
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $n = count($testResults);
        $i = 0;
        foreach ($testResults as $tr) {
            $i++;
            $this->assertEqual($tr->isCorrect, FALSE);
        }
    } 
  
    /*
    public function test_grade_syntax_error() {
        $q = test_question_maker::make_question('ccode', 'sqr');
        $code = "def sqr(x): return x  x";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 1);
        $this->assertEqual($testResults[0]->outcome, 'Syntax Error');
    }
    
    
    public function test_grade_runtime_error() {
        $q = test_question_maker::make_question('ccode', 'sqr');
        $code = "def sqr(x): return x * y";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 1);
        $this->assertEqual($testResults[0]->outcome, 'Runtime Error');
    }  

    
    public function test_grade_delayed_runtime_error() {
        $q = test_question_maker::make_question('ccode', 'sqr');
        $code = "def sqr(x):\n  if x != 11:\n    return x * x\n  else:\n    return y";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 3);
        $this->assertEqual($testResults[2]->outcome, 'Runtime Error');
    }  
    
    
    public function test_triple_quotes() {
        $q = test_question_maker::make_question('ccode', 'sqr');
        $code = <<<EOCODE
def sqr(x):
    """This is a function
       that squares its parameter"""
    return x * x
EOCODE;
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 1);
        $this->assertEqual($result[1], question_state::$gradedright);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $n = count($testResults);
        $i = 0;
        foreach ($testResults as $tr) {
            $i++;
            $this->assertEqual($tr->outcome, 'Yes');
            $this->assertEqual(trim($tr->expected), trim($tr->output));
            $this->assertEqual($tr->mark, 1);
            $this->assertEqual($tr->hidden, $i == $n ? 1 : 0); // last one hidden
        }
    }
    
    
    public function test_helloFunc() {
        // Check a question type with a function that prints output
        $q = test_question_maker::make_question('ccode', 'helloFunc');
        $code = "def sayHello(name):\n  print 'Hello ' + name";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 1);
        $this->assertEqual($result[1], question_state::$gradedright);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 4);
    } 
    
    
    public function test_copyStdin() {
        // Check a question that reads stdin and writes to stdout
        $q = test_question_maker::make_question('ccode', 'copyStdin');
        $code = <<<EOCODE
def copyStdin(n):
  for i in range(n):
    line = raw_input()
    print line
EOCODE;
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 4);
        $this->assertEqual($testResults[0]->outcome, 'Yes');
        $this->assertEqual($testResults[1]->outcome, 'Yes');
        $this->assertEqual($testResults[2]->outcome, 'Yes');
        $this->assertEqual($testResults[3]->outcome, 'Runtime Error');
     }
     
     public function test_timeout() {
         // Check a question that loops forever. Should cause sandbox timeout
        $q = test_question_maker::make_question('ccode', 'timeout');
        $code = "def timeout():\n  while (1):\n    pass";
        $response = array('answer' => $code);
        $result = $q->grade_response($response);
        $this->assertEqual($result[0], 0);
        $this->assertEqual($result[1], question_state::$gradedwrong);
        $this->assertTrue(isset($result[2]['_testresults']));
        $testResults = unserialize($result[2]['_testresults']);
        $this->assertEqual(count($testResults), 1);
        $this->assertEqual($testResults[0]->outcome, 'Runtime Error');
        $this->assertEqual($testResults[0]->output, 'SIGTERM (timeout or too much memory?)');
     } 
     
     */  
}

