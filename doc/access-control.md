Access Control (FlupdoMachine)
==============================

This page describes access control implemented in FlupdoMachine::checkAccessPolicy().

Each transition of state machine has access control rule assigned. Each rule is
of one of following type:

`anyone`
:	Completely open for anyone.

`anonymous`
:	Only anonymous users allowed (not logged in; user ID must be null).

`user`
:	All logged-in users allowed (non-null user ID)

`owner`
:	Owner must match current user. Options: `owner_property` (name of
	property containing user ID), `session_state` (session machine must be
	in this state).

`role`
:	Current user must have specified role (independent of state machine)

`condition`
:	Condition in SQL select. Options: `sql_select` (SQL condition).

`user_relation`
:	Condition in SQL select, using user ID as parameter. Options:
	`sql_select` (SQL expression, checked to not be null), `required_value`
	(value of `sql_select` is compared with this value).


Unknown rule types are considered unsafe and access is always denied.

