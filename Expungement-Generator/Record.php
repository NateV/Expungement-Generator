<?php

/*************************************************
*
*	Record.php
*	The class that describes an entire criminal record.  It is a collection of
* arrests with a number of functions that help do things to/on those arrests.
*
*	Copyright 2011-2018 Community Legal Services
*
* Licensed under the Apache License, Version 2.0 (the "License");
* you may not use this file ehexcept in compliance with the License.
* You may obtain a copy of the License at
*
*    http://www.apache.org/licenses/LICENSE-2.0

* Unless required by applicable law or agreed to in writing, software
* distributed under the License is distributed on an "AS IS" BASIS,
* WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
* See the License for the specific language governing permissions and
* limitations under the License.
*
************************************************************/

require_once("Arrest.php");
require_once("Person.php");
require_once("ArrestSummary.php");

class Record
{
    private $arrests = array();
    private $arrestSummary;
    private $person;
    private $cleanSlateEligible = array();
    private $cleanSlateEligibleCases = array();
    private static $sealingOverviewTemplate = "SealingOverview.docx";

    // public function __construct($arrests)
    // {
    //     $this->arrests=$arrests;
    // }

    public function __construct($person)
    {
        $this->person = $person;//do nothing
        $this->arrestSummary = new ArrestSummary();
    }

    public function getArrests() { return $this->arrests; }
    public function getPerson() { return $this->person; }

    public function addArrest($arrest)
    {
        $this->arrests[$arrest->getFirstDocketNumber()] = $arrest;
    }

    public function getTotalArrests()
    {
        return count($this->arrests);
    }

    // parse the docket sheets into Arrest objects and place them all into the
    // $arrests arrays
    public function parseDockets($tempFile, $pdftotext, $docketFiles)
    {
        // loop over all of the files that we uploaded and read them in to see if they are expungeable
        foreach($docketFiles["userFile"]["tmp_name"] as $key => $file)
        {
            error_log("pdftotext now");

            $command = $pdftotext . " -layout \"" . $file . "\" \"" . $tempFile . "\"";
            //print $command;
            system($command, $ret);

            if ($ret == 0)
            {
                # print $filename . "<br />";
                $thisRecord = file($tempFile);

                $arrest = new Arrest();

                if ($arrest->isDocketSheet($thisRecord[1]) || $arrest->checkIsJuvenilePhilly($thisRecord[0]))
                {
                    // if this is a regular docket sheet, use the regular parsing function
                    error_log("Parsing a docket sheet");
                    $arrest->readArrestRecord($thisRecord, $this->person);

                    // associate the PDF with the arrest for later saving to the DB
                    // associate the real PDF file name with the arrest as well for use in the overview
                    if ($docketFiles["userFile"]["size"][$key] > 0)
                    {
                        $arrest->setPDFFile($file);
                        $arrest->setPDFFileName($docketFiles["userFile"]["name"][$key]);
                    }

                    // now add the arrest to the arrests array
                    if ($arrest->isArrestCriminal()) {
                      error_log("adding criminal arrest");
                      error_log("it has " . sizeof($arrest->getCharges()) . " charges");
                      $this->addArrest($arrest);
                    }

                }
                elseif (ArrestSummary::isArrestSummary($thisRecord))
                {
                    // if this is a summary sheet of all arrests, make a separate array
                    $this->arrestSummary->processArrestSummary($thisRecord);
                }
            } else {
                error_log("PDF to text failed");
                error_log($ret);
        }
        }

        try
        {
            error_log("unlinking " . $tempFile);
            unlink($tempFile);
        }
        catch (Exception $e) {}

    }

    // integrates information from the summaryArrest object and the arrests array.  The summary
    // arrest object most commonly contains additional information about the judge, but could
    // have other useful information like the OTN or DC number.
    // also integrates information into the Person object if a PPID and SID exist
    // Includes an optional $isAPI, default False. The function won't print to the screen if
    // $isAPI is True.
    public function integrateSummaryInformation($isAPI=False)
    {
        if ($this->arrestSummary != null && $this->arrestSummary->hasValuableInformation())
        {
            // integrate arrests together
            foreach ($this->arrests as $arrest)
            {
                // grab the docket number off of the arrest
                $docket = $arrest->getFirstDocketNumber();

                // and combine with the like summary, if one exists
                if ($this->arrestSummary->isArrestInSummary($docket))
                $arrest->combineWithSummary($this->arrestSummary->getArrest($docket));
            }

            // warn the user about any cases that are in the summary, but that were not uploaded
            $summaryKeys = $this->arrestSummary->getArrestKeys();
            $arrestKeys = array_keys($this->arrests);
            $missingDockets = array_diff($summaryKeys, $arrestKeys);

            // this shoudl probably be moved elsewhere since it is printing to screen
            if ( (count($missingDockets) > 0) && ($isAPI==False) )
            {
                print "<b>The following cases appear in the summary docket, but you didn't upload a corresponding docket sheet:</b><br/>";
                foreach ($missingDockets as $missingDocket)
                print "$missingDocket<br/>";
                print "<br/>";

            }
        }

        // integrate the DOB from the arrests into the person
        foreach ($this->arrests as $arrest)
        {
            $DOB = $arrest->getDOB();
            if($DOB != null and $DOB != "")
            {
                $this->person->setDOB($DOB);
                return;
            }
        }

    }

