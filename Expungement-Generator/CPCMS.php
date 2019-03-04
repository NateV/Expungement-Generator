<?php
/*********************************
 *
 * CPCMS.php
 * The main class for searching CPCMS and scraping the content from there.
 * Relies heavily on a casperjs script that Nate Vagel and Michael Hollander Wrote
 *
 * Copyright 2011-2016 Community legal Services
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
 *
 **********************************/

require_once("config.php");
require_once("ArrestSummary.php");
require_once("helpers/docketsearch_api.php");
require_once("helpers/docket_number_from_url.php");

class CPCMS
{
    private $first;
    private $last;
    private $dob = null;

    // array of associative arrays w/ docket info
    private $results = array();

    // array of associative arrays w/ docket info
    private $resultsMDJ = array();

    private $bestSummaryDocketNumber;
    private $bestSummaryDocketNumberMDJ;

    // All removed, because these base urls no longer work.
    // TODO delete these
    // public static $docketURL = "https://ujsportal.pacourts.us/DocketSheets/CPReport.ashx?docketNumber=";
    // public static $summaryURL = "https://ujsportal.pacourts.us/DocketSheets/CourtSummaryReport.ashx?docketNumber=";
    // public static $docketURLMDJ = "https://ujsportal.pacourts.us/DocketSheets/MDJReport.ashx?docketNumber=";
    // public static $summaryURLMDJ = "https://ujsportal.pacourts.us/DocketSheets/MDJCourtSummaryReport.ashx?docketNumber=";


    // expects that the date will come in YYYY-MM-DD formate, but CPCMS requires mm/dd/yyy format
    public function __construct($first, $last, $dob)
    {
        $this->first = htmlspecialchars(stripslashes($first));
        $this->last = htmlspecialchars(stripslashes($last));

        if (($time = strtotime($dob))!==false)
            $this->dob = date("m/d/Y", $time);
    }


    // searches CPCMS for the person matchine first, last, and DOB.  If no DOB is specified, returns
    // the first page of results from a search of just the first and last name.
    // param mdj will do an MDJ search and modify the MDJ member not the regular cpcms member.
    // Returns the sttaus code.
    public function cpcmsSearch($mdj=false) {
        error_log("Conducting cpcms search");
        $results = docketNameSearch(
            $this->first, $this->last,
            isset($this->dob) ? $this->dob : false,
            $mdj);

        // $searchString = " --first='$this->first' --last='$this->last'";
        //
        // // if a DOB is specified, then search for it.  If not, only return the first page of results
        // if (isset($this->dob))
        //   $searchString = $searchString . " --DOB=$this->dob";
        // else
        //   $searchString = $searchString . " --limit";
        //
        // if ($mdj)
        //   $searchString = $searchString . " --mdj";
        //
        // $command = $GLOBALS['casperjsCommand'] . " " . $GLOBALS['casperScript'] . $searchString;
        // //print $command . "<br/>";
        // exec($command, $results);

        $status = $results["status"];

        // foreach($results as $key=>$value)
        // {
        //    $results[$key] = explode(" | ", $value);
        // }
        // $status = array_shift($results);
        if ($mdj && ($status == "success"))
            $this->resultsMDJ = $results["dockets"];
        else if ($status == "success")
            $this->results = $results["dockets"];
        else {
            $this->results = [];
            $this->resultsMDJ = [];
        }

        return $status;
    }


    public function getResults()   { return $this->results; }
    public function getMDJResults()   { return $this->resultsMDJ; }

    // assumes a result array that looks like this:
    // [1..n]->Docket Number | Active/inactive | OTN | DOB
    //
    public function printResultsForWeb()
    {

        $this->sortResults();

        //first print CP cases, then print MDJ
        $summaryCase = $this->findBestSummaryDocketNumber();

        print "<a href='$this->summaryURL" . $summaryCase . "' target='_blank'>Summary Docket</a>";
        print "<table class='pure-table'><thead><th>Docket Number</th><th>Status</th><th>OTN</th><TH>DOB</TH></thead>";
        foreach ($this->results as $result)
        {
            print "<tr>";
            print (
              "<td><a href='" .
              $result["docket_sheet_url"] .
              "' target='_blank'>" .
              $result["docket_number"] .
              "</a>");
            print (
              "(<a href='" . $result["summary_url"] .
              "' target='_blank'>s</a>)</td>");
            print "<td>" . $result["status"] . "</td>";
            print "<td>" . $result["otn"] . "</td>";
            print "<td>" . $result["dob"] . "</td>";
            print "</tr>";
        }
        print "</table>";

        //MDJ
        if (count($resultsMDJ > 0))
        {
            $summaryCaseMDJURL = $this->findBestSummaryDocketNumberMDJ();
            print "<br/><b>MDJ Cases</b>";
            print (
                "<a href='" . $summaryCaseMDJURL . "' target='_blank'>Summary Docket (MDJ)</a>");
            print "<table class='pure-table'><thead><th>Docket Number</th><th>Status</th><th>OTN</th><TH>DOB</TH></thead>";
            foreach ($this->resultsMDJ as $result)
            {
                print "<tr>";
                print (
                  "<td><a href='" .
                  $result["docket_sheet_url"] . "' target='_blank'>" .
                  $result["docket_number"] . "</a>");
                print (
                  "(<a href='" . $result["summary_url"] .
                  "' target='_blank'>s</a>)</td>");
                print "<td>" . $result["status"] . "</td>";
                print "<td>" . $result["otn"] . "</td>";
                print "<td>" . $result["dob"] . "</td>";
                print "</tr>";
            }
            print "</table>";
        }
    }


