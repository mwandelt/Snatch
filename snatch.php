<?php

// Snatch - Simple short term storage via HTTP
//
// Stores data sent via HTTP-POST for a short period of time and makes it
// retrievable via random HTTP-GET URL.
//
// Copyright (c) 2021 Martin Wandelt
// 
// Permission is hereby granted, free of charge, to any person obtaining a copy
// of this software and associated documentation files (the "Software"), to deal
// in the Software without restriction, including without limitation the rights
// to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
// copies of the Software, and to permit persons to whom the Software is
// furnished to do so, subject to the following conditions:
// 
// The above copyright notice and this permission notice shall be included in all
// copies or substantial portions of the Software.
// 
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
// AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
// LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
// OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
// SOFTWARE.

$expirationTime = 300; // seconds
$characters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
$tmp = sys_get_temp_dir();
header('Content-Type: text/plain');

### Handle POST request ###

$data = file_get_contents('php://input');

if ( ! empty( $data ) ){
	do {
		$code = '';
		foreach ( range( 1, 6 ) as $i ){
			$code .= $characters[ rand( 0, strlen( $characters ) - 1 ) ];
		}
		$hash = hash( 'sha256', $code );
		if ( @mkdir( "{$tmp}/snatch/{$hash}", 0700, TRUE ) ){
			break;
		}
	} while ( TRUE );

	file_put_contents( "{$tmp}/snatch_{$code}/data", $data );
	header('HTTP/1.1 201 Accepted');
	echo $code;
	exit;
}

### Handle GET request ###

$code = $_SERVER['QUERY_STRING'] ?? '';

if ( ! empty( $code ) ){
	$hash = hash( 'sha256', $code );
	$file = "{$tmp}/snatch/{$hash}/data";

	if ( ! file_exists( $file ) ){
		header('HTTP/1.1 404 Not Found');
		die('404 Not Found');
	}

	if ( time() - filemtime( $file ) > $expirationTime ){
		unlink( $file );
		rmdir( dirname( $file ) );
		header('HTTP/1.1 410 Gone');
		die('410 Gone');
	}

	readfile( $file );
	unlink( $file );
	rmdir( dirname( $file ) );
	exit;
}

### Handle other request ###

header('HTTP/1.1 400 Bad Request');
die('400 Bad Request');

// end of file snatch.php