    // takes an array of Arrests and determines which ones are part and parcel of the same case.
    // @return the array of Arrests, pared down.
    public function combineArrests()
    {
        // start by comparing the arrests and combining the ones with matching OTNS or DC numbers
        foreach ($this->arrests as $key=>$arrest)
        {
            $innerArrests = $this->arrests;
            foreach ($innerArrests as $innerKey=>$innerArrest)
            {
                if($arrest->combine($innerArrest))
                {
                    print "combining " . $arrest->getFirstDocketNumber() . " | " . $innerArrest->getFirstDocketNumber() . "<br />";
                    unset($this->arrests[$innerKey]);
                }
            }
        }
        // reindex the arrests array now that some entries have been removed
        $this->arrests = array_values($this->arrests);
    }

    public function parseArrests() {
        // Similar to createOverview, but without the microsoft word
        $results = Array();
        if (sizeof($this->arrests) == 0) {
            $results['expungements_redactions'] = ["none"];
        } else {
            $results['expungements_redactions'] = Array();
            $results['sealing'] = Array();
            foreach($this->arrests as $arrest) {

                $thisArrest = Array();

                $thisArrest['docket'] = htmlspecialchars(implode(", ", $arrest->getDocketNumber()), ENT_COMPAT, 'UTF-8');
                $thisArrest['otn'] = htmlspecialchars($arrest->getOTN(), ENT_COMPAT, 'UTF-8');

                $expType = "No expungement possible";
                if ($arrest->isArrestRedaction()) {
                    $expType = "Partial Expungement";
                }
                if ($arrest->isArrestExpungement()) {
                    $expType = "Expungement";
                }
                if ($arrest->isArrestARDExpungement()) {
                    $expType = "ARD Expungement***";
                }
                if ($arrest->isArrestSummaryExpungement($this->arrests)) {
                    $expType = "Summary Expungement";
                }
                if ($arrest->isArrestOver70Expungement($this->arrests, $this->person)) {
                    $expType = "Expungement (over 70)";
                }
                // Ignoring act 5 sealing for now
                $thisArrest['expungement_type'] = $expType;
                $thisArrest['unpaid_costs'] = htmlspecialchars(number_format($arrest->getCostsTotal() - $arrest->getBailTotal(),2),ENT_COMPAT, 'UTF-8');
                $thisArrest['bail'] = htmlspecialchars(number_format($arrest->getBailTotalTotal(),2), ENT_COMPAT, 'UTF-8');
                $results['expungements_redactions'][] = $thisArrest;
            }//end of processing arrests

        }// end of processing results
        //error_log("Returning response:");
        //error_log("-----------");
        return $results;
    }//end of parseArrests

