# Snatch – Simple short term storage via HTTP

When installed on a web server the snatch.php script stores data sent to it for 
a short period of time and returns a random code for retrieving it later on.
It may be used as a backend for mobile apps that need to transfer settings, keys etc. 
from one device to another.

The data may only be retrieved once and will be deleted automatically after
successful retrieval.


## Installation

Just copy the snatch.php file to a public directory of your web server. You may
adjust the storage period by changing the value of the `$expirationTime` variable.
Make sure that PHP is able to write to the system's temporary directory.


## Storing

Send the data to be stored via HTTP POST request to the snatch.php script. The
script returns a random code which should be presented to the user.


## Retrieving

Call the snatch.php script and provide the storage code as query string:

```
https://myserver.tld/snatch.php?d7JKx2
```

If the code is correct and has not expired, the script returns the stored data
and deletes it immediately from the server.

The script provides a simple mechanism for protecting against brute-force
attacks. If a request fails due to an incorrect code, the client's IP address
is blocked for 10 seconds; if another attempt fails, it is blocked for 20
seconds etc.


## HTTP status codes

The script returns the following status codes:

- `200 OK` on successful retrieval
- `201 Accepted`on successful storage
- `400 Bad Request` when calling the script without POST or GET data
- `403 Forbidden` when IP is temporarily blocked due to failed retrieval attempts
- `404 Not Found` when the retrieval code could not be found
- `410 Gone` when the retrieval code has expired
