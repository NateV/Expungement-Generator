<?php
// @todo replace mysql_escape_string with mysql_real_escape_string
// @todo check login

/***********************************************************************
*
*	expunge.php
*	The main controller for actually completing the expungements.  Deals with
*   dump of all docket sheets and summary sheet, sends them all to be parsed,
*	combines information as needed, and then makes calls to generate reports.
*
*	Copyright 2011-2015 Community Legal Services
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file except in compliance with the License.
* You may obtain a copy of the License at
*
*    http://www.apache.org/licenses/LICENSE-2.0

* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
**
***********************************************************************/


require_once("config.php");
require_once("Record.php");
require_once("Arrest.php");
require_once("ArrestSummary.php");
require_once("Person.php");
require_once("Attorney.php");
require_once("utils.php");
require_once("CPCMS.php");
require_once("expungehelpers.php");

include('head.php');
include('header.php');
?>

<div class="main">
    <div class="pure-u-5-24">&nbsp;</div>
    <div class="pure-u-14-24">

<?php
// if the user isn't logged in, then don't display this page.  Tell them they need to log in.
if (!isLoggedIn())
include("displayNotLoggedIn.php");

// they are logged in, see if we were supposed to do a CPCMS search or if they are sending
// us files from CPCMS themselves
else if (isset($_POST['cpcmsSearch']) && $_POST['cpcmsSearch'] == "true")
{
    // this is a CPCMS search.  So do one, display the results in a form (with hidden fields for
    // entries from teh previous screen), and shoot everything back here to do the expungements
    $urlPerson = getPersonFromPostOrSession();
    $cpcms = new CPCMS($urlPerson['First'], $urlPerson['Last'], $urlPerson['DOB']);
    $status = $cpcms->cpcmsSearch();
    $statusMDJ = $cpcms->cpcmsSearch(true);
    if ((count($cpcms->getResults()) == 0) &&
        (count($cpcms->getMDJResults()) == 0)) {
    // if (!preg_match("/Status: 0/",$status[0]) && !preg_match("/Status: 0/", $statusMDJ[0]))
        print "<br/><b>Your search returned no results.  This is probably because there is no one with the name '" . $urlPerson['First'] . " " . $urlPerson['Last'] . "' in the court database.</b><br/><br/>  The other possibliity is that CPCMS is down.  You can press back and try your search again or you can check <a href='https://ujsportal.pacourts.us/DocketSheets/CP.aspx' target='_blank'>CPCMS by clicking here and doing your search there</a>.</b>";
    }
    else
    {
        //only integrate the summary information if we
        // have a DOB; otherwise what is the point?
        if (!empty($urlPerson['DOB']))
            $cpcms->integrateSummaryInformation();

        // remove the cpcmsSearch variable from the POST vars and then pass them to
        // a display funciton that will display all of the arrests as a webform, with all
        // of the post vars re-posted as hidden variables.  Also pass this filename as the
        // form action location.
        unset($_POST['cpcmsSearch']);

        $cpcms->displayAsWebForm(basename(__FILE__));
    }
}