    /** Right now this just checks to see if a case is Act 56 eligible.  In them
    * future, this might also include other types of eligibility, which might
    * mean that there should be other functions insie: checkCleanSlate, checkAc72, etc...
    * This runs throgh each of the arrests and checks to see if each is eligible
    * for clean slate or otherwize triggers some sort of diqualification from
    * clean slate.
    * @Return void.  It just sets some variables and then those can be checked
    * later.
    *********************************/
    public function checkSealingEligibility()
    {
        $this->checkCleanSlateMurderFelony();
        $this->checkCleanSlatePast10MFConviction();
        $this->checkCleanSlatePast15MoreThanOneM1F();
        $this->checkCleanSlatePast15ProhibitedConviction();
        $this->checkCleanSlatePast20MoreThanThreeM2M1F();
        $this->checkCleanSlatePast20FProhibitedConviction();

        foreach ($this->arrests as $arrest)
        {
            // check this arrest to see if there are fines/costs owed or if there are unsealable
            // charges in the case
            $this->cleanSlateEligible['FinesCosts'][$arrest->getFirstDocketNumber()]['answer'] = $arrest->checkCleanSlateHasOutstandingFinesCosts();
            $this->cleanSlateEligible['UnsealableCharge'][$arrest->getFirstDocketNumber()] = $arrest->checkCleanSlateIsUnsealableOffense();

            // if there are any unsealable charges, set to True so that we can filter this case out
            if (array_filter($this->cleanSlateEligible['UnsealableCharge'][$arrest->getFirstDocketNumber()], array(__CLASS__, 'not_empty')))
                $this->cleanSlateEligible['UnsealableCharge'][$arrest->getFirstDocketNumber()]['answer'] = TRUE;
            else
                $this->cleanSlateEligible['UnsealableCharge'][$arrest->getFirstDocketNumber()] = FALSE;


            // if there are no global exclusions and this case is eligible
            // then set clean slate to true
            // TODO: Captures MD cases.  There is currently no function to
            // filter out MD cases.  They are never sealable b/c they aren't
            // criminal.  BUt the "isCriminal" function on arrests includes md arrests.
            // The solution is probably to just make another function, but that feels silly.
            // Maybe overload the function but with defaults to allow for
            // MDs to be excluded?
           //
            if(!$this->cleanSlateEligible['FinesCosts'][$arrest->getFirstDocketNumber()]['answer'] &&
               !$this->cleanSlateEligible['UnsealableCharge']['answer'] &&
               !$arrest->isArrestExpungement())
            {
               $arrest->setIsCleanSlateEligible(TRUE);
               $this->cleanSlateEligibleCases[] = $arrest->getFirstDocketNumber();
            }

            else
            {
                $arrest->setIsCleanSlateEligible(FALSE);
            }

        }

    }

    // Remove False elements from an array.
    //
    // Args:
    //  $var: Array or null
    //
    // Returns:
    //  If $var is an array, return an array of elements
    //  that don't evaluate to false (probably usually
    //  an array with null elements removed.)
    //  If $var is null itself, return False;
    public static function not_empty($var)
    {
        if (empty($var)) {
          return FALSE;
        }
        return array_filter($var);
    }

    // checks whether there are any murder of F1 convictions on the record
    // under 18 PaCS 9122.1(b2i)
    public function checkCleanSlateMurderFelony()
    {
        // if we have already gone through this before, just exit
        if (isset($this->cleanSlateEligible['MurderF1']['answer']))
            return;

        foreach ($this->arrests as $arrest)
        {
            // check whether there are any murder/felony convictions on each
            // and return true if there are
            $this->cleanSlateEligible['MurderF1'][$arrest->getFirstDocketNumber()] = $arrest->checkCleanSlateMurderFelony();
        }
        // if we have inserted elements into the array, that means that there are
        // potential F1 convictions.  TO check if there are elements in the array
        // see if there are any non-null nodes in the array
        if (array_filter($this->cleanSlateEligible['MurderF1'], array(__CLASS__, 'not_empty')))
        {
            $this->cleanSlateEligible['MurderF1']['answer'] = TRUE;
        }
        else
            $this->cleanSlateEligible['MurderF1']['answer'] = FALSE;
    }

    // checks whether there are any convictions in the last 10 years
    // under 18 PaCS 9122.1(a)
    public function checkCleanSlatePast10MFConviction()
    {
        // if we have already gone through this before, just exit
        if (isset($this->cleanSlateEligible['Past10MFConviction']['answer']))
            return;

        foreach ($this->arrests as $arrest)
        {
            // check whether there are any murder/felony convictions on each
            // and return true if there are
            $this->cleanSlateEligible['Past10MFConviction'][$arrest->getFirstDocketNumber()] = $arrest->checkCleanSlatePast10MFConviction();
        }
        // if we have inserted elements into the array, that means that there are
        // potential F1 convictions.  TO check if there are elements in the array
        // see if there are any non-null nodes in the array
        if (array_filter($this->cleanSlateEligible['Past10MFConviction'], array(__CLASS__, 'not_empty')))
            $this->cleanSlateEligible['Past10MFConviction']['answer'] = TRUE;
        else
            $this->cleanSlateEligible['Past10MFConviction']['answer'] = FALSE;

    }

