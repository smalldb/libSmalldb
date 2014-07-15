State Machine Definitions
=========================

Point of %Smalldb is to make application logic easier to specify. This means it
must be easy to define state machines. There are three ways of defining a state
machine:

  1. PHP Class inherited from Smalldb\StateMachine\AbstractMachine.
  2. JSON file
  3. JSON file + GraphML file

Since transition implementations require some PHP code anyway, there is no way
to remove assigned PHP class completely (at least not for now). However, in
common case it is not practical to define state machine in PHP code. Easier way
is to use graphical editors like yEd to create GraphML file describing the
state machine.

To make state machine diagram easy to create using existing tools, it is
possible to "include" a GraphML file into JSON file. The GraphML file is parsed
and converted into internal structure which is equivalent to the structure of
the JSON file, then both files are merged together. If some option is defined
in both files, value from JSON file is used, so it is easy to overcome
imperfections of used editor.

@see Examples of state machine definitions are in the
     `doc/examples/statemachine/` directory.

