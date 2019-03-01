<?php

  function docketNumberFromURL($docketURL) {
    // Given a Docket's URL, return the docket number.
    error_log(
        "Docket URL: " . $docketURL . " to " .
        substr($docketURL, -54, 21));
    return(substr($docketURL, -54, 21));
  }
?>