    // checks whether there are more than 1 M1 or F convictions in the last 15 years
    // under 18 PaCS 9122.1(b2iii)
    public function checkCleanSlatePast15MoreThanOneM1F()
    {
        // if we have already gone through this before, just exit
        if (isset($this->cleanSlateEligible['Past15MoreThanOneM1F']['answer']))
            return;

        foreach ($this->arrests as $arrest)
        {
            // check whether there are any murder/felony convictions on each
            // and return true if there are
            $this->cleanSlateEligible['Past15MoreThanOneM1F'][$arrest->getFirstDocketNumber()] = $arrest->checkCleanSlatePast15MoreThanOneM1F();
        }

        // if we have inserted elements into the array, that means that there are
        // potential M1 and F convictions.  Filter the array for non-null
        // elements and then count the number of these elements. For the
        $M1Fs = array_map('array_filter', $this->cleanSlateEligible['Past15MoreThanOneM1F']);

        // array_map with count will create an array where the keys are the case
        // numbers and the values are the counts of charges within the case
        // numbers.
        // array_sum then sums up all of those case numbers
        // IMPORTANT: if you want to count the total cases represented in the
        // array rather than the total charges, then change the line below to
        // if (count($MF1s) >1).
        // IMPORTANT #2: If you count this same array after true/false
        // has been set, you have to subtract 1 from the count($MF1s) as
        // ['answer'] will also get added into the count
        if (array_sum(array_map("count", $M1Fs)) > 1)
        {
            $this->cleanSlateEligible['Past15MoreThanOneM1F']['answer'] = TRUE;
        }
        else
            $this->cleanSlateEligible['Past15MoreThanOneM1F']['answer'] = FALSE;
    }

    // checks whether there are any prohibited convictions the last 15 years
    // under 18 PaCS 9122.1(b2iii)
    public function checkCleanSlatePast15ProhibitedConviction()
    {
        // if we have already gone through this before, just exit
        if (isset($this->cleanSlateEligible['Past15ProhibitedConviction']['answer']))
            return;

        foreach ($this->arrests as $arrest)
        {
            // check whether there are any murder/felony convictions on each
            // and return true if there are
            $this->cleanSlateEligible['Past15ProhibitedConviction'][$arrest->getFirstDocketNumber()] = $arrest->checkCleanSlatePast15ProhibitedConviction();
        }
        // if we have inserted elements into the array, that means that there are
        // potential F1 convictions.  TO check if there are elements in the array
        // see if there are any non-null nodes in the array
        if (array_filter($this->cleanSlateEligible['Past15ProhibitedConviction'], array(__CLASS__, 'not_empty')))
        {
            $this->cleanSlateEligible['Past15ProhibitedConviction']['answer'] = TRUE;
        }
        else
            $this->cleanSlateEligible['Past15ProhibitedConviction']['answer'] = FALSE;

    }

    // checks whether there are more than 3 M2, M1, or F convictions in the last 20 years
    // under 18 PaCS 9122.1(b2ii)
    public function checkCleanSlatePast20MoreThanThreeM2M1F()
    {
        // if we have already gone through this before, just exit
        if (isset($this->cleanSlateEligible['Past20MoreThanThreeM2M1F']['answer']))
            return;

        foreach ($this->arrests as $arrest)
        {
            // check whether there are any murder/felony convictions on each
            // and return true if there are
            $this->cleanSlateEligible['Past20MoreThanThreeM2M1F'][$arrest->getFirstDocketNumber()] = $arrest->checkCleanSlatePast20MoreThanThreeM2M1F();
        }

        // if we have inserted elements into the array, that means that there are
        // potential M1 and F convictions.  Filter the array for non-null
        // elements and then count the number of these elements. For the
        $M2M1Fs = array_map('array_filter', $this->cleanSlateEligible['Past20MoreThanThreeM2M1F']);

        // array_map with count will create an array where the keys are the case
        // numbers and the values are the counts of charges within the case
        // numbers.
        // array_sum then sums up all of those case numbers
        // IMPORTANT: if you want to count the total cases represented in the
        // array rather than the total charges, then change the line below to
        // if (count($MF1s) >1).
        // IMPORTANT #2: I THINK! If you count this same array after true/false
        // has been set, you have to subtract 1 from the count($MF1s) as
        // ['answer'] will also get added into the count
        if (array_sum(array_map("count", $M2M1Fs)) > 3)
        {
            $this->cleanSlateEligible['Past20MoreThanThreeM2M1F']['answer'] = TRUE;
        }
        else
            $this->cleanSlateEligible['Past20MoreThanThreeM2M1F']['answer'] = FALSE;

    }

    // checks whether there are any prohibited F convictions in the last 20 years
    // under 18 PaCS 9122.1(b2ii)
    public function checkCleanSlatePast20FProhibitedConviction()
    {
        // if we have already gone through this before, just exit
        if (isset($this->cleanSlateEligible['Past20FProhibitedConviction']['answer']))
            return;

        foreach ($this->arrests as $arrest)
        {
            // check whether there are any murder/felony convictions on each
            // and return true if there are
            $this->cleanSlateEligible['Past20FProhibitedConviction'][$arrest->getFirstDocketNumber()] = $arrest->checkCleanSlatePast20FProhibitedConviction();
        }
        // if we have inserted elements into the array, that means that there are
        // potential F1 convictions.  TO check if there are elements in the array
        // see if there are any non-null nodes in the array
        if (array_filter($this->cleanSlateEligible['Past20FProhibitedConviction'], array(__CLASS__, 'not_empty')))
        {
            $this->cleanSlateEligible['Past20FProhibitedConviction']['answer'] = TRUE;
        }
        else
            $this->cleanSlateEligible['Past20FProhibitedConviction']['answer'] = FALSE;

    }

    // create the overview file
    public function generateCleanSlateOverview($templateDir, $dataDir)
    {
        $docx = new \PhpOffice\PhpWord\TemplateProcessor($templateDir . Record::$sealingOverviewTemplate);

        // set name and date variables
    	$docx->setValue("NAME", htmlspecialchars($this->person->getFirst() . " " . $this->person->getLast(), ENT_COMPAT, 'UTF-8'));
        $docx->setValue("DATE", date("m/d/Y"));

        // check to see if there are any record-wide restrictions on sealing
        // and set that variable in the record
        if (!$this->cleanSlateEligible['MurderF1']['answer'] &&
           !$this->cleanSlateEligible['Past10MFConviction']['answer'] &&
           !$this->cleanSlateEligible['Past15MoreThanOneM1F']['answer'] &&
           !$this->cleanSlateEligible['Past15ProhibitedConviction']['answer'] &&
           !$this->cleanSlateEligible['Past20MoreThanThreeM2M1F']['answer'] &&
           !$this->cleanSlateEligible['Past20FProhibitedConviction']['answer'])
        {
            $docx->setValue("GLOBAL_SEALING_ELIGIBLE", "IS");
            $docx->setValue("INELIGIBLE_REASONS", "");
        }

        else
        {
            // if we aren't eligible for sealing, state why and add the reason
            // to a strong that we will set later.
            $docx->setValue("GLOBAL_SEALING_ELIGIBLE", "IS POSSIBLY NOT");
            $reasons = "";
            if ($this->cleanSlateEligible['MurderF1']['answer'])
                $reasons .= "There is a murder or potential felony F1 conviction on the record.\r\n";
            if ($this->cleanSlateEligible['Past10MFConviction']['answer'])
                $reasons .= "There is an M or F conviction from the last 10 years on the record.\r\n";
            if ($this->cleanSlateEligible['Past15MoreThanOneM1F']['answer'])
                $reasons .= "There is potentially more than one M1 or F conviction on the record.\r\n";
            if ($this->cleanSlateEligible['Past15ProhibitedConviction']['answer'])
                $reasons .= "There is a prohibited conviction from the past 15 years (e.g. indecent assault, etc..).\r\n";
            if ($this->cleanSlateEligible['Past20MoreThanThreeM2M1F']['answer'])
                $reasons .= "There are potentially more than three M2, M1, or F convictions on the record.\r\n";
            if ($this->cleanSlateEligible['Past20FProhibitedConviction']['answer'])
                $reasons .= "There is potentially an F for a prohibited 20 year conviction (e.g. Article B, D, chapter 61, registration) on the record.";

            $docx->setValue("INELIGIBLE_REASONS", $reasons);
        }

        // Now run through each piece of the eligibility array and print out
        // the relevant sections; there may be information here that is helpful
        // even if a case is eligible for sealing.
        $this->setCleanSlateDisqualifiers($docx, 'MurderF1', 'MURDER_CASE_CHARGE', 'MURDER_REASON');
        $this->setCleanSlateDisqualifiers($docx, 'Past10MFConviction', 'PAST_10_CONV_CASE_CHARGE', 'PAST_10_CONVICTION_REASON');
        $this->setCleanSlateDisqualifiers($docx, 'Past15MoreThanOneM1F', 'PAST_15_M1F_CASE_CHARGE', 'PAST_15_M1F_REASON');
        $this->setCleanSlateDisqualifiers($docx, 'Past15ProhibitedConviction', 'PAST_15_PROHIB_CASE_CHARGE', 'PAST_15_PROHIB_REASON');
        $this->setCleanSlateDisqualifiers($docx, 'Past20MoreThanThreeM2M1F', 'PAST_20_M2M1F_CASE_CHARGE', 'PAST_20_M2M1F_REASON');
        $this->setCleanSlateDisqualifiers($docx, 'Past20FProhibitedConviction', 'PAST_20_F_PROHIB_CASE_CHARGE', 'PAST_20_F_PROHIB_REASON');

        // clone the case row for each case in the record that isn't otherwise
        // an expungement
        $i=0;
        foreach ($this->arrests as $arrest)
        {
            if(!$arrest->isArrestExpungement() && !$arrest->isArrestARDExpungement() &&
                !$arrest->isArrestSummaryExpungement($this->arrests) &&
                !$arrest->isArrestOver70Expungement($this->arrests, $this->person))
                $i = $i+1;
        }

        $docx->cloneRow("CASE", $i);

        // fill in each row of the document
        $i=1;
        foreach($this->arrests as $arrest)
        {
            // we don't care about this arrest if it is otherwise expungeable
            if($arrest->isArrestExpungement() || $arrest->isArrestARDExpungement() ||
                $arrest->isArrestSummaryExpungement($this->arrests) ||
                $arrest->isArrestOver70Expungement($this->arrests, $this->person))
                continue;

            # set the case number and F&C amount
            $docx->setValue("CASE#".$i,$arrest->getFirstDocketNumber());
            $docx->setValue("FC#".$i, "$" . $arrest->getCostsTotal());

            $sCharges = array();
            $sReasons = array();
            // run through each charge and add a message to an array
            // for each charge
            foreach ($arrest->getCharges() as $charge)
            {
                // only print charges that are aren't otherwsise expungeable
                if (!$charge->isRedactable() && $charge->isConviction())
                {
                    $sCharges[] = $charge->getCodeSection();
                    $sReasons[] = $charge->getSealableMessage();
                }
            }

            // and then write each charge and sealable reason to the document
            $docx->setValue("CONV#".$i, implode("\r\n", $sCharges));
            $docx->setValue("SEALABLE#".$i, implode("\r\n", $sReasons));

            $i=$i+1;
        }

        // now write the overview to a file and return the file location
    	$outputFile = $dataDir . $this->person->getFirst() . $this->person->getLast() . Record::$sealingOverviewTemplate;
    	$docx->saveAs($outputFile);

    	return $outputFile;

    }

