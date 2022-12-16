<?php

// Snatch - Simple short term storage via HTTP
//
// Stores data sent via HTTP-POST for a short period of time and makes it
// retrievable via random HTTP-GET URL.
//
// Copyright (c) 2021-22 Martin Wandelt
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
$delayPerFailedAttempt = 10; // seconds

if ( ! file_exists( "{$tmp}/snatch_remotes" ) ){
	mkdir( "{$tmp}/snatch_remotes" );
}

### Do some clean-up

$listing = glob( "{$tmp}/snatch_remotes/*" );
foreach ( $listing as $item ){
	if ( time() - filemtime( $item ) > $delayPerFailedAttempt * filesize( $item ) ){
		unlink( $item );
	}
}

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

	$iv = openssl_random_pseudo_bytes( 16 );
	$encrypted = openssl_encrypt( $data, 'aes-256-ctr', $code, 0, $iv );
	$content = base64_encode( $iv ) . ':' . $encrypted;
	file_put_contents( "{$tmp}/snatch/{$hash}/data", $content );
	chmod( "{$tmp}/snatch/{$hash}/data", 0700 );
	header('HTTP/1.1 201 Accepted');
	echo $code;
	exit;
}

### Handle GET request ###

$code = $_SERVER['QUERY_STRING'] ?? '';

if ( ! empty( $code ) ){

	// Check for brute force attack
	$ip = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR']
		?? $_SERVER['REMOTE_ADDR'] ?? NULL;
	if ( ! $ip ){
		header('HTTP/1.1 403 Forbidden');
		die('403 Forbidden (missing remote IP address)');
	}
	$ipFile = "{$tmp}/snatch_remotes/" . md5( $ip );
	$failedAttempts = file_exists( $ipFile ) ? filesize( $ipFile ) : 0;
	if ( $failedAttempts
		&& time() - filemtime( $ipFile ) < $delayPerFailedAttempt * $failedAttempts
	){
		header('HTTP/1.1 403 Forbidden');
		die('403 Forbidden (IP temporarily blocked due to failed attempts)');
	}

	// Handle request
	$hash = hash( 'sha256', $code );
	$dataFile = "{$tmp}/snatch/{$hash}/data";

	if ( ! file_exists( $dataFile ) ){
		file_put_contents( $ipFile, 'x', FILE_APPEND );
		header('HTTP/1.1 404 Not Found');
		die('404 Not Found');
	}

	if ( time() - filemtime( $dataFile ) > $expirationTime ){
		header('HTTP/1.1 410 Gone');
		$data = '';
	}
	else {
		list ( $iv, $encrypted ) = explode( ':', file_get_contents( $dataFile ) );
		$iv = base64_decode( $iv );
		$data = openssl_decrypt( $encrypted, 'aes-256-ctr', $code, 0, $iv );
	}
	unlink( $dataFile );
	rmdir( dirname( $dataFile ) );
	if ( $failedAttempts ){
		unlink( $ipFile );
	}
	echo $data;
	exit;
}

### Handle other request ###

header('HTTP/1.1 400 Bad Request');
die('400 Bad Request');

// end of file snatch.php

