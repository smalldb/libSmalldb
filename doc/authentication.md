Authentication
==============


Basic principles
----------------

Authentication is a mechanism to securely identify a user, so a server knows
who is on the other end of the HTTP connection. Authentication does not tell
what the user is allowed to do -- that is matter of
[authorization](doc/access-control.md).

Authentication on the modern web can be reduced to following three mechanisms:

  1. **Basic auth:** Using username and secret password the user identifies
     himself. Only the user knows the password, server knows only a hash of the
     password. (There are more variants of this approach, like [digest access
     authentication](https://en.wikipedia.org/wiki/Digest_access_authentication),
     which eliminates sending plain password over the network. Also a
     certificate can be used instead of the password.)

  2. **Signed token:** User's client somehow (using a Single Sign On service)
     obtains a token with a trusted signature and presents this token to the
     server. Server verifies the signature and if the signature is valid the
     user is authenticated (see [JSON Web Token](https://jwt.io/)).

  3. **Shared token:** User's client (web browser) and the server share a
     secret token (or part of it). When tokens match, the user is
     authenticated.

Traditional user session is typicaly implemented using the shared token. For
REST services it is usually better to use the signed token, because it is
state-less (all authentication data are stored in the signed token).

The basic auth is usually used only to obtain a shared token, or to obtain
a signed token using SSO service and then pass the obtained signed token to the
web application to obtain the shared token or invoke some REST API. The signed
token allows truly state-less REST services, since the application only
validates signature and does not need maintain any session data between HTTP
requests (session data are stored in the signed token).

In traditional web application built on Smalldb we need two state machines: One
to represent a user and second to represent the shared token. User state
machine is used simply to verify user's password. The shared token machine is
the interesting one...


CookieAuth and SharedTokenMachine
---------------------------------

`Auth\CookieAuth` and `Auth\SharedTokenMachine` classes implement a shared
token authentication. The part of the token is stored in an authentication
cookie and other part in database (using a session state machine).

`CookieAuth` class handles the cookies. `SharedTokenMachine` class maintains
the session. There should be little to no need to modify `CookieAuth` class,
but `SharedTokenMachine` is designed to be modified for specific needs of your
application.

(...)

