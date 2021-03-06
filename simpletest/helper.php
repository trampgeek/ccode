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
 * Test helpers for the ccode question type.
 *
 * @package    qtype
 * @subpackage ccode
 * @copyright  2012 Richard Lobb, University of Canterbury
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Test helper class for the ccode question type.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ccode_test_helper extends question_test_helper {
    public function get_test_questions() {
        return array('sqr', 'sqrNoSemicolons', 'helloFunc',
            'copyStdin', 'timeout', 'exceptions', 'strToUpper',
            'strToUpperFullMain', 'stringDelete');
    }

    /**
     * Makes a ccode question asking for a sqr() function
     * @return qtype_ccode_question
     */
    public function make_ccode_question_sqr() {
        question_bank::load_question_definition_classes('ccode');
        $ccode = new qtype_ccode_question();
        test_question_maker::initialise_a_question($ccode);
        $ccode->name = 'Function to square a number n';
        $ccode->questiontext = 'Write a function int sqr(int n) that returns n squared.';
        $ccode->generalfeedback = 'No feedback available for ccode questions.';
        $ccode->testcases = array(
            (object) array('testcode'       => 'printf("%d", sqr(0));',
                           'output'         => '0',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(7));',
                           'output'         => '49',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(-11));',
                           'output'         => '121',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0),
           (object) array('testcode'        => 'printf("%d", sqr(-16));',
                           'output'         => '256',
                           'display'        => 'HIDE',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );
        $ccode->qtype = question_bank::get_qtype('ccode');
        $ccode->unitgradingtype = 0;
        $ccode->unitpenalty = 0.2;
        return $ccode;
    }

    /**
     * Makes a ccode question asking for a sqr() function but without
     * semicolons on the ends of all the printf testcases.
     * @return qtype_ccode_question
     */
    public function make_ccode_question_sqrNoSemicolons() {
        question_bank::load_question_definition_classes('ccode');
        $ccode = new qtype_ccode_question();
        test_question_maker::initialise_a_question($ccode);
        $ccode->name = 'Function to square a number n';
        $ccode->questiontext = 'Write a function int sqr(int n) that returns n squared.';
        $ccode->generalfeedback = 'No feedback available for ccode questions.';
        $ccode->testcases = array(
            (object) array('testcode'       => 'printf("%d", sqr(0))',
                           'output'         => '0',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(7))',
                           'output'         => '49',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => 'printf("%d", sqr(-11))',
                           'output'         => '121',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0),
           (object) array('testcode'        => 'printf("%d", sqr(-16))',
                           'output'         => '256',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );
        $ccode->qtype = question_bank::get_qtype('ccode');
        $ccode->unitgradingtype = 0;
        $ccode->unitpenalty = 0.2;
        return $ccode;
    }


    /**
     * Makes a ccode question to write a function that just print 'Hello <name>'
     * This test also tests multiline expressions.
     * @return qtype_ccode_question
     */
    public function make_ccode_question_helloProg() {
        question_bank::load_question_definition_classes('ccode');
        $ccode = new qtype_ccode_question();
        test_question_maker::initialise_a_question($ccode);
        $ccode->name = 'Program to print "Hello ENCN260"';
        $ccode->questiontext = 'Write a program that prints "Hello ENCN260"';
        $ccode->generalfeedback = 'No feedback available for ccode questions.';
        $ccode->testcases = array(
            (object) array('testcode' => '',
                          'output'    => 'Hello ENCN260 ',
                          'display'   => 'SHOW',
                          'hiderestiffail' => 0,
                          'useasexample'   => 0)
        );
        $ccode->qtype = question_bank::get_qtype('ccode');
        $ccode->unitgradingtype = 0;
        $ccode->unitpenalty = 0.2;
        return $ccode;
    }

    /**
     * Makes a ccode question to write a program that reads n lines of stdin
     * and writes them to stdout.
     * @return qtype_ccode_question
     */
    public function make_ccode_question_copyStdin() {
        question_bank::load_question_definition_classes('ccode');
        $ccode = new qtype_ccode_question();
        test_question_maker::initialise_a_question($ccode);
        $ccode->name = 'Function to copy n lines of stdin to stdout';
        $ccode->questiontext = 'Write a function copyLines(n) that reads stdin to stdout';
        $ccode->generalfeedback = 'No feedback available for ccode questions.';
        $ccode->testcases = array(
            (object) array('testcode' => '',
                          'stdin'     => '',
                          'output'    => '',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => '',
                          'stdin'     => "Line1\n",
                          'output'    => "Line1\n",
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => '',
                          'stdin'     => "Line1\nLine2\n",
                          'output'    => "Line1\nLine2\n",
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0)
        );
        $ccode->qtype = question_bank::get_qtype('ccode');
        $ccode->unitgradingtype = 0;
        $ccode->unitpenalty = 0.2;
        return $ccode;
    }


    public function make_ccode_question_strToUpper() {
        question_bank::load_question_definition_classes('ccode');
        $ccode = new qtype_ccode_question();
        test_question_maker::initialise_a_question($ccode);
        $ccode->name = 'Function to convert string to uppercase';
        $ccode->questiontext = 'Write a function void strToUpper(char s[]) that converts s to uppercase';
        $ccode->generalfeedback = 'No feedback available for ccode questions.';
        $ccode->testcases = array(
            (object) array('testcode' => "
#include <stdio.h>
  #include <ctype.h>
char s[] = {'1','@','a','B','c','d','E',';', 0};
strToUpper(s);
printf(\"%s\\n\", s);
",
                          'stdin'     => '',
                          'output'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => "
  #include <stdio.h>
#include <ctype.h>
char s[] = {'1','@','A','b','C','D','e',';', 0};
strToUpper(s);
printf(\"%s\\n\", s);
",
                          'stdin'     => '',
                          'output'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0)
        );
        $ccode->qtype = question_bank::get_qtype('ccode');
        $ccode->unitgradingtype = 0;
        $ccode->unitpenalty = 0.2;
        return $ccode;
    }


    public function make_ccode_question_strToUpperFullMain() {
        // A variant of strToUpper where test cases include an actual main func
        question_bank::load_question_definition_classes('ccode');
        $ccode = new qtype_ccode_question();
        test_question_maker::initialise_a_question($ccode);
        $ccode->name = 'Function to convert string to uppercase';
        $ccode->questiontext = 'Write a function void strToUpper(char s[]) that converts s to uppercase';
        $ccode->generalfeedback = 'No feedback available for ccode questions.';
        $ccode->testcases = array(
            (object) array('testcode' => "
  #include <stdio.h>
#include <ctype.h>
int main() {
  char s[] = {'1','@','a','B','c','d','E',';', 0};
  strToUpper(s);
  printf(\"%s\\n\", s);
  return 0;
}
",
                          'stdin'     => '',
                          'output'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0),
            (object) array('testcode' => "
  #include <stdio.h>
#include <ctype.h>
int main() {
  char s[] = {'1','@','A','b','C','D','e',';', 0};
  strToUpper(s);
  printf(\"%s\\n\", s);
  return 0;
}
",
                          'stdin'     => '',
                          'output'    => '1@ABCDE;',
                          'display'   => 'SHOW',
                          'useasexample'   => 0,
                          'hiderestiffail' => 0)
        );
        $ccode->qtype = question_bank::get_qtype('ccode');
        $ccode->unitgradingtype = 0;
        $ccode->unitpenalty = 0.2;
        return $ccode;
    }

    /**
     * Makes a ccode question asking for a stringDelete() function that
     * deletes from a given string all characters present in another
     * string
     * @return qtype_ccode_question
     */
    public function make_ccode_question_stringDelete() {
        question_bank::load_question_definition_classes('ccode');
        $ccode = new qtype_ccode_question();
        test_question_maker::initialise_a_question($ccode);
        $ccode->name = 'Function to delete from a source string all chars present in another string';
        $ccode->questiontext = 'Write a function void stringDelete(char *s, const char *charsToDelete) that takes any two C strings as parameters and modifies the string s by deleting from it all characters that are present in charsToDelete.';
        $ccode->generalfeedback = 'No feedback available for ccode questions.';
        $ccode->testcases = array(
            (object) array('testcode'       => "char s[] = \"abcdefg\";\nstringDelete(s, \"xcaye\");\nprintf(\"%s\\n\", s);",
                           'output'         => 'bdfg',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => "char s[] = \"abcdefg\";\nstringDelete(s, \"\");\nprintf(\"%s\\n\", s);",
                           'output'         => 'abcdefg',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 1),
            (object) array('testcode'       => "char s[] = \"aaaaabbbbb\";\nstringDelete(s, \"x\");\nprintf(\"%s\\n\", s);",
                           'output'         => 'aaaaabbbbb',
                           'display'        => 'SHOW',
                           'hiderestiffail' => 0,
                           'useasexample'   => 0)
        );
        $ccode->qtype = question_bank::get_qtype('ccode');
        $ccode->unitgradingtype = 0;
        $ccode->unitpenalty = 0.2;
        return $ccode;
    }

    /**
     * Makes a ccode question that loops forever, to test sandbox timeout.
     * @return qtype_ccode_question
     *
    public function make_ccode_question_timeout() {
        question_bank::load_question_definition_classes('ccode');
        $ccode = new qtype_ccode_question();
        test_question_maker::initialise_a_question($ccode);
        $ccode->name = 'Program to generate a timeout';
        $ccode->questiontext = 'Write a program that loops forever';
        $ccode->generalfeedback = 'No feedback available for ccode questions.';
        $ccode->testcases = array(
            (object) array('testcode' => '',
                          'stdin'     => '',
                          'output'    => '',
                          'display'  => 'SHOW', 'hiderestiffail' => 0)
        );
        $ccode->qtype = question_bank::get_qtype('ccode');
        $ccode->unitgradingtype = 0;
        $ccode->unitpenalty = 0.2;
        return $ccode;
    }

     */
}
