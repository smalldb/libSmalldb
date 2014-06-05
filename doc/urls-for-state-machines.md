URLs for State Machines and HTTP API
====================================

%Smalldb does not enforce any URL scheme, but current implementation is designed
as described in this section. Primary goals are to have RESTful interface and
to be compatible with plain HTML forms.

URL vs. State Machine ID
------------------------

State machine ID is identifier unique within an application. It typicaly
consists of machine type and primary key. The primary key is unique within all
state machine instances of given type (like primary key is unique in SQL
table).

To make state machine ID world-wide unique a server name (a domain) must be
added. So the full URL of state machine instance may look like this:

    http://example.org/entity-type/primary-key

Please note that primary key may be compound so the path may have more than two
pieces. How is the path mapped to state machine space is determined by [backend].

In some applications it may be desired to have all machine instances arranged
in a one large tree, where the path to any single state machine instance does
not contain any information about its type. The [backend] will have to lookup
the tree node in a database when processing URL, but otherwise it makes no
difference.

@note The mapping between state machine space and URL space must be bidirectional.

[backend]: @ref Smalldb\StateMachine\AbstractBackend


Actions and HTTP Methods
------------------------

HTML5 specification describes two HTTP methods: GET and POST. Other HTTP
methods, like PUT or DELETE, are not mentioned in HTML5 specification, so they
cannot be used by HTML forms. And because plain HTML form is the minimal client
for Smalldb, only HTTP GET and HTTP POST is used.

**HTTP GET** represents read operation. No change is performed on server. The only
exception is extending session timeout.

**HTTP POST** represents write operation, which in context of state machine means
invoking a transition (action).

To specify which action should be performed a query parameter "action" is used.
For example, the URL for invoking an "edit" action looks like this:

    http://example.org/entity-type/primary-key!edit

When HTTP POST is performed on this URL, the "edit" transition in specified
state machine is invoked.

However, it is also possible to use HTTP GET on such URL. In that case an
information required to perform HTTP POST is returned to a client. In case of
web browser this means a HTML form is sent back. Format of such information is
determined by "Accept" HTTP header, so it is possible to have fancy Javascript
client using the same URLs as plain HTML forms.


Examples of HTTP Requests 
-------------------------

@note These are examples of requests sent from ordinary web browser. If fancy
client is used, responses should be in format requested by Accept header, for
example JSON, instead of HTML pages with forms.


`GET %http://example.org/entity-type/primary-key`
	- Read state of state machine with ID [ "entity-type", "primary-key" ].
	- Response: HTML page displaying the state and properties of state
	  machine.

`GET %http://example.org/entity-type/primary-key!edit`
	- Retrieve HTML form which will be used to edit the state machine.
	- Response: HTML page with requested HTML form.

`POST %http://example.org/entity-type/primary-key!edit`
	- Edit given entity by invoking "edit" transition on given state
	  machine.
	- Response on success: HTTP 303 redirect (redirect after post) to the
	  first URL. Or HTTP 200 if fancy client is used.
	- Response on failure: HTTP 4xx response containing the same HTML form
	  and error message.


RESTful HTTP API?
-----------------

%Smalldb knows only two operations: Reading a state and invoking a transition.
From this point of view there is no use of other HTTP methods, including HTTP
PUT which would only make everything more complicated.

%Smalldb HTTP API still preserves other important properties of RESTful
applications, like identifying resources (state machines) with URL and possibly
linking to each other, state-less communication (and related scalability),
cachability, uniform interfaces, and others. Keep in mind that using of HTTP
methods in REST is a consequence, not primary feature of REST.