    public function displaySearchForm($postLocation)
    {
        // print out a form to resubmit your search query
        print "<form action='$postLocation' method='post'>";

print <<<END
            <div class="form-item">
                <label for="personFirst">Client's Name</label> <!--'-->
                <div class="form-item-column">
END;
                    print '<input type="text" name="personFirst" id="personFirst" class="form-text" value="' . $_SESSION['urlPerson']['First'] . '" />';
print <<<END
                </div>
                <div class="form-item-column">
END;
                    print '<input type="text" name="personLast" id="personLast" class="form-text" value="' . $_SESSION['urlPerson']['Last'] . '" />';
print <<<END
          </div>
                <div class="space-line"></div>
                <div class="description">
                    <div class="form-item-column">
                        First Name
                    </div>
                    <div class="form-item-column">
                        Last Name
                    </div>
                </div>
                <div class="space-line"></div>
            </div>
            <div class="form-item">
                <label for="personDOB">Date of Birth</label>
END;
                    print '<input type="date" id="date" name="personDOB" value="' . $_SESSION['urlPerson']['DOB'] . '" maxlength="10"/>';
PRINT <<<END
                <div class="description">MM/DD/YYYY</div>
            </div>
            <input type="hidden" name="cpcmsSearch" value="true" />
            <div class="form-item">
                <input type="submit" value="Redo CPCMS Search" />
            </div>
        </form>
END;
    }

    // displays the search data in a table; if $form is true, puts in a form and includes a checkbox in the
    // first column
    public function displayResultsInTable($postLocation, $form)
    {
        $this->sortResults();
        $summaryCaseURL = $this->findBestSummaryDocketNumber();
        $summaryCaseMDJURL = $this->findBestSummaryDocketNumberMDJ();

        print "<div class='space-line'>&nbsp;</div>";
        print "<div class='boldLabel'>Docket Sheets downloaded from CPCMS</div>";
        print "<div class='space-line'>&nbsp;</div>";

        if (empty($this->dob))
          print "<div class='boldLabel'>Only showing the first page of results from CPCMS because no DOB specified.  Note that because we are only searching the first page of results and so many MDJ dockets are not criminal, there is a reasonable chance that no MDJ dockets will show up when no DOB is specified.  If you put in a DOB, your client's criminal MDJ cases will show up.</div>";

        if (!empty($summaryCaseURL))
        {
            print "<div class='boldLabel'>";
            print "<a href='". $summaryCaseURL . "' target='_blank'>Summary Docket</a>";
            print "</div>";
        }
        if (!empty($summaryCaseMDJURL))
        {
            print "<div class='boldLabel'>";
            print "<a href='". $summaryCaseMDJURL . "' target='_blank'>MDJ Summary Docket</a>";
            print "</div>";
        }
        print "<div class='space-line'>&nbsp;</div>";
        if ($form)
           print "<form action='$postLocation' method='post'>";
        print "<table class='pure-table'><thead>";
        if ($form)
           print "<th>&nbsp;</th>";
        print "<th>Docket Number</th><th>Status</th><th>OTN</th>";

        // only print the DOB field if we are viewing
        if (empty($this->dob))
          print "<TH>DOB</TH>";
        print "</thead>";
        if ($form)
          print "<tr><td><input type='checkbox' id='checkAll' checked='checked'/></td><td>Check/Uncheck All</td></tr>";
        foreach ($this->results as $result)
        {
            print "<tr>";
            if ($form)
              print (
                "<td><input type='checkbox' name='docket[]' value='" .
                $result["docket_number"] .
                "' checked='checked' class='checkItem' /></td>");
            print (
              "<td><a href='" . $result["docket_sheet_url"] .
              "' target='_blank'>" . $result["docket_number"] .
              "</a>");
            print "(<a href='" . $result["summary_url"] . "' target='_blank'>s</a>)</td>";
            print "<td>" . $result["case_status"] . "</td>";
            print "<td>" . $result["otn"] . "</td>";

            // only print the DOB if we are looking at a general search
            if (empty($this->dob))
                print "<td>" . $result["dob"] . "</td>";
            print "</tr>";
        }
        foreach ($this->resultsMDJ as $result)
        {
            print "<tr>";
            if ($form)
              print (
                "<td><input type='checkbox' name='docket[]' value='" .
                $result["docket_number"] .
                "' checked='checked' class='checkItem' /></td>");
            print (
              "<td><a href='" . $result["docket_sheet_url"] .
              "' target='_blank'>" . $result["docket_number"] .
              "</a>");
            print "(<a href='". $result["summary_url"] . "' target='_blank'>s</a>)</td>";
            print "<td>" . $result["case_status"] . "</td>";
            print "<td>" . $result["otn"] . "</td>";

            // only print the DOB if we are looking at a general search
            if (empty($this->dob))
                print "<td>" . $result["dob"] . "</td>";
            print "</tr>";
        }
        print "</table>";

    }

