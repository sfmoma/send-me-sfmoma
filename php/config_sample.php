<?php

//sfmoma API key (deprecated)
define("SFMOMA_API_KEY", "");

// the phrase when in SMS body triggers a collection search
// in the case of send me sfmoma, this value is "send me"
define("TRIGGER", "send me");

//is it running on prod?
$prod_domain = strstr($_SERVER['SERVER_NAME'],'https://www.example.com');
define("PROD_DOMAIN", $prod_domain);

// assets for custom results
define("S3_BUCKET", "https://path/to/aws.com/s3/bucket/...");

// for encrpyting phone numbers to create unique IDs
// phone numbers themselves are not stored in analytics
define("CRYPT_SALT", "");

//Load server settings files for env
if(PROD_DOMAIN) {
	// if on prod server
	define("API_ROOT_URL", "");
	// use Twilio prod tokens
	define("TWILIO_SID", "");
	define("TWILIO_TOKEN", "");
	define("AUTOLOADER_PATH", $_SERVER['DOCUMENT_ROOT'].'vendor/autoload.php');
	// MIXPANEL credentials
	define("MIXPANEL_TOKEN", "");
}
else {
	// use local
	define("API_ROOT_URL", "");
	// use Twilio dev tokens
	define("TWILIO_SID", "");
	define("TWILIO_TOKEN", "");
	//test numbers
	define("TEST_TO_NUMBER", "");
	define("TEST_FROM_NUMBER", "");
	define("AUTOLOADER_PATH", $_SERVER['DOCUMENT_ROOT'].'/vendor/autoload.php');
	// MIXPANEL dev credentials
	define("MIXPANEL_TOKEN", "");
}

// the sfmoma api is paginated
// how many pages deep should we go with the API results?
define("API_PAGE_LIMIT", 1500);
define("API_ARTWORK_ROOT", API_ROOT_URL."artworks/");

// 1 dimensional array of words you don't want to match artworks to
$nonos_array = array(
		"bad word 1",
		"bad word 2",
		//etc...
);

define("NONOS", $nonos_array);

// map emoji to words
// more info found here: https://gist.github.com/jaymollica/cf179e0facabbb4ec8cf9e69804b7cb1
$emoji_array = array(
		"surrogate-pair-1" => "keyword-1",
		"surrogate-pair-2" => "keyword-2",
		//etc...
);

define("EMOJI", $emoji_array);
// colors
// associative array color_name => hex (note: no leading # on the hex color)
$colors_array = array(
	        'blue'=>'0000FF',
	        'brown'=>'A52A2A',
	        // etc ...
        );

define("COLORS", $colors_array);

?>