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
function docketNameSearch($firstName, $lastName, $dob, $mdj) {
  global $docketScraperAPIURL;
  $ch = curl_init($docketScraperAPIURL . "/" .
                  "searchName/" .
                  ($mdj? "MDJ" : "CP"));
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

  if ($jsonResults["status"] != "success") {
    error_log($jsonResults["status"]);
  }

  return $jsonResults;
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
  $ch = curl_init($docketScraperAPIURL . "/" .
                  "lookupDocket/" .
                  ($mdj? "MDJ" : "CP"));
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
