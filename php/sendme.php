<?php

/**
 * Handle incoming requests for "Send Me SFMOMA".
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

class SendMeSFMOMABase {

	// see config_sample.php for more info on these constants
	public $api_key = SFMOMA_API_KEY;
	public $trigger = TRIGGER;
	public $page_limit = API_PAGE_LIMIT;
	public $artwork_api_root = API_ARTWORK_ROOT;
	public $s3_bucket_root = S3_BUCKET;
	public $nonos = NONOS;
	public $emoji = EMOJI;
	public $colors = COLORS;

	function __construct() {
		
	}

	// curl the api
	function query_collection($url, $query_data=[]) {

		$ch = curl_init();
		$timeout = 5;
		$request_headers[] = 'Authorization: Token '.$this->api_key;
		$query = http_build_query($query_data);

		$url = $url.'?'.$query;

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		$data_json = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($data_json);

		return $data;

	}

	// collect artwork data
	function get_artwork_data($url, $query_params, &$artwork_data, $page=1) {

		$query_params['page'] = $page;

		$response = $this->query_collection($url,$query_params);
		$next = $response->next;
		
		$artwork_data[] = $response;

		if ($page < $this->page_limit && $next) {
			$page++;
			$this->get_artwork_data($url, $query_params, $artwork_data, $page);
		}
		return $artwork_data;
	}

	// take random page rather than aggregate all results
	function get_random_page($url, $query_params, &$artwork_data, $page=1) {
		$query_params['page'] = $page;
		$response = $this->query_collection($url,$query_params);
		$count = $response->count;

		// if more than one page is returned, choose a random one
		if($count > 20) {
			$pages = ceil($count / 20);
			$page = rand(1, $pages);
		}

		$query_params['page'] = $page;
		$rand_response = $this->query_collection($url, $query_params);
		$artwork_data[] = $rand_response;

		return $artwork_data;

	}

    function extract_images($response) {

    	$images = array();

    	if(count($response) > 0) {
			foreach ($response as $r) {
				foreach($r->results AS $res) {
					foreach($res->images AS $img) {
						if($img->is_hero_image == true) {
							$images[] = array(
								'img_url' => $img->public_image,
								'img_title' => $res->title->display,
								'artist' => $res->artists[0]->artist->name_display,
								'web_url' => $res->web_url,
								'date' => $res->date->display,
								'object_keywords' => $res->object_keywords,
								'accession_number' => $res->accession->number,
							);
						}
					}
				}
			}
		}

		return $images;

    }

    // 1. prepare keyword
    function prepare_keyword($body_lc) {
    	$body_parts = explode($this->trigger, $body_lc);

    	else {
	        preg_match('/(?<='.$this->trigger.' )\S+/i', $body_lc, $match);

	        if ($match) {
		        //trim white space
		        $keyword = trim($match[0]);
		        //remove punctuation
		        $keyword = preg_replace("/(?![.=$'€%-])\p{P}/u", "", $keyword);
		        //remove dots (somehow not covered in the above)
		        $keyword = str_replace(".", "", $keyword);
		        //convert to lower case
		        $keyword = strtolower($keyword);
		        //check / transform for common prefixes: san, los, las, new and add the next word to search term
		    	$keyword = $this->check_prefix($keyword, $body_lc);
		    	//check / transform for common lede: a, an, my and take word after as term
		    	$keyword = $this->check_lede($keyword, $body_lc);
		    	//check and transform certain keywords
		    	$keyword = $this->transform_me($keyword);
		    	//check if keyword is encoded as emoji and translate encoded emoji to keyword
		    	$keyword = $this->check_emojis($keyword);
	    	}
	    	else {
	    		$keyword = FALSE;
	    	}
    	}

    	return $keyword;
    }

    // 2. prepare query
    function prepare_query($keyword) {
    	$sendme_response = array();
    	$sendme_response['type'] = "undefined";

    	//default to negative response
    	$media = FALSE;
    	$body = $this->get_messaging('no_matches');

    	if($this->check_reserved_terms($keyword)) {
    		// prepare reserved_term response
    		$aliases = $this->get_utility_words('reserved_term_alias');
	    	if(array_key_exists($keyword, $aliases)) {
	    		$keyword = $aliases[$keyword];
	    	}
    		$function_name = '_'.$keyword.'__response';
	    	$sendme_response = $this->$function_name($keyword);
	    	$sendme_response['type'] = "reserved-term";
    	}
    	else if($this->check_pig_hash($keyword)) {
    		//retrieve a PIG if it exists
    		$kw_upper = strtoupper($keyword);
    		$media = "https://s3-us-west-2.amazonaws.com/sfmomamedia/proxies/pic/images/".$kw_upper.".jpg";
    		$body = "#SelfComposed";
    		$sendme_response = array(
    			"body" => $body,
    			"media" => $media,
    			"type" => "PIG SelfComposed"
    		);
    	}
    	else {
    		// if term not reserved prepare API query
    		$query_params['has_images'] = TRUE;

    		if($this->check_color($keyword)) {
    			// prepare color search query
    			$query_params['color_css_name_str__icontains'] = $keyword;
    			$type = "artwork-color";
	    	}
	    	else {
	    		// prepare object_keywords__iregex query
	    		$query_params['object_keywords__iregex'] = "\y".$keyword."(s|es)*\y";
	    		$type = "artwork-keyword";
	    	}
	    	
	    	//query api for artwork data
	    	$artwork_data = array();
	    	$artwork_data = $this->get_random_page($this->artwork_api_root, $query_params, $artwork_data);

	    	//extract images and caption data from response
	    	$images = $this->extract_images($artwork_data);

	    	//shuffle results to randomize
	    	shuffle($images);
	    	foreach($images as $img) {	    		
	    		//choose a pg-rated image
	    		if(!$this->check_nonos($img)) {
	    			$media = $img['img_url'];
					$body = $img['artist'].', '."'".$img['img_title']."'".', '.$img['date'];
					$accession_number = $img['accession_number'];
					$artist = $img['artist'];
	    			break;
	    		}
	    	}

	    	$sendme_response['body'] = $body;
    		$sendme_response['media'] = $media;
    		$sendme_response['type'] = $type;
    		if(isset($accession_number)) {
    			$sendme_response['accession_number'] = $accession_number;
    		}
    		if(isset($artist)) {
    			$sendme_response['artist'] = $artist;
    		}
    	}

    	return $sendme_response;

    }

    function check_reserved_terms($keyword) {
    	$is_reserved = FALSE;

    	$aliases = $this->get_utility_words('reserved_term_alias');
    	if(array_key_exists($keyword, $aliases)) {
    		$keyword = $aliases[$keyword];
    	}

    	$function_name = '_'.$keyword.'__response';
    	if(method_exists ( $this , $function_name )) {
    		$is_reserved = TRUE;
    	}
    	return $is_reserved;
    }

    function check_prefix($keyword, $body_lc) {
    	$common_prefix = $this->get_utility_words('common_prefix');
    	if (in_array($keyword, $common_prefix) ) {
    		list($before, $after) = explode($this->trigger." ", $body_lc);
            $after_parts = explode(" ", $after);
            $keyword = $after_parts[0].' '.$after_parts[1];
    	}
    	return $keyword;
    }

    // is the user asking for their #SelfComposed pic?
    function check_pig_hash($keyword) {
    	$kw_upper = strtoupper($keyword);
    	$is_pig = FALSE;
    	$pig_url = "https://s3-us-west-2.amazonaws.com/sfmomamedia/proxies/pic/images/".$kw_upper.".jpg";
    	if(@get_headers($pig_url)[0] == 'HTTP/1.1 200 OK') {
		     // the PIG exists
    		$is_pig = TRUE;
		}
		else {
			// the PIG does not exist
			$is_pig = FALSE;
		}
		return $is_pig;
    }

    function check_lede($keyword, $body_lc) {
    	$common_lede = $this->get_utility_words('common_lede');
    	if(in_array($keyword, $common_lede)) {
    		list($before, $after) = explode($this->trigger." ", $body_lc);
            $after_parts = explode(" ", $after);
            $keyword = $after_parts[1];
    	}
    	return $keyword;
    }

    function transform_me($keyword) {
    	$transform_mes = $this->get_utility_words('transform_me');
    	if (array_key_exists($keyword, $transform_mes)) {
    		$keyword = $transform_mes[$keyword];
    	}
    	return $keyword;
    }

    // is the artwork tagged with a nono?
	function check_nonos($img) {
		$is_nono = FALSE;
		$object_keywords = $img['object_keywords'];
		$keywords = explode(";", $object_keywords);
		$nonos = $this->get_utility_words('nonos');
		foreach($keywords AS $word) {
			$w = trim($word);
			$w = strtolower($w);
			if(in_array($w, $nonos)) {
				$is_nono = TRUE;
				break;
			}

		}
		return $is_nono;
	}

	// is the keyword on the list of nonos?
	function check_nonos_kw($keyword) {
		$nonos = $this->get_utility_words('nonos');
		if (in_array($keyword, $nonos)) {
			$keyword = '';
		}
		return $keyword;
	}

	// is the keyword an emoji?
	function check_emojis($keyword) {
		$kw_json = json_encode($keyword);
		//strip quotes out
		$kw_json = str_replace('"', "", $kw_json);
		$emojis = $this->get_utility_words('emojis_to_words');
		if(array_key_exists($kw_json, $emojis)) {
			$keyword = $emojis[$kw_json];
		}
		return $keyword;
	}

	// is the keyword the name of a color?
	function check_color($keyword) {
		$is_color = FALSE;
		$colors = $this->get_utility_words('colors');
		if(array_key_exists($keyword, $colors)) {
			$is_color = TRUE;
		}
		return $is_color;
	}

	// this is a dumb check for s3/bucket/keyword/keyword.jpg
	function s3_reserved_response($keyword, $bucket, $tag="jpg") {
		$media = $this->s3_bucket_root."".$bucket."/".$keyword."/".$keyword.".".$tag;
		if(@get_headers($media != 'HTTP/1.1 200 OK')){
		     // the media was not found exists
    		$media = FALSE;
		}
		return $media;
	}

	// example of a custom response
	// functions named "_KEYWORD__response()" will override default response
	function _jay__response($keyword) {
		//where's your media?
		$bucket = "bdaybash";
		$media = $this->s3_reserved_response($keyword, $bucket, "jpg");
		$body = "It's JAY!";
		$response = array(
			"body" => $body,
			"media" => $media,
		);
		return $response;
	}

	// another example of a custom response
	// "Send me tiny games" will send you back an example of a tiny games from PlaySFMOMA
	function _tinygames__response($keyword) {

		$tiny_games = array (
			array(
				"name" => "ARTISTS EVERYWHERE: A game about imagining talent, for two or more players.",
				"game" => "Look at the people around you. On the count of three, each of you points (subtly) to the stranger who you think is most likely to be an artist. Now explain your choice - what kind of art do they make? The player with the most convincing story wins.",
			),
			array(
				"name" => "THE WAITING GAME: A game about hidings things… from yourself.",
				"game" => "Write a secret note to your future self and hide it somewhere in your home. If it takes you more than a month to see the note again, you win!",
			),
			array(
				"name" => "MNEMONIC CANVAS: A memory game of imaginary painting.",
				"game" => "In this game you’re going to remember the following six words: LIGHTNING, COBALT, FORGIVENESS, MIDNIGHT, CANVAS, and RADIO. Create an imaginary painting in your head that will help you remember them. Now close your eyes, imagine your fictional painting, and recite the words back in order.",
			),
			array(
				"name" => "ELEMENTAL: An observation game for one sharp-eyed player.",
				"game" => "Look around you. To win this game, find examples of any five of the following seven elements of art in the world you see. LINE, SHAPE, TEXTURE, CONTRAST, (A)SYMMETRY, POLITICS, BRILLIANCE.",
			),
			array(
				"name" => "SUNSHINE: A game about small connections.",
				"game" => "For the rest of today, you’re playing this game. Earn 1 point for every stranger you make smile, 2 points if you make them laugh. You win if you end the day with 7 or more points.",
			),
			array(
				"name" => "RAINBOW IN YOUR POCKET: A game about hues, shades, and old receipts.",
				"game" => "The goal of this game is to create a rainbow (Red, Orange, Yellow, Green, and Blue) out of things you have with you right now. Clothes work, as do any little thing in your pocket or wallet. Search carefully - you only need to find a tiny bit of the color to count it!",
			),
			array(
				"name" => "TODAY IS SPECIAL: An adventurous game from one brave player.",
				"game" => "This is a game about trying new things. To win, you must eat something you’ve never eaten, stand somewhere you’ve never stood, and said something you’ve never said, all before you go to sleep tonight. How did it go? Share your story with friends or @SFMOMA.",
			),
			array(
				"name" => "EYES ON THE BACK OF YOUR HEAD: An observation game for two or more players.",
				"game" => "Have another player look right at you - and tell them not to turn around. Now look over their shoulder and tell them three things about what is behind them. But, make one of them up! If they can identify the two truths, they win.",
			),
			array(
				"name" => "URBAN RETREAT: A game about escaping, if only for a moment.",
				"game" => "Close your eyes. Now using auditory clues, imagine that you’ve momentarily escaped the city (at least in your own head). Reimagine the world around you. Perhaps the noise of cars going by is actually the rumbling of distant winds on the mountaintop, or the growling of beasts on Jupiter. You earn one point for every thing that you transform.",
			),
			array(
				"name" => "WHAT ARE WE DRAWING HERE? A creative game for two or more players.",
				"game" => "Grab some paper and something to draw with. One player starts by drawing something simple using exactly three lines - anything works! The next player must then add three new lines to completely transform the drawing. Keep taking turns, changing the thing each time. How long can you go?",
			),
			array(
				"name" => "DUCHAMP WOULD BE PROUD:A game about ordinary masterpieces ",
				"game" => "Choose a nearby everyday object and create a museum object label for it. What is the title, what are the materials, how was it made, what was the “artist” thinking when he or she made this? Post a photo and your description online and see what your friends think of this priceless artwork.",
			),
			array(
				"name" => "THE ART AROUND US:An art game for civic players.",
				"game" => "Think of a piece of public art you’ve seen around town. Perhaps a sculpture, a mural, or a statue? Imagine it carefully and think of three specific details about it - for example the colors that appear in it, or the name of the artist. Now go find it and see how you did - you win if at least 2 of your details were correct.",
			),
			array(
				"name" => "OLD AND NEW: An observation game for two time sensitive players.",
				"game" => "One player is looking for things that were made MORE than 30 years ago. The other player is searching for things made LESS than 1 year ago. As you walk through town, players call out things that fit their requirement to earn a point. Once an object is scored, close that entire category of things: for example if one player spots a classic car made more than 30 years ago, for the rest of the game no further cars can be scored.",
			),
			array(
				"name" => "ALPHABET CITY:A game of quick observations for two or more players.",
				"game" => "Kick off the game by saying any word, perhaps SMILE or DANCE. Now it’s a race to see who can find all the letters in the word - by locating them in the world around you. Any letters that are clearly visible count, so look for advertisements, posters, or street signs. You’re not taking turns or pointing everything out, just go as fast as you can! When someone’s completed the word they win that round and choose the next word. If the everyone gets stuck on a hard word, just move on and try something shorter.",
			),
			array(
				"name" => "RAINBOW CONNECTIONS:A game about stepping in paint for 2 or more players.",
				"game" => "Whenever you spot any kind of color on the ground or sidewalk, step on it and call out the color to claim it and earn 1 point. Consider construction markings, or street signage that's painted on. Or graffiti. First person to collect the rainbow (Red, Orange, Yellow, Green, Blue) wins. ",
			),
			array(
				"name" => "SONG IN YOUR ART: A singing game for two musical players or teams ",
				"game" => "Think of a song that mentions a visual artist, artwork or artistic process. You must be able to sing at least 8 words of it. Sing it to the other team and see if they can guess the title. First team to stump the opponents wins.",
			),

		);

		$tiny_game = $tiny_games[mt_rand(0, count($tiny_games) - 1)];

		$body = $tiny_game['name'] . "\n\n\n " . $tiny_game['game'] . "\n\n\n #PlaySFMOMA";

		$response = array(
			"body" => $body,
			"media" => FALSE,
		);

		return $response;
	}

	// a function for returning different lists of words
	function get_utility_words($list) {

		$words = array();

		//words to include with next word in text
		$common_prefix = array(
			'san',
			'los',
			'las',
			'new',
			'tiny',
			'modern',
			'sfmoma',
		);

		// use this array to create aliases for phrases or terms
		$reserved_term_alias = array(
			"tiny game" => 'tinygames',
			"tiny games" => 'tinygames',
		);

		//words to not include in artwork search
		$common_lede = array (
			'a',
			'an',
			'the',
			'some',
			'something',
			'any',
			'my',
			'that',
			'their',
			'his',
			'her',
			'your',
			'more',
			'to',
		);

		//trasnform some terms for better matches
		$transform_me = array(
			'puppies' => 'dogs',
			'puppy' => 'dogs',
			'kittens' => 'cats',
			'kitty' => 'cats',
			'kitties' => 'cats',
			'donuts' => 'doughnuts',
			'laughter' => 'laughing',
			'kisses' => 'kissing',
			'wolf' => 'wolves',
			'box' => 'boxes',
		);

		// map color names to hex vals
		$colors  =  $this->colors;

		// map emoji to keyword
		// more here: https://gist.github.com/jaymollica/cf179e0facabbb4ec8cf9e69804b7cb1
		$emojis_to_words = $this->emoji;

		// nonos are words you don't want folks searching against
		$nonos = $this->nonos;

		if(is_array($$list)) {
			$words = $$list;
		}

	    return $words;
	}

	function get_messaging($msg) {
		$intro = 'Hello from Send Me SFMOMA! Text “send me cats” or "send me 🌵" to this number to receive an artwork via SMS from our collection.';

		$no_matches = 'We could not find any matches.  Maybe try "Send me San Francisco" or "Send me 🌊" or "Send me something purple".';

		$beta_intro = 'Hello from Send Me SFMOMA!  Thank you for exploring our collection this way. Beta testing is over for now, please stay tuned for updates by following @sfmoma and @sfmomalab.';

		$messaging = array (
			'no_matches' => $no_matches,
			'intro' => $intro,
			'beta_intro' => $beta_intro,
			'terms' => '',
		);

		return $messaging[$msg];

	}
}

?>