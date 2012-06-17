ccode
=====

@version 26 May 2012
@author Richard Lobb, University of Canterbury, New Zealand.

ccode is a Moodle question type that requests students to submit C code to
some given specification, e.g. *write a function int sqr(int x) that returns its parameter
squared*. The submission is graded by running a series of testcases of the code
in a sandbox, comparing the output with the expected output. If all testcases
pass, the question is deemed correct, otherwise it is incorrect. ccode is
expected to be run in a special adaptive mode, so the student submits each
question repeatedly, until a correct result is obtained. Their
mark for the question is then determined by the number of submissions and
the per-submission penalty set within Moodle in the usual way.

State of the play
-----------------
As at 17 June 2012, ccode is still under development, though it's sufficiently stable
that I intend using it with a class of 180 students in one month's time.
Most of the code is common to the stable and heavily tested 
[Python version of the plugin](https://github.com/trampgeek/pycode), called
pycode, although ccode uses different
tests and a different sandbox. [pycode has been used for 18 months,
with 3 large classes (300 - 400 students) and has handled
hundreds of thousands of quiz question submissions during laboratories
and term tests.]

A. Installing the C/C++ sandbox
-------------------------------

ccode uses the sandbox that comes with the Moodle online judge assignment
plugin (https://github.com/hit-moodle/moodle-local\_onlinejudge), so this
must be installed first. Installation under Ubuntu was straightforward; I just
followed the install instructions at
https://github.com/hit-moodle/moodle-local\_onlinejudge/blob/master/README.md

Under RedHat, however, it proved a bit more problematic:

1. pcntl is compiled into PHP on my version of Red Hat (RHEL6), so I had to
remove the check to see if it was a loaded extension in
/var/www/html/moodle/local/onlinejudge/cli/judged.php by commenting
out lines 83 - 85:

		if (!extension\_loaded('pcntl') || !extension\_loaded('posix')) {
			cli\_error('PHP pcntl and posix extension must be installed!');
		}
	
2. I had to install php posix, which in RHEL6 is part of php-devel, with

		sudo yum install php-devel


B. Install the pycode plug-in
-----------------------------
Pycode is a Python equivalent of ccode, and was developed before it. It
defines a set of base classes for programming question plugins, referred to
generically as progcode. [The Moodle 2.1 question architecture doesn't allow
for the possibility of abstract question types, so to prevent users from
instantiating the base types, they are hidden inside pycode. This isn't very
satisfactory, but was the suggestion made by Tim Hunt in a forum posting and
I can't immediately think of a better solution.]

Installing pycode and the progcode base classes is done by:

		git clone https://github.com/trampgeek/pycode
		sudo mv pycode <moodle\_base>/question/type/

A problem here is that pycode questions will not be usable unless the pypy
sandbox is also installed -- see the pycode documentation if you wish to
do this. Without the pypy sandbox you won't be able to run 
pycode questions, but *ccode* should still work fine.

C. Install the moodle quiz question behaviour.
----------------------------------------------
A special Moodle quiz question behaviour is required for progcode-based
question types, called adaptive\_adapted\_for_progcode.
It's available as a GIT repo. Install it by:

		git clone https://github.com/trampgeek/adaptive_adapted\_for\_progcode
		sudo mv adaptive_adapted_for_progcode/ <moodle\_base>/question/behaviour/

D. Install the ccode plug-in
----------------------------

Install (at last) the c-code plug itself by:

		git clone https://github.com/trampgeek/ccode
		sudo mv pycode <moodle\_base>/question/type/


E. Test ccode/progcode in Moodle
--------------------------------
* Log in as an administrator
* You should be told there are modules available to update.
* Update them.
* Select Settings > Site administrations > Development > Unit tests
* Run the tests in folder question/type/ccode
* You shouldn't get any errors. If you do, you're in trouble. Panic.