    // given one of the subarrays of the checkCleanSlate variable,
    // returns an array where one element is an array of all of the
    // case numbers and charges; the other element is an array of
    // all of the reasons that a case falls into that subarray
    private function getCaseChargeAndReason($a)
    {
        // if this subarray has no answers in it, exit
        if (empty($a))
            return null;

        $case_charge = array();
        $reason = array();
        // iterate over all of the cases
        foreach($a as $case=>$infos)
        {
            // and each charge within the case
            if (gettype($infos) != "array") {
              continue;
            }

            foreach($infos as $info)
            {
                // and add to the proper arrays the case num, charge, and description
                if (!empty($info[0]))
                {
                    // info is usually two elements: charge and reason
                    // but for 10-year convictions, it is just a reason
                    if (gettype($info)==="array")
                    {
                        $case_charge[] = $case . " | " . $info[0];
                        $reason[] = $info[1];
                    }
                    else
                    {
                        $case_charge[] = $case;
                        $reason[] = $info;
                    }
                }
            }
        }

        return array($case_charge, $reason);

    }

    // a private internal funciton used to update a part of the sealing overview
    // document.
    private function setCleanSlateDisqualifiers($docx, $arrayElement, $case_charge, $reason)
    {
        $reasons = $this->getCaseChargeAndReason($this->cleanSlateEligible[$arrayElement]);

        // only set reasons if there a) are any and b) there is a sealing disuqualification from this offense
        if (!empty($reasons) && !empty($reasons[0]) && $this->cleanSlateEligible[$arrayElement]['answer'])
        {
            $docx->setValue($case_charge, implode("\r\n",$reasons[0]));
            $docx->setValue($reason, implode("\r\n",$reasons[1]));
        }
        else
        {
            $docx->setValue($case_charge, "");
            $docx->setValue($reason, "");
        }
    }
}
