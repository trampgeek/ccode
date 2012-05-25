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
///////////////////
/// ccode ///
///////////////////
/// CCODE QUESTION TYPE CLASS //////////////////
// A ccode question consists of a specification for piece of program
// code, which might be a function or a complete program or (possibly in the
// future) a fragment of code. 
// The student's response must be source code that defines
// the specified function. The student's code is executed by
// a set of test cases, all of which must pass for the question
// to be marked correct. The code execution takes place in an external
// Moodle onlinejudge plugin (see
// https://github.com/hit-moodle/moodle-local_onlinejudge), so potentially
// many different languages can be supported.
// There are no part marks -- the question is marked 100% or
// zero. It is expected that each ccode question will have its
// own submit button and students will keep submitting until
// they pass all tests, so that their mark will be based on
// the number of submissions and the penalty per wrong
// submissions.

/**
 * @package 	qtype
 * @subpackage 	ccode
 * @copyright 	&copy; 2011 Richard Lobb
 * @author 	Richard Lobb richard.lobb@canterbury.ac.nz
 */

require_once($CFG->dirroot . '/question/type/progcode/questiontype.php');

/**
 * qtype_ccode extends the base question_type to ccode-specific functionality.
 * A ccode question requires an additional DB table, question_ccode_testcases,
 * that contains the definitions for the testcases associated with a ccode
 * question. There are an arbitrary number of these, so they can't be handled
 * by adding columns to the ccode_question table.
 */
class qtype_ccode extends qtype_progcode {

    public function name() {
        return 'ccode';
    }
}