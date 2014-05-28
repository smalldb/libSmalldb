The Big Picture
===============

%Smalldb operates on possibly infinite space of state machines. Instead of
creating a machine instance, a transition from not-exists state is invoked;
deletion is transition to not-exists state. Each machine instance is
represented by a record in database. Missing record means the machine is in
not-exists state.

Machines are always of some type. Particular machine is identified by global
ID, which is usualy composed of machine type and primary key.


Components of %Smalldb
----------------------

  * **Backend** maintains access to the space of machines by creating
    References, creates Machines when required and passes global context around
    (i.e. database connection). There is usualy only one instance per
    application.

  * **Machine** implements behavior of all state machines of given type. There
    is one instance of AbstractMachine per machine type.

  * **Reference** is syntactic sugar used to easily access machine instances.
    Method call on Reference invokes given transition, state and machine
    properties are available as member variables of the Reference. The
    Reference is similar to active record pattern, but semantic is different.


%Smalldb State Machines
-----------------------

%Smalldb state machine is nondeterministic finite automaton combined with Kripke
structure. Each machine has finite set of states, finite set of transitions
between these states and set of named properties (key-value pairs).

State of a state machine is not stored directly. It is retrieved using state
function which is mapping from all possible sets of properties to states. This
mapping is one-way only. This approach is very similar to Kripke structures.

Nondeterminism represents possibility of failure. There may be multiple
transitions of the same name originating from the same state. When transition
is invoked, it is not known which one will be used, however, transition will
always end in one of the target states. Which state it will be depends on
result of operation represented by transition. This behavior can be also
represented using deterministic automaton with guards, where values of guards
are not known in advance. Point is that the result of transition depends on
external and unknown variables.


Initialization
--------------

%Smalldb backend is initialized during application initialization. Database
connections and similar resources are not created by the backend, but they are
passed to backend's to constructor as properties (member variables) of context.

Such resources are often used somewhere else in the application, so it is more
practical to initialize them separately. This also makes it easier to use
various containers for global stuff.

Machine implementation should have option to specify which resource from the
context should be used. For example there may be more than one database
connection, and `$context->database` cannot be used in both cases.


URL Routing
-----------

\todo Review routing.

State machine space can be two-way mapped to URL space, because state machine
ID is global identifier, just like URL (well, except hostname). This mapping is
done by Backend.

To use this mapping in cascade router it is neccessary to inject the router
with an additional piece of logic, which will create reference from URL and
publish it on router's output.

**Example:** Connect `core/router` to `postproc` output of
`smalldb/router_factory` block and add following routing rule group. The
`postprocessor` option tells router to use %Smalldb router for mathing routes.
See Smalldb\Cascade\RouterFactoryBlock documentation for example of its configuration.

~~~~~
        "smalldb": {
            "defaults": {
            },
            "postprocessor": "smalldb",
            "routes": {
                    "/**": {
                    }
            }
        },
~~~~~
<!--- */ -->

