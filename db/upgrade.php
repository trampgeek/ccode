<?php


function xmldb_qtype_ccode_upgrade($oldversion) {
    global $CFG, $DB;

    $result = TRUE;
    $dbman = $DB->get_manager();

    if ($oldversion < 2010121022) {

        // Define table question_ccode_testcases to be created
        $table = new xmldb_table('question_ccode_testcases');

        // Adding fields to table question_ccode_testcases
        $table->add_field('id', XMLDB_TYPE_INTEGER, null, null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('questionid', XMLDB_TYPE_INTEGER, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('expression', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('result', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table question_ccode_testcases
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('questionid', XMLDB_KEY_FOREIGN, array('questionid'), 'questions', array('id'));

        // Conditionally launch create table for question_ccode_testcases
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // ccode savepoint reached
        upgrade_plugin_savepoint(true, 2010121022, 'qtype', 'ccode');
    }

    if ($oldversion < 2010121023) {

        // Define field useasexample to be added to question_ccode_testcases
        $table = new xmldb_table('question_ccode_testcases');
        $field = new xmldb_field('useasexample', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0', 'result');

        // Conditionally launch add field useasexample
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ccode savepoint reached
        upgrade_plugin_savepoint(true, 2010121023, 'qtype', 'ccode');
    }

    if ($oldversion < 2010121024) {

        // Define field hidden to be added to question_ccode_testcases
        $table = new xmldb_table('question_ccode_testcases');
        $field = new xmldb_field('hidden', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, null, null, '0', 'result');

        // Conditionally launch add field useasexample
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ccode savepoint reached
        upgrade_plugin_savepoint(true, 2010121024, 'qtype', 'ccode');
    }

    if ($oldversion < 2011121545) {

        $table = new xmldb_table('question_ccode_testcases');

        // Rename expression to testcode
        $field = new xmldb_field('expression', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'testcode');
        }

        // Rename result to output
        $field = new xmldb_field('result', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'output');
        }

        // Add field stdin
        $field = new xmldb_field('stdin', XMLDB_TYPE_TEXT, null, null, null, null, null);
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // ccode savepoint reached
        upgrade_plugin_savepoint(true, 2011121545, 'qtype', 'ccode');
    }

    // Version of 31 January 2012 modifies all existing questions to use
    // 'print f(x)' type tests rather than the shell-input version with just f(x)

    if ($oldversion < 2012013101) {
        $rs = $DB->get_recordset_sql('
                SELECT * from {question_ccode_testcases}');
        foreach ($rs as $record) {
            $testInput = trim($record->testcode);
            if ($testInput &&
                strpos($testInput, "\n") === FALSE &&
                strpos($testInput, 'print ') !== 0 ) {
                // Single line input not starting with print: a candidate for update
                    $record->testcode = "print " . $testInput;
                    $matches = array();
                    if (preg_match("|'(.*)'|", $record->output, $matches)) {
                        $record->output = $matches[1];
                    }
                    $DB->update_record('question_ccode_testcases', $record);
            }
        }
        $rs->close();
        // ccode savepoint reached
        upgrade_plugin_savepoint(true, 2012013101, 'qtype', 'ccode');
    }

    // Fix up bug in last upgrade -- replace all 'print print' occurrences
    // with a single print

    if ($oldversion < 2012013102) {
        $rs = $DB->get_recordset_sql('
                SELECT * from {question_ccode_testcases}');
        foreach ($rs as $record) {
            $testInput = trim($record->testcode);
            if ($testInput &&
                strpos($testInput, 'print print') === 0 ) {
                // Input starting with 'print print' -- oops
                    $record->testcode = substr($testInput, 6);
                    $DB->update_record('question_ccode_testcases', $record);
            }
        }
        $rs->close();
        // ccode savepoint reached
        upgrade_plugin_savepoint(true, 2012013102, 'qtype', 'ccode');
    }

    if ($oldversion < 2012062902) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('question_ccode_testcases');
        $displayField = new xmldb_field('display', XMLDB_TYPE_CHAR, 40, null, TRUE, null, 'SHOW');
        $dbman->add_field($table, $displayField);
        $hideRestIfFail = new xmldb_field('hiderestiffail', XMLDB_TYPE_INTEGER, 1, TRUE, TRUE, null, 0);
        $dbman->add_field($table, $hideRestIfFail);
        $DB->set_field_select('question_ccode_testcases', 'display','HIDE', 'hidden');
        $hiddenField = new xmldb_field('hidden', XMLDB_TYPE_INTEGER, null, null, null, null, null);
        $dbman->drop_field($table, $hiddenField);
        upgrade_plugin_savepoint(true, 2012062902, 'qtype', 'ccode');
    }

        // New version increases stdin and output field sizes to medium text
    if ($oldversion < 2012073001) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('question_ccode_testcases');
        $stdin = new xmldb_field('stdin', XMLDB_TYPE_TEXT, 'medium');
        $output = new xmldb_field('output', XMLDB_TYPE_TEXT, 'medium');
        $dbman->change_field_precision($table, $stdin);
        $dbman->change_field_precision($table, $output);
        upgrade_plugin_savepoint(true, 2012073001, 'qtype', 'ccode');
    }

    return $result;
}
?>