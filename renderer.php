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
 * Multiple choice question renderer classes.
 *
 * @package    qtype
 * @subpackage ccode
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

define('FORCE_TABULAR_EXAMPLES', TRUE);

require_once($CFG->dirroot . '/local/onlinejudge/judgelib.php');

/**
 * Subclass for generating the bits of output specific to ccode questions.
 *
 * @copyright  Richard Lobb, University of Canterbury.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_ccode_renderer extends qtype_renderer {

    /**
     * Generate the display of the formulation part of the question. This is the
     * area that contains the question text, and the controls for students to
     * input their answers. Some question types also embed bits of feedback, for
     * example ticks and crosses, in this area.
     *
     * @param question_attempt $qa the question attempt to display.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    public function formulation_and_controls(question_attempt $qa, question_display_options $options) {
        $question = $qa->get_question();
        $qtext = $question->format_questiontext($qa);
        $testcases = $question->testcases;
        $examples = array_filter($testcases, function($tc) {
                    return $tc->useasexample;
                });
        if (count($examples) > 0) {
            $qtext .= html_writer::tag('p', 'For example:', array());
            $qtext .= html_writer::start_tag('div', array('class' => 'ccode-examples'));
            $qtext .= $this->formatExamples($examples);
            $qtext .= html_writer::end_tag('div');
        }


        $qtext .= html_writer::start_tag('div', array('class' => 'prompt'));
        $answerprompt = get_string("answer", "quiz") . ': ';
        $qtext .= $answerprompt;
        $qtext .= html_writer::end_tag('div');

        $responsefieldname = $qa->get_qt_field_name('answer');
        $ta_attributes = array(
            'class' => 'ccode-answer',
            'name' => $responsefieldname,
            'id' => $responsefieldname,
            'cols' => 80,
            'rows' => 18,
            'onkeydown' => 'keydown(event, this)',
            'onkeyup' => 'ignoreNL(event)',
            'onkeypress' => 'ignoreNL(event)'
        );

        if ($options->readonly) {
            $ta_attributes['readonly'] = 'readonly';
        }

        $currentanswer = $qa->get_last_qt_var('answer');
        $qtext .= html_writer::tag('textarea', s($currentanswer), $ta_attributes);
        return $qtext;

        // TODO: consider how to prevent multiple submits while one submit in progress
        // (if it's actually a problem ... check first).
    }

    /**
     * Gereate the specific feedback. This is feedback that varies according to
     * the reponse the student gave.
     * This code tries to allow for the possiblity that the question is being
     * used with the wrong (i.e. non-adaptive) behaviour, which would mean that
     * test results aren't available.
     * @param question_attempt $qa the question attempt to display.
     * @return string HTML fragment.
     */
    protected function specific_feedback(question_attempt $qa) {
        $trSerialised = $qa->get_last_qt_var('_testresults');
        if ($trSerialised) {
            $q = $qa->get_question();
            $testCases = $q->testcases;
            $testResults = unserialize($trSerialised);
            if ($this->count_errors($testResults) == 0) {
                $resultsclass = "ccode-test-results-good";
            } else {
                $resultsclass = "ccode-test-results-bad";
            }

            $fb = html_writer::start_tag('div', array('class' => $resultsclass));
            $fb .= html_writer::tag('p', '&nbsp;', array('class' => 'ccode-spacer'));
            // debugging(print_r($testCases, TRUE));
            $fb .= $this->buildResultsTable($testCases, $testResults);

            // Summarise the status of the response in a paragraph at the end.

            $fb .= $this->buildFeedback($testCases, $testResults);
            $fb .= html_writer::end_tag('div');
        } else { // No testresults?! Probably due to a wrong behaviour selected
            $text = get_string('qWrongBehaviour', 'qtype_ccode');
            $fb = html_writer::start_tag('div', array('class' => 'missingResults'));
            $fb .= html_writer::tag('p', $text);
            $fb .= html_writer::end_tag('div');
        }
        return $fb;
    }

    private function buildResultsTable($testCases, $testResults) {
        // First determine which columns are required in the result table
        // by looking for occurrences of testcode and stdin data in the tests.
        
        list($numStdins, $numTests) = $this->countBits($testCases);
        
        $table = new html_table();
        $table->attributes['class'] = 'ccode-test-results';
        $table->head = array();
        if ($numTests) {
            $table->head[] = 'Test';
        }
        if ($numStdins) {
            $table->head[] = 'Stdin';
        }
        $table->head = array_merge($table->head, array('Expected', 'Got', ''));
        
        $tableData = array();
        $testCaseKeys = array_keys($testCases);  // Arbitrary numeric indices. Aarghhh.
        $i = 0;
        foreach ($testResults as $testResult) {
            if (!$testResult->hidden) {
                $tableRow = array();
                $result = $testResult->output;
                if ($numTests) {
                    $tableRow[] = s($testResult->testcode);
                }
                if ($numStdins) {
                    $tableRow[] = s($testCases[$testCaseKeys[$i]]->stdin);
                }
                $tableRow = array_merge($tableRow, array(
                    s($testResult->expected),
                    s($result)
                ));

                $rowWithLineBreaks = array();
                foreach ($tableRow as $col) {
                    $rowWithLineBreaks[] = $this->addLineBreaks($col);
                }
                $rowWithLineBreaks[] = $this->feedback_image($testResult->mark);
                $tableData[] = $rowWithLineBreaks;
            }
            $i++;
        }
        $table->data = $tableData;
        $resultTableHtml = html_writer::table($table);
        return $resultTableHtml;
    }

    // Compute the HTML feedback to give for a given set of testresults
    private function buildFeedback($testCases, $testResults) {
        $lines = array();  // Build a list of lines of output
        if (count($testResults) != count($testCases)) {
            $lines[] = get_string('aborted', 'qtype_ccode');
            $lines[] = get_string('noerrorsallowed', 'qtype_ccode');
        } else {
            $numErrors = $this->count_errors($testResults);
            if ($numErrors > 0) {
                if ($numErrors == $this->count_errors($testResults, True)) {
                    // Only hidden tests were failed
                    $lines[] = get_string('failedhidden', 'qtype_ccode');
                }
                $lines[] = get_string('noerrorsallowed', 'qtype_ccode');
            } else {
                $lines[] = get_string('allok', 'qtype_ccode') .
                        "&nbsp;" . $this->feedback_image(1.0);
                ;
            }
        }

        // Convert list of lines to HTML paragraph

        $para = html_writer::start_tag('p');
        $para .= $lines[0];
        for ($i = 1; $i < count($lines); $i++) {
            $para .= html_writer::empty_tag('br') . $lines[$i];
        }
        $para .= html_writer::end_tag('p');
        return $para;
    }

    // Count the number of errors in the given array of test results.
    // If $hidden_only is true, count only the errors in the hidden tests
    private function count_errors($testResults, $hiddenonly = False) {
        $cnt = 0;
        foreach ($testResults as $test) {
            if ($test->outcome != ONLINEJUDGE_STATUS_ACCEPTED && (!$hiddenonly || $test->hidden)) {
                $cnt++;
            }
        }
        return $cnt;
    }
    
    
    // Format one or more examples
    private function formatExamples($examples) {
        if ($this->allSingleLine($examples) && ! FORCE_TABULAR_EXAMPLES) {
            return $this->formatExamplesOnePerLine($examples);
        }
        else {
            return $this->formatExamplesAsTable($examples);
        }
    }
    
    
    // Return true iff there is no standard input and all output and shell
    // input cases are single line only
    private function allSingleLine($examples) {
        foreach ($examples as $example) {
            if (!empty($example->stdin) ||
                strpos($example->testcode, "\n") !== FALSE ||
                strpos($example->output, "\n") !== FALSE) {
               return FALSE;
            }
         }
         return TRUE;
    }

    
    
    // Return a '<br>' separated list of expression -> result examples.
    // For use only where there is no stdin and shell input is one line only.
    private function formatExamplesOnePerLine($examples) {
       $text = '';
       foreach ($examples as $example) {
            $text .=  $example->testcode . ' &rarr; ' . $example->output;
            $text .= html_writer::empty_tag('br');
       }
       return $text;
    }
    
    
    private function formatExamplesAsTable($examples) {
        $table = new html_table();
        $table->attributes['class'] = 'pycodeexamples';
        list($numStd, $numShell) = $this->countBits($examples);
        $table->head = array();
        if ($numStd) {
            $table->head[] = 'Standard Input';
        }
        if ($numShell) {
            $table->head[] = 'Test';
        }
        $table->head[] = $numStd && $numShell ? 'Total output' : 'Output';
        
        $tableRows = array();
        foreach ($examples as $example) {
            $row = array();
            if ($numStd) {
                $row[] = $this->addLineBreaks(s($example->stdin));
            }
            if ($numShell) {
                $row[] = $this->addLineBreaks(s($example->testcode));
            }
            $row[] = $this->addLineBreaks(s($example->output));
            $tableRows[] = $row;
        }
        $table->data = $tableRows;
        return html_writer::table($table);
    }
    
    
    // Return a count of the number of non-empty stdins and non-empty shell
    // inputs in the given list of test objects or examples
    private function countBits($tests) {
        $numStds = 0;
        $numShell = 0;
        foreach ($tests as $test) {
            if (!empty($test->stdin)) {
                $numStds++;
            }
            if (!empty($test->testcode)) {
                $numShell++;
            }
        }
        return array($numStds, $numShell);
    }
    
    
    
    // Replace all newline chars in a string with HTML line breaks.
    // Also replace spaces with &nbsp;
    private function addLineBreaks($s) {
        return str_replace("\n", "<br />", str_replace(' ', '&nbsp;', $s));
    }

}
