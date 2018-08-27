

<?php
	include('CPCMS.php');
	require_once('utils.php');
	require_once('config.php');
	require_once('Record.php');
	include('expungehelpers.php');
	include('helpers/mail_helper.php');
	include('helpers/api_validator.php');
	include('helpers/loggers.php');
	include('helpers/request_builder.php');
	//initialize the response that will get sent back to requester
	$response = array();
	//set default response code:
	http_response_code(404);


	$request = request_builder();

	// Log the request, but strip identifying info
	$test_headers = $request;
        error_log("Logging a request to eg-api.");

	$test_headers['apikey'] = preg_replace('/./', 'x', $test_headers['apikey']);
	$test_headers['personFirst'] = preg_replace('/(?!^)./','x',$test_headers['personFirst']);
	$test_headers['personLast'] = preg_replace('/(?!^)./','x',$test_headers['personLast']);
	$test_headers['personStreet'] = preg_replace('/(?!^)./','x',$test_headers['personStreet']);
	$log_trail = "";


	// Test if the quest is well formed.
	if(malformedRequest($request)) {
		http_response_code(400);
		$response['results']['status'] = malformedRequest($request);
		$log_trail .= "malformed request";
	} elseif(!validAPIKey($request, $db)) {
		http_response_code(401);
		$response['results']['status'] = "Invalid request.";
		$log_trail .= "invalid request";
	} else {
		$user_id = validAPIKey($request, $db);
		http_response_code(200);
		error_log("Starting to process a good response.");
		$log_trail .= "valid request"; //build a string that shows how the request moved through the script.
					     //Return it at the end in write_to_resource_log()
		// a cpcmsSearch flag can be set to true in the post request
		// to trigger a cpcms search.
		if (isset($request['cpcmsSearch']) && preg_match('/^(t|true|1)$/i', $request['cpcmsSearch'])===1){
			$log_trail .= ",cpcmsSearch";
			$urlPerson = getPersonFromPostOrSession($request);

			$cpcms = new CPCMS($urlPerson['First'],$urlPerson['Last'], $urlPerson['DOB']);
			$status = $cpcms->cpcmsSearch();
			$statusMDJ = $cpcms->cpcmsSearch(true);
			if (!preg_match("/0/",$status[0]) && !preg_match("/0/", $statusMDJ[0])) {
				$response['results'] = "Your CPCMS search returned no results.";
			} else {
				//only integrate the summary information if we
        // have a DOB; otherwise what is the point?
        			if (!empty($urlPerson['DOB'])) {
            				$cpcms->integrateSummaryInformation();
				}
				// We need an array of docket numbers, so we take the list of results
				// and extract only the docket number from each.
				$docketNums = array();
				foreach (array_merge($cpcms->getResults(), $cpcms->getMDJResults()) as $result) {
					$docketNums[] = $result[0];
				};
        // remove the cpcmsSearch variable from the POST vars and then pass them to
        // a display funciton that will display all of the arrests as a webform, with all
        // of the post vars re-posted as hidden variables.  Also pass this filename as the
        // form action location.
        			unset($request['cpcmsSearch']);
			}
		} // end of processing cpcmsSearch
		error_log("Done processing cpcmsSearch");
		$urlPerson = getPersonFromPostOrSession($request);
		$person = new Person($urlPerson['First'],
			$urlPerson['Last'],
			$urlPerson['SSN'],
			$urlPerson['Street'],
			$urlPerson['City'],
			$urlPerson['State'],
			$urlPerson['Zip']);
		$record = new Record($person);

		// TODO get rid of this?
		getInfoFromGetVars($request); //this sets session variables based on the GET or
		// POST variables 'docket', 'sealingRegardless', 'expungeRegardless', and
		// 'zipOnly'
		$response['personFirst'] = $urlPerson['First'];
		$response['personLast'] = $urlPerson['Last'];
		$response['dob'] = $urlPerson['DOB'];
		$attorney = new Attorney($user_id, $db);

		error_log("Figured out the Attorney:");
		error_log("Attorney " . $request['current_user'] . " is " . $user_id);
		$docketFiles = $_FILES;

		if (!isset($docketNums)) {
			// If $docketNums wasn't set in CPCMS search, initialize it to an empty array.
			$docketNums = array();
		}

		if (isset($request['docketNums'])) {
			// Add any docket numbers passed in POST request to $docketnums.
			// POST[docketnums] should be a comma-delimited string like "MC-12345,CP-34566"
			$log_trail .= ",requested docket numbers";
			$docketNumsRequest = filter_var($request['docketNums'], FILTER_SANITIZE_SPECIAL_CHARS);
			foreach (explode(",",$docketNumsRequest) as $doc) {
				if ($doc) { //Doc will be false if the filter fails.
					array_push($docketNums, $doc);
				}
			}
			$response['results']['dockets'] = $docketNums;
		}
		// doExpungements prints a table to the screen.
		// combineArrests also prints to the screen.
		// I only want to print a response object to the screen, so I put the
		// the functions that print
		// into an OutputBuffer to prevent that.
		ob_start();
		if (count($docketNums)>0) {
			//if the cpcms search has been run and has found dockets
			//or of docket numbers were sent with the request to the api
			$docketFiles = CPCMS::downloadDockets($docketNums);
			$record->parseDockets($tempFile, $pdftotext, $docketFiles);
			$record->integrateSummaryInformation(True);
			//set $isAPI in integrateSummaryInformation() to True to prevent printing to screen
			$record->combineArrests();
			$response['results']['arrestCount'] = $record->getTotalArrests();
			# TODO Could add a function to insert a string of arrest information into $response.
			# TODO Could also add a function to insert information about chargeObjects (child of Arrest)
		}
        $files=[];

		error_log("beginning to create petitions, if requested.");
		if (preg_match('/^(t|true|1)$/i', $request['createPetitions'])===1) {
    		$files = doExpungements($record->getArrests(), $templateDir, $dataDir, $record->getPerson(),
	    		$attorney, $_SESSION['expungeRegardless'],
		    	$db);
	    	//$response['results']['sealing'] = $parsed_results['sealing'];
			$files[] = createOverview($record->getArrests(), $templateDir, $dataDir, $record->getPerson());
			$files[] = $record->generateCleanSlateOverview($templateDir, $dataDir);
        }//end of creating petitions if createPetitions was set.

		ob_end_clean();

		$parsed_results = $record->parseArrests();
   		$response['results']['expungements_redactions'] = $parsed_results['expungements_redactions'];

        // create the zip file.  The $files array contains the petitions; it will be empty if createPetitions
        // isn't set to 1 or t or true
		$zipFile = zipFiles($files, $dataDir, $docketFiles,
				uniqid($record->getPerson()->getFirst() . $record->getPerson()->getLast(), true) . "Expungements");

		if (count($docketNums) > 0) {
			$response['results']['expungeZip'] = basename($zipFile);
		} else {
			$response['results']['status'] = "Error. No dockets downloaded. It would be nice if this message were more helpful.";
		}

		// write everything to the DB as long as this wasn't a "test" upload.
		// we determine test upload if a SSN is entered.  If there is no SSN, we assume that
		// there was no expungement either - it was just a test to see whether expungements were
		// possible or a test of the generator itself by yours truly.

		error_log("starting to write to db");
		if (isset($urlPerson['SSN']) && $urlPerson['SSN'] != "") {
			//error_log("writing to db:");
			//error_log("arrests:");
		    	//file_put_contents('php://stderr', print_r($arrests), TRUE);
			//error_log("person");
			//file_put_contents('php://stderr', print_r($person), TRUE);
			//error_log("attorney");
			//file_put_contents('php://stderr', print_r($attorney), TRUE);

			writeExpungementsToDatabase($record->getArrests(), $record->getPerson(), $attorney, $db);
			//error_log("wrote to db");
		}
		//error_log("cleaning up files");
		cleanupFiles($files);
		//error_log("done writing to db");


		error_log("checking whether to email petitions.");

		if (isset($request['emailPetitions']) && preg_match('/^(t|true|1)$/i', $request['emailPetitions'])===1){
			$log_trail .= ",emailing results";
			if (!(isset($request['createPetitions']) && preg_match('/^(t|true|1)$/i', $request['createPetitions'])===1)) {
				$file_path = NULL;
				if (isset($response['results']['expungeZip'])) {
					unset($response['results']['expungeZip']);
				}
			} else {
				$file_path = $response['results']['expungeZip'];
				$path_parts = pathinfo($response['results']['expungeZip']);
				$response['results']['expungeZip'] = $baseURL . "secureServe.php?serveFile=" . $path_parts['filename'];
			}
		    //mailPetition($_REQUEST['current_user'], $_REQUEST['current_user'], $response, $file_path);
		    error_log("Mailing to " . mailDestination($request));
		    mailPetition(mailDestination($request), mailDestination($request), $response, $file_path);
		} else {
			error_log("emailPetitions was not set");
		}

	}// end of processing req from a valid user
	error_log("Finished api request.");
	if (isset($user_id)) {
		writeToResourceLog($user_id,"eg-api.php",$log_trail);
	} else {
		writeToResourceLog(-1,"eg-api.php",$log_trail);
	}
	print_r(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES, 10));

	function malformedRequest($request) {
		// Given a dictionary $request
		// Return false if there are no missing values
		// 	but return a message if any of certain conditions are true.
		// This takes advantage of the truthiness of php strings
		// 	to supply a helpful message.

		// apikey should get checked first for a complicated reason. The requestbuilder also
		// checks if an apikey is missing, and will assume the request data was json if the api key
		// was missing. So if the request was HTTP, but missing an apikey, $request will be based on
		// the input json data, which is empty and has _no_ keys. Putting apikey key first here will
		// tell the user that the api key is missing, which is accurate, and gives them the right instruction
		// for correcting the issue.
		if ( empty($request['apikey']) ) {
			return "Key missing from request.";
		}

		if (empty($request['current_user'])) {
			return "User email missing from request.";
		}

		if ( ($request['cpcmsSearch'] == 'false') && empty($request['docketNums']) ) {
			return "If you do not wish to do a CPCMS search, then you must supply docket numbers.";
		}

		if ( !isset($request['createPetitions']) || ($request['createPetitions'] == '')  ) {
			return "Should I create petitions? Please include createPetitions=[0|1] in your request.";
		}


		return False;
	};//End of well-formed request





?>