    public function formClose()
    {

print "
        <div class='form-item'>
        <input type='hidden' name='scrapedDockets' value='true'>
        <input type='submit' value='Expunge' />
        </div>
        </form>
        <script type='text/javascript'>
        // this allows us to have a checkall/deselect all checkbox
        $('#checkAll').click(function () {
            $(':checkbox.checkItem').prop('checked', this.checked);
        });
        </script>

"; //"
    }

    // assumes a result array that looks like this:
    // [1..n]->Docket Number | Active/inactive | OTN | DOB
    // will create a form that displays all of the dockets with some information about them
    // as well as hidden fields from all of the postVars passed in.  The form will submit
    // to the $postLocation
    public function displayAsWebForm($postLocation)
    {
        $this->displaySearchForm($postLocation);
        $this->displayResultsInTable($postLocation, true);
        $this->formClose();
    }

    // Returns the URL of the summary docket that we think is the most useful.
    //
    // If there is no best summary docket number, then retuns null.
    public function findBestSummaryDocketNumber()
    {
        if (count($this->results) < 1)
            return null;

        if (isset($this->bestSummaryDocketNumber)) {//is this ever set?
          return $this->bestSummaryDocketNumber;
        } else {
          $number = $this->findBestSummaryDocket($this->results, "CR");
        }

        if ($number === 0)
            return $this->results[0]["summary_url"];
        else
            return $number;
    }


    // Returns the URL of the summary docket that we think is the most useful.
    //
    // If there is no best summary docket number, then retuns null.
    public function findBestSummaryDocketNumberMDJ()
    {
        if (count($this->resultsMDJ) < 1)
            return null;

        if (isset($this->bestSummaryDocketNumberMDJ))
          return $this->bestSummaryDocketNumberMDJ;

        else $number = $this->findBestSummaryDocket($this->resultsMDJ, "(TR|NT|CR)");

        if ($number === 0)
            return $this->results[0]["summary_url"];

        else
            return $number;
    }

    // finds the best case to use to look at a person's summary docket
    // (a roll up of all of their dockets)
    // Older cases are generally not good for this
    // (what is older?  Maybe I should just look for the most recent)
    // Closed cases are better.  Cases with a -CR- in them are better
    // (criminal cases), SU cases second best
    // Helpfully, the results returned by CPCMS are generally already in the correct order.
    // returns 0 if there are no docketes that fit the bill
    private function findBestSummaryDocket($aResults, $bestDocketTell)
    {
        // default case--if there is only one docket number, return 0; this tells the
        // user of the function to use the first docket number since there are none that match our criteria
        // this also prevents an infinite recursion
        if (count($aResults) == 0)
            return 0;

        // if this is a closed case and it is criminal, return it
        if (preg_match("/Closed/", $aResults[0]["case_status"]) && preg_match("/\-".$bestDocketTell."\-/", $aResults[0]["docket_number"]))
            return $aResults[0]["summary_url"];

        // otherwise, remove the first element from the array (the one that we just rejected) and try again
        else
        {
            array_shift($aResults);
            // perform the same exercise on the remaining dockets
            return $this->findBestSummaryDocket($aResults, $bestDocketTell);
        }
    }

    // Looks up the summary docket for one case and reads through it.  If there are any additional
    // cases to add to the array, adds them in with all of the relevant information.
    public function integrateSummaryInformation()
    {
        error_log("Integrating Summary Information");
        $summaryURL = $this->findBestSummaryDocketNumber();
        $summaryDocketNumber = docketNumberFromURL($summaryURL);
        $summaryFile = $this->getDocket(
            $summaryURL, $summaryDocketNumber, true );

        $command = $GLOBALS['pdftotext'] . " -layout \"" . $summaryFile . "\" \"" . $GLOBALS['tempFile'] . "\"";
        system($command, $ret);
        //        print "<br>The pdftotext command: $command <BR />";

        if ($ret == 0)
        {
            $thisRecord = file($GLOBALS['tempFile']);
            $summary = new ArrestSummary();
            $summary->readArrestSummary($thisRecord);
            $sArrests = $summary->getArrests();

            // compare the arrests from the summary docket to the
            // arrests already on this CPCMS object.  Add in any arrests
            // that weren't already there with a notation that they were found on the summary
            $docketsFound = $this->results;
            foreach ($sArrests as $arrest)
            {
                // each arrest could have multiple docket numbers
                foreach ($arrest->getDocketNumber() as $dn)
                {
                    $add = true;
                    // check each arrest against the arrests stored on this object
                    foreach ($docketsFound as $docketFound)
                    {
                        // if we find a match between this docket number and a docket number
                        // in our results list, break and go on to the next docket numbe rin our list
                        if ($dn==$docketFound["docket_number"])
                        {
                            $add = false;
                            break;
                        }
                    }

                    // if we never found a match, add this docket number to
                    // the list of results.
                    // false indicates that this is a CP, not MDJ docket.
                    $this->results[] = docketNumberSearch($dn, false)["docket"];

                      // replaced by a function to add a single docket to the
                      // the results array
                      // array(
                      //   "docket_number" => $dn,
                      //   "otn" => $arrest->getOTN(),
                      // );
                }// end loop through dockets associated w/ an arrest
            }// end loop through list of arrests found in a summary docket
        }
    }

    // downloads a docketsheet from CPCMS.
    public static function getDocket($docketURL, $docketNumber, $isSummary)
    {
        $ch = CPCMS::initConnection();
        curl_setopt($ch, CURLOPT_URL, $docketURL);

        $filename = $GLOBALS['dataDir'] . $docketNumber;
        if ($isSummary) $filename = $filename . "Summary";
        $filename = $filename . ".pdf";
        $fp = fopen($filename, "w");
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec($ch);
        fclose($fp);
        curl_close($ch);

        // return the filename
        return $filename;

    }


    // initializes a connection for CURL
    public static function initConnection()
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FORBID_REUSE, FALSE);
        return $ch;
    }


    // Sort the $results member by year of case, with the most recent cases at the start of the array
    private function sortResults()
    {
        if (sizeof($this->results) === 0) {
          return;
        }
        error_log("Sorting results");
        usort($this->results, function($a,$b) {
           return substr($b["docket_number"],-4) -
                  substr($a["docket_number"],-4);
        });

        usort($this->resultsMDJ, function($a,$b) {
           return substr($b["docket_number"],-4) -
                  substr($a["docket_number"],-4);
        });
    }

    // takes an array of docket urls and downloads them.
    //
    // If $summaryURL is included, it is the url of a docket to save with
    // Summary in the file name.
    //
    // If a file already exists in the $data dir,
    // will just use that and not redownload.
    // returns an associative array like the $_FILES array.  It will have the following fields:
    // $files['userFile']['tmp_name'][] which is the path to the file
    // $files['userFile']['size'][] which is the size of the file
    // $files['userFile']['name'][] which is the name of the file (the docket number is this case
    public static function downloadDockets($docketURLs, $summaryURL = none)
    {
        $files = array();

        // sort the dockets in reverse cron order
        // usort ($dockets, function($a,$b) {
        //     return substr($b, -4) - substr($a ,-4);
        // });

        foreach ($docketURLs as $du)
        {
            // first check to see if there is already a docket downloaded with this number
            $dn = docketNumberFromURL($du);
            $thisFile = $GLOBALS['dataDir'] . $dn . ".pdf";
            if (!(file_exists($thisFile) && filesize($thisFile) > 0))
                $thisFile = CPCMS::getDocket($du, $dn, false);
            $files['userFile']['tmp_name'][] = $thisFile;
            $files['userFile']['size'][] = filesize($thisFile);
            $files['userFile']['name'][] = $dn . ".pdf";
        }

        // now download the summary, if provided
        if ($summaryURL) {
          $summaryNumber = docketNumberFromURL($summaryURL);
          $thisFile = (
            $GLOBALS['dataDir'] . "Summary" .
            $summaryNumber . ".pdf"
          );
          if (!(file_exists($thisFile) && filesize($thisFile) > 0)) {
            $thisFile = CPCMS::getDocket(
              $summaryURL, $summaryNumber, true
            );
          }
          $files['userFile']['tmp_name'][] = $thisFile;
          $files['userFile']['size'][] = filesize($thisFile);
          $files['userFile']['name'][] = "SummaryDocket.pdf";
        }

        return $files;
    }
}

?>
