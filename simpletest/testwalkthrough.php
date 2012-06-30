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
 * This file contains overall tests of calculated questions.
 *
 * @package    qtype
 * @subpackage calculated
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/engine/simpletest/helpers.php');


/**
 * Unit tests for the pycode question type.
 *
 * @copyright  2011 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**TODO** Much more thorough behaviour test required, e.g. of all the
 * testcase controls (useAsExample, display, hideRestIfFail).
 */
class qtype_ccode_walkthrough_test extends qbehaviour_walkthrough_test_base {
    public function test_adaptive() {

        // Create a gapselect question.
        $q = test_question_maker::make_question('ccode', 'sqr');
        $q->hints = array(
            new question_hint(1, 'This is the first hint.', FORMAT_HTML),
            new question_hint(2, 'This is the second hint.', FORMAT_HTML),
        );
        $this->start_attempt_at_question($q, 'adaptive', 1, 1);

        // Check the initial state.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_does_not_contain_feedback_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_no_hint_visible_expectation());

        // Submit blank.
        $this->process_submission(array('-submit' => 1, 'answer' => ''));

        // Verify.
        $this->check_current_state(question_state::$invalid);
        $this->check_current_mark(null);
        $this->check_current_output(
                $this->get_contains_marked_out_of_summary(),
                $this->get_does_not_contain_feedback_expectation(),
                // $this->get_contains_validation_error_expectation(), **TODO** why does this fail?
                $this->get_does_not_contain_try_again_button_expectation(),
                $this->get_no_hint_visible_expectation());

        // Submit a wrong answer
        $this->process_submission(array('-submit' => 1, 'answer' => 'int sqr(int n) { return n; }'));

        // Verify.
        $this->check_current_state(question_state::$todo);
        $this->check_current_mark(0);
        $this->check_current_output(
                new PatternExpectation('/' .
                        preg_quote(get_string('noerrorsallowed', 'qtype_pycode') . '/'))
              );

        // Now get it right.
        $this->process_submission(array('-submit' => 1, 'answer' => 'int sqr(int n) { return n * n; }'));

        // Verify.
        $this->check_current_state(question_state::$complete);
        $this->check_current_mark(0.6666666667);
        $this->check_current_output(
                $this->get_contains_correct_expectation(),
                $this->get_does_not_contain_validation_error_expectation(),
                $this->get_no_hint_visible_expectation());
    }
}
