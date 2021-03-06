<?php


require_once("config.php");
// Use the docket scraper api to search a person's information by name.
//
// Args:
//   firstName (str): first name of a person
//   lastName (str): last name of a person
//   dob (str or false): optional date of birth
//   mdj (bool): whether to search mdj. False indicates a Common Pleas search.
//
// Returns:
//   associative array of the json response from the api, with the results of
//   the search.
function docketNameSearch($firstName, $lastName, $dob) {
  global $docketScraperAPIURL;
  $ch = curl_init($docketScraperAPIURL . "/" .
                  "ujs/search/name/");
  $jsonData = array(
    "first_name" => $firstName,
    "last_name" => $lastName
  );

  if ($dob) {
    $jsonData["dob"] = $dob;
  }


  $jsonDataEncoded = json_encode($jsonData);
  //Tell cURL that we want to send a POST request.
  curl_setopt($ch, CURLOPT_POST, 1);
  //Attach our encoded JSON string to the POST fields.
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
  //Do not print request results.
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  //Set the content type to application/json
  curl_setopt(
      $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  $result = curl_exec($ch);
  curl_close($ch);
  //get the json results
  $jsonResults = json_decode($result, true);


  return $jsonResults;
}


function manyDocketNumberSearch($dnQueue, $mdj) {
  error_log("Searching for " . count($dnQueue) . " docket numbers at once.");
  global $docketScraperAPIURL;
  $ch = curl_init($docketScraperAPIURL . "/ujs/search/docket/many/");
  $jsonData = array(
    "docket_numbers" => $dnQueue
  );
  $jsonDataEncoded = json_encode($jsonData);
  //Tell cURL that we want to send a POST request.
  curl_setopt($ch, CURLOPT_POST, 1);
  //Attach our encoded JSON string to the POST fields.
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
  //Set the content type to application/json
  curl_setopt(
      $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  //Do not print request results.
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  $result = curl_exec($ch);
  error_log("Completed searching for " . count($dnQueue) . " docket numbers at once.");
  curl_close($ch);
  $jsonResults = json_decode($result, true);

  if (array_key_exists("searchResults", $jsonResults) && (array_key_exists("dockets", $jsonResults["searchResults"]))) {
    error_log("Found " . count($jsonResults["searchResults"]["dockets"]) . " of " . count($dnQueue) . " .");
    //error_log(oprint_r($jsonResults,TRUE));
    error_log("---finished multiple docket search---");
    return $jsonResults["searchResults"];
  }
  error_log("Found no search results!");
  //error_log(print_r($jsonResults, TRUE));
  error_log("--finished multiple docket search---");
  return array("dockets"=>array());
}


// Use the docket scraper api to search for a specific docket.
//
// Args:
//   docketNumer: The number of a docket to find.
//
// Returns:
//   associative array with information about a specific docket.
function docketNumberSearch($docketNumber, $mdj) {
  global $docketScraperAPIURL;
  $ch = curl_init($docketScraperAPIURL . "/ujs/search/docket/");
  $jsonData = array(
    "docket_number" => $docketNumber
  );

  $jsonDataEncoded = json_encode($jsonData);
  //Tell cURL that we want to send a POST request.
  curl_setopt($ch, CURLOPT_POST, 1);
  //Attach our encoded JSON string to the POST fields.
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
  //Set the content type to application/json
  curl_setopt(
      $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
  //Do not print request results.
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_VERBOSE, 0);
  $result = curl_exec($ch);
  curl_close($ch);
  $jsonResults = json_decode($result, true);

  return $jsonResults;
}

?>
