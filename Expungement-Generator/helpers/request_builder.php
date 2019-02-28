<?php
// Function parses $_REQUEST and json post data to return the information that the user sent to the api
// This is because we want to be able to accept JSON info sent with the HTTP headers that may not be in the post-request object.
// First checks to see if there is JSON not in the

function request_builder()
{
	$request = array();

  $data = file_get_contents('php://input');
	$json = json_decode($data, true);

	#error_log("request");
	#file_put_contents('php://stderr', print_r($_REQUEST, TRUE));

	#error_log("json");

        #file_put_contents('php://stderr', print_r($json, TRUE));

	//N.B. We check every key explicitly (instead of having a list that just goes through all the keys) so that eventually we'll also be able to validate each key's value (e.g. the apikey looks like one thing, and current_user is an email, and so on.) Right now, every key is treated the same and validation is scattered around elsewhere, but later, that won't be the case.

	if (array_key_exists('current_user', $_REQUEST) || array_key_exists('current_user', $json)) {
		$request['current_user'] = (array_key_exists("current_user", $_REQUEST)) ? $_REQUEST['current_user'] : $json['current_user'];
	}

	if (array_key_exists('apikey', $_REQUEST) || array_key_exists('apikey', $json)) {
		$request['apikey'] = (array_key_exists('apikey', $_REQUEST)) ? $_REQUEST['apikey'] : $json['apikey'];
	}

	if (array_key_exists('personFirst', $_REQUEST) || array_key_exists('personFirst', $json)) {
		$request['personFirst'] = (array_key_exists('personFirst', $_REQUEST)) ? $_REQUEST['personFirst'] : $json['personFirst'];
	}

	if (array_key_exists('personLast', $_REQUEST) || array_key_exists('personLast', $json)) {
		$request['personLast'] = (array_key_exists('personLast', $_REQUEST)) ? $_REQUEST['personLast'] : $json['personLast'];
	}

	if (array_key_exists('personDOB', $_REQUEST) || array_key_exists('personDOB', $json)) {
		$request['personDOB'] = (array_key_exists('personDOB', $_REQUEST)) ? $_REQUEST['personDOB'] : $json['personDOB'];
	}

	if (array_key_exists('cpcmsSearch', $_REQUEST) || array_key_exists('cpcmsSearch', $json)) {
		$request['cpcmsSearch'] = (array_key_exists('cpcmsSearch', $_REQUEST)) ? $_REQUEST['cpcmsSearch'] : $json['cpcmsSearch'];
	}

	if (array_key_exists('docketNums', $_REQUEST) || array_key_exists('docketNums', $json)) {
		$request['docketNums'] = (array_key_exists('docketNums', $_REQUEST)) ? $_REQUEST['docketNums'] : $json['docketNums'];
	}

	if (array_key_exists('createPetitions', $_REQUEST) || array_key_exists('createPetitions', $json)) {
		$request['createPetitions'] = (array_key_exists('createPetitions', $_REQUEST)) ? $_REQUEST['createPetitions'] : $json['createPetitions'];
	}

	if (array_key_exists('emailPetitions', $_REQUEST) || array_key_exists('emailPetitions', $json)) {
		$request['emailPetitions'] = (array_key_exists('emailPetitions', $_REQUEST)) ? $_REQUEST['emailPetitions'] : $json['emailPetitions'];
	}

	if (array_key_exists('emailDomain', $_REQUEST) || array_key_exists('emailDomain', $json)) {
		$request['emailDomain'] = (array_key_exists('emailDomain', $_REQUEST)) ? $_REQUEST['emailDomain'] : $json['emailDomain'];
	}

	if (array_key_exists('emailAddressField', $_REQUEST) || array_key_exists('emailAddressField', $json)) {
		$request['emailAddressField'] = (array_key_exists('emailAddressField',$_REQUEST)) ? $_REQUEST['emailAddressField'] : $json['emailAddressField'];
	        if (array_key_exists($request['emailAddressField'], $_REQUEST) || array_key_exists($request['emailAddressField'], $json)) {
		    $request[$request['emailAddressField']] = (array_key_exists($request['emailAddressField'], $_REQUEST)) ? $_REQUEST[$request['emailAddressField']] : $json[$request['emailAddressField']];
		}
	}


	//personSSN
	if (array_key_exists('personSSN', $_REQUEST) || array_key_exists('personSSN', $json)) {
		$request['personSSN'] = (array_key_exists('personSSN', $_REQUEST)) ? $_REQUEST['personSSN'] : $json['personSSN'];
	}

	//personStreet
	if (array_key_exists('personStreet', $_REQUEST) || array_key_exists('personStreet', $json)) {
		$request['personStreet'] = (array_key_exists('personStreet', $_REQUEST)) ? $_REQUEST['personStreet'] : $json['personStreet'];
	}

	//personCity
	if (array_key_exists('personCity', $_REQUEST) || array_key_exists('personCity', $json)) {
		$request['personCity'] = (array_key_exists('personCity', $_REQUEST)) ? $_REQUEST['personCity'] : $json['personCity'];
	}

	//personState
	if (array_key_exists('personState', $_REQUEST) || array_key_exists('personState', $json)) {
		$request['personState'] = (array_key_exists('personState', $_REQUEST)) ? $_REQUEST['personState'] : $json['personState'];
	}

	//personZip
	if (array_key_exists('personZip', $_REQUEST) || array_key_exists('personZip', $json)) {
		$request['personZip'] = (array_key_exists('personZip', $_REQUEST)) ? $_REQUEST['personZip'] : $json['personZip'];
	}


	return $request;
}


?>