else
{ // CPCMS Search is false or not sent in the POST at all.
    if (isset($_POST['docket']))
        $_SESSION['docket'] = $_POST['docket'];
    if (isset($_POST['scrapedDockets']))
        $_SESSION['scrapedDockets'] = $_POST['scrapedDockets'];

    // get information about the person from the POST vars passed in
    $urlPerson = getPersonFromPostOrSession();
    $person = new Person($urlPerson['First'], $urlPerson['Last'], $urlPerson['SSN'], $urlPerson['Street'], $urlPerson['City'], $urlPerson['State'], $urlPerson['Zip']);
    $record = new Record($person);

    // set some session vars based on get and post vars
    getInfoFromGetVars();

    // make sure to change this in the future to prevent hacking!
    $attorney = new Attorney($_SESSION["loginUserID"], $db);
    if($GLOBALS['debug'])
        $attorney->printAttorneyInfo();

    // parse the uploaded files will lead to expungements or redactions
    error_log("number of files uploaded: " . sizeof($_FILES));
    $docketFiles = $_FILES;
    if (isset($_SESSION['scrapedDockets']))
    {
        // I think the user did a CPCMS search, which puts the dockets found
        // in the search into a session variable called scrapedDockets.
        error_log("scrapedDockets is set");
        $allDockets = array_merge($_SESSION['docket'], explode("|", $_REQUEST['otherDockets']));
        $docketFiles = CPCMS::downloadDockets($allDockets);
    } else {
      error_log("scrapedDockets is not set in the session..");
      // I think user has clicked the Sealing or Pardon links,
      // after uploading their dockets, rather than doing a cpcms search.
      if (isset($_REQUEST['docket']) && isset($_REQUEST['otherDockets'])) {
        // I think user has clicked the Sealing link, which specifies a target
        // docket, but also all the other dockets b/c they are relevant to
        // reviewing the full record for sealing.
        $allDockets = array_merge(array($_REQUEST['docket']), explode("|", $_REQUEST['otherDockets']));
        $docketFiles = CPCMS::findFiles($allDockets);
      }
      if (isset($_REQUEST['docket']) && !isset($_REQUEST['otherDockets'])) {
        // I think that the user has clicked the Pardon link, which only
        // sends the docket param, because only this one docket will be relevant
        // to a pardon application.
        $docketFiles = CPCMS::findFiles(array($_REQUEST['docket']));
      }
    }

    error_log("There are " . sizeof($docketFiles) . " dockets to parse");
    if (sizeof($docketFiles) > 0) {
      $record->parseDockets($tempFile, $pdftotext, $docketFiles);
    }

    // integrate the summary information in with the arrests
    $record->integrateSummaryInformation();

    print "<b>EXPUNGEMENT INFORMATION</b><br/><br/>";
    // combine the docket sheets that are from the same arrest
    #$arrests = combineArrests($arrests);
    $record->combineArrests();


    // check to see if Act5 Sealable
    error_log("checking sealing eligibility.");
    $record->checkSealingEligibility();


    // do the expungements in PDF form
    error_log("doing expungments.");
    $files = doExpungements($record->getArrests(), $templateDir, $dataDir, $record->getPerson(), $attorney, $_SESSION['expungeRegardless'], $db);
    $files[] = createOverview($record->getArrests(), $templateDir, $dataDir, $record->getPerson());

    $files[] = $record->generateCleanSlateOverview($templateDir, $dataDir);

    // zip up the final PDFs
    $zipFile = zipFiles($files, $dataDir, $docketFiles, $record->getPerson()->getFirst() . $record->getPerson()->getLast() . "Expungements");

    print "<div>&nbsp;</div>";
    if (count($files) > 0)
        print "<div><b>Download Petitions and Overview: <a href='secureServe.php?serveFile=" . basename($zipFile). "'>" . basename($zipFile) . "</a></b></div>";
    else
        print "<div><b>No expungeable or redactable offenses found for this individual.</b></div>";

    printFinalPageHelpText();
    // write everything to the DB as long as this wasn't a "test" upload.
    // we determine test upload if a SSN is entered.  If there is no SSN, we assume that
    // there was no expungement either - it was just a test to see whether expungements were
    // possible or a test of the generator itself by yours truly.
    if (isset($urlPerson['SSN']) && $urlPerson['SSN'] != "")
    writeExpungementsToDatabase($record->getArrests(), $record->getPerson(), $attorney, $db);


    // if we are debuging, display the expungements
    if ($GLOBALS['debug'])
    screenDisplayExpungements($record->getArrests);

    // cleanup any files that are left over
    cleanupFiles($files);
} // if isLoggedIn()
        ?>
    </div> <!-- content-center -->
    <div class="pure-u-4-24"><?php include("expungementDisclaimers.php");?></div>
</div>

<?php
include ('foot.php');
