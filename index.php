<?php

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