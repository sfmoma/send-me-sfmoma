<?php

	require __DIR__.'/config.php';

	// intialize external libs
	require AUTOLOADER_PATH;

	// initialize Twilio
	use Twilio\Rest\Client;
	$client = new Client(TWILIO_SID, TWILIO_TOKEN);

	// initialize mixpanel
	$mp = Mixpanel::getInstance(MIXPANEL_TOKEN);

	include __DIR__."/sendme.php";

?>