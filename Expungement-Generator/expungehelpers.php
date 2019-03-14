<?php
//******  begin helper functions *******************/





// loops through all of the Arrests in $arrests and does a paper and to screen expungement
// @return an array of the file names that were created during this process.
function doExpungements($arrests, $templateDir, $dataDir, $person, $attorney, $expungeRegardless, $db)
{
	$files = array();

	allDocketNumbers;

	foreach($arrests as $arrest) {
		$allDocketNumbers[] = implode("|", $arrest->getDocketNumber());
	}

	$allDocketNumbers = implode("|", $allDocketNumbers);

	print "<table class='pure-table pure-table-horizontal pure-table-striped'>";
    print "<thead><tr><th>Docket #</th><th>Expungeable</th><th>Optional Petitions</th></tr></thead>";
	foreach ($arrests as $arrest)
	{
		if($_SESSION["sealingRegardless"] && !in_array($arrest->getFirstDocketNumber(), $_SESSION['docket']))
			continue;

        print "<tr><td>".$arrest->getFirstDocketNumber()."</td><td>";
        if ($arrest->isArrestOnlyHeldForCourt() && !$expungeRegardless)
        {
            print "Held for Court</td><td>--</td>";
            print "<td><a href='?expungeRegardless=true&docket=" . implode("|",$arrest->getDocketNumber()) ."' target='_blank'><i>Exp. (rarely used)</i></a></td></tr>";
            print "</tr>";
        }
        else
        {
	  	  if ($arrest->isArrestSummaryExpungement($arrests) || $arrest->isArrestExpungement() ||  $arrest->isArrestOver70Expungement($arrests, $person) || $arrest->isArrestRedaction() || $expungeRegardless || $_SESSION['sealingRegardless'])
		  {
			$files[] = $arrest->writeExpungement($templateDir, $dataDir, $person, $attorney, $expungeRegardless, $db);

			// if this isn't a philly arrest and this is an agency that has IFP status, then add in
			// an IFP notice.
			if (($arrest->getCounty()!="Philadelphia" && $attorney->getIFP()) || $attorney->getIFP()==2)
				$files[] = $arrest->writeIFP($templateDir, $person, $attorney);

            if ($arrest->getCounty()=="Montgomery")
                $files[] = $arrest->writeCOS($templateDir, $person, $attorney);

			if ($arrest->isArrestExpungement() || $arrest->isArrestSummaryExpungement($arrests) || $expungeRegardless)
				print "Expungement";
			else if ($arrest->isArrestOver70Expungement($arrests, $person))
				print "Expungement (over 70)";
            else if ($_SESSION['sealingRegardless'])
                print "Sealing Sealing";
			else
				print "Partial Expungement";
          }
          else
            print "No";


      print "</td>";

      // allow generation of sealing and Pardon petitions
      print (
				"<td><a href='?sealingRegardless=true&docket=" .
				implode("|",$arrest->getDocketNumber()) .
				"&otherDockets='" . $allDocketNumbers .
				"' target='_blank'>Sealing</a> | " .
				"<a href='?expungeRegardless=true&docket=" . implode("|",$arrest->getDocketNumber()) .
				"' target='_blank'>Pardon</a></td></tr>");
    } // if held for court
	}
	print "</table>";
	return $files;
}

// creates an overview document that lists all of the relevant information for the advocate
function createOverview($arrests, $templateDir, $dataDir, $person)
{
    $docx = new \PhpOffice\PhpWord\TemplateProcessor($templateDir . Arrest::$overviewTemplate);

	// set person variables
	$docx->setValue("NAME", htmlspecialchars($person->getFirst() . " " . $person->getLast(), ENT_COMPAT, 'UTF-8'));
	//$docx->setValue("PPID", htmlspecialchars($person->getPP(), ENT_COMPAT, 'UTF-8'));
	//$docx->setValue("SID", htmlspecialchars($person->getSID(), ENT_COMPAT, 'UTF-8'));

	if (sizeof($arrests) > 0)
		$docx->setValue("DOB", htmlspecialchars($arrests[0]->getDOB(), ENT_COMPAT, 'UTF-8'));

	$docx->cloneRow("DOCKET", count($arrests));

    $i = 1;
    $j=1;
	foreach ($arrests as $arrest)
	{
		$expType = "No expungement possible";
		if ($arrest->isArrestRedaction())
			$expType = "Partial Expungement";
		if ($arrest->isArrestExpungement())
			$expType = "Expungement";
		if ($arrest->isArrestARDExpungement())
			$expType = "ARD Expungement***";
		if ($arrest->isArrestSummaryExpungement($arrests))
			$expType = "Summary Expungement";
		if ($arrest->isArrestOver70Expungement($arrests, $person))
			$expType = "Expungement (over 70)";
        if ($_SESSION['sealingRegardless'])
            $expType = "Sealing";
		$docx->setValue("DOCKET#" . $i, htmlspecialchars(implode(", ", $arrest->getDocketNumber()), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("PDFNAME#" . $i, htmlspecialchars($arrest->getPDFFileName(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("OTN#" . $i, htmlspecialchars($arrest->getOTN(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("EXPUNGEMENT_TYPE#" . $i, htmlspecialchars($expType, ENT_COMPAT, 'UTF-8'));
		$docx->setValue("UNPAID_COSTS#" . $i, htmlspecialchars(number_format($arrest->getCostsTotal() - $arrest->getBailTotal(),2), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("BAIL#" . $i, htmlspecialchars(number_format($arrest->getBailTotalTotal(),2), ENT_COMPAT, 'UTF-8'));
        $i = $i+1;

	}

	$outputFile = $dataDir . $person->getFirst() . $person->getLast() . "Overview.docx";
	$docx->saveAs($outputFile);

	return $outputFile;
}

// writes the expungements to the database
// @return none
function writeExpungementsToDatabase($arrests, $person, $attorney, $db)
{

	// we only record some information in the database. For
	// a host program we write everything to the DB. For other programs
	// that use the EG, we only want to update the number of total
	// petitions generated and return
	if ($attorney->getSaveCIToDatabase()==1) {
		// otherwise, write the defendant into the db if he doesn't already exist
		$person->writePersonToDB($db);
	}

	$total = 0;
	// and then for each arrest, write the arrest into the database as well
	foreach ($arrests as $arrest)
	{
		// count the number of petitions prepared
		if ($arrest->isArrestExpungement() || $arrest->isArrestRedaction() || $arrest->isArrestSummaryExpungement($arrests))
			$total++;
		// only add this to the db for certain programs
		if ($attorney->getSaveCIToDatabase()==1)
			$arrest->writeExpungementToDatabase($person, $attorney, $db, true);
	}
	$attorney->updateTotalPetitions($total, $db);
	return;
}

// prints out the expungement data to the screen
function screenDisplayExpungements($arrests)
{
	foreach ($arrests as $arrest)
	{
		print "expungement? " . $arrest->isArrestExpungement() . "<br />";
		print "redaction? " . $arrest->isArrestRedaction() . "<br />";
		print "held for court? ". $arrest->isArrestHeldForCourt() . "<br />";
		$arrest->simplePrint();
		print "<br />";

	}
}

// Takes the json response object andcreates a human readable message to send to the email address associated with an api request.
function createHumanReadableExpungementResponseFromJSON($response)
{
    $expungementInfo = json_decode($response, true);

    $msg = "The Expungement Generator ";
    $msg .= "searched CPCMS for _{$expungementInfo['personFirst']} {$expungementInfo['personLast']}_ with DOB _{$expungementInfo['dob']}_ and found _{$expungementInfo['results']['arrestCount']}_ arrests.";

    $msg .= "\r\n\r\n\r\n";

    // for each arrest in the expungementInfo array, add on the docket number and the expungement type.
    // we probably need error checking code to see if there are any results!
    foreach ($expungementInfo['results']['expungements_redactions'] as $arrest)
    {
        // we may want to split the docket at the first comma and just include anything before the comma
        // if there are multiple docket numbers associated with one case, they all show up under docket and
        // the response could be sort of long on a line/line basis.
        $msg .= "{$arrest['docket']} | {$arrest['expungement_type']}\r\n";
    }

    return $msg;
}

// prints some general help text for the final page of the expungement process
function printFinalPageHelpText()
{
	print <<<END
	<div><br/>
		<b>Brief Instructions:</b>
		<ul>
		<li>On its first pass, the EG only prepares expungement petitions.  The .zip link will include all expungement petitions, an overview, and a sealing overview that helps to determine whether each case is sealable or not.</li>
		<li>If the EG indicates that expungement is not available and the case involves a misdemeanor that is more than 10 years old, the case might be sealed. The sealing overview in the zip file can help you to analyze whether each case is sealable.</li>
 		<li>If you decide that a case is sealable, the EG can prepare a sealing petition for you.  To prepare a sealing petition, click on the “sealing” button next to the case. Unlike expungement petitions, sealing petitions will be produced one at a time, meaning you will have to press the sealing button next to a case, download the zip file, and then move on to the next case.</li>
		<li>The “sealing” button will prepare a petition only if you downloaded docket sheets from CPCMS, not if you uploaded your own.  This is a known bug and limitation.</li>
		<li>If CPCMS lists a different name or date of birth for your client on a specific docket, that docket may download only if you search by the name that appears on the dockets.  If you prepare a petition using a name that is not your client's, be sure to change the “petitioner” in the body of the order and petition to your client’s current name after you generated the petition; keep the original information in the caption.</li>
	</ul></div>
END;
}
