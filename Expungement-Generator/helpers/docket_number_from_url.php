<?php

  function docketNumberFromURL($docketURL) {
    // Given a Docket's URL, return the docket number.
    if (preg_match(
      '/^.*(?P<docket_number>[A-Z]{2}-[0-9]{2,5}-[A-Z]{2}-[0-9]{7}-[0-9]{4})&.*$/',
      $docketURL, $matches)) {
          // error_log(
          //   "Figured out the docket url."
          // );
          return $matches["docket_number"];
      } else {
        error_log("Could not figure out docket number from: " . $docketURL);
        return $docketURL;
      }
  }
?>
