<?php

/**
 * Home page for "Send Me SFMOMA".
 *
 * Copyright (C) San Francisco Museum of Modern Art
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * 
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

	require 'php/header.php';

	if(isset($_REQUEST['Body'])) {
		$body_raw = $_REQUEST['Body'];
	}
	else {
		//if there's no Body parameter ditch out, it's not a text
		print "nope";
		exit();
	}

    
	if(PROD_DOMAIN) {
		$to = $_REQUEST['From'];
		$from = $_REQUEST['To'];
	}
	else {
		$to = TEST_TO_NUMBER;
		$from = TEST_FROM_NUMBER;
	}

	//set to true to put application in maintenance mode
	$maintenance = false;

	if(!$maintenance) {

		$user_number_enc = crypt($to, CRYPT_SALT);

	    $body_lc = strtolower($body_raw);
	    $sendme = new SendMeSFMOMABase();

	    // track an event
		$mp->track("message received", 
			array(
				"body" => $body_raw,
				"to" => $from,
				"from" => $user_number_enc,
			)
		);

		if (strpos($body_lc, TRIGGER) !== false) {
			$keyword = $sendme->prepare_keyword($body_lc);

			if(strlen($keyword) > 2) {
				$response = $sendme->prepare_query($keyword);
				$body = $response['body'];
				$media = $response['media'];
				$type = $response['type'];
				if(!$media) {
					$type = "No Matches";
				}
			}
			else {
				$body = $sendme->get_messaging('no_matches');
				$media = FALSE;
				$type = "No Matches";
			}
		}
		else {
			// trigger phrase not present, send intro
			$body = $sendme->get_messaging('intro');
			$media = FALSE;
			$type = "Intro";
			$keyword = 'none';
		}

		$message = array (
			'from' => $from,
			'body' => $body,
		);

		if ($media) {
			$message['mediaUrl'] = $media;
		}

	}

	if($maintenance) {
		$message = array (
			'from' => $from,
			'body' => "Send Me SFMOMA is taking a break while we do some maintenace.  We'll be back soon!",
		);
	}

	// send response SMS/MMS via twilio SDK
	$client->messages->create(
 	       $to,
 	       $message
 	   );

	// send anonymized info to mixpanel
    $mp_event = "message sent";
    $mp_attr = array(
			"body" => $body,
			"media" => $media,
			"type" => $type, // artwork-keyword, artwork-color, PIG, reserved-term, etc
			"accession_number" => "",
			"keyword" => $keyword,
			"to" => $user_number_enc,
			"from" => $from,
		);
    // if artwork log accession number and artist
    if (isset($response['accession_number'])) {
    	$mp_attr['accession_number'] = $response['accession_number'];
    }
    if (isset($response['artist'])) {
    	$mp_attr['artist'] = $response['artist'];
    }
    // send to mixpanel
	$mp->track($mp_event, $mp_attr);
?>