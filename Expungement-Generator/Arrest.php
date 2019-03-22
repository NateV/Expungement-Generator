<?php
	// @@@@@@@@ TODO - Add extra columns to the arrest column to see if expungement, summary, ard, etc...
	// @todo make aliases work automatically - they should be able to be read off of the case summary
	// @todo think about whether I want to have the charges array check to see if a duplicate
	//		 charge is being added and prevent duplicate charges.  A good example of this is if
	//       a charge is "replaced by information" and then later there is a disposition.
	// 		 we probably don't want both the replaced by information and the final disposition on
	// 		 the petition.  This is especially true if the finally dispoition is Guilty

/********************************************
*
*	Arrest.php
*	This is the big momma class.  It handles everything from parsing a criminal arrest
* 	to checking if an arrest should be a redaction or expungement
*	to checking to see if multiple arrests shoudl be combined.
*	It also handles writing an individual arrest to the database and to word files.
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
*
********************************************/

require_once("Charge.php");
require_once("Person.php");
require_once("Attorney.php");
require_once("utils.php");
require_once("config.php");
//require_once __DIR__ . '/vendor/phpoffice/phpword/src/PhpWord/Autoloader.php';
//\PhpOffice\PhpWord\Autoloader::register();
require "vendor/autoload.php";


class Arrest
{

	private $mdjDistrictNumber;
	private $county;
	private $OTN;
	private $DC;
    private $jNumber;
    private $jDischargeDate;
    private $SID;
    private $PID;
	private $docketNumber = array();
	private $arrestingOfficer;
	private $arrestingAgency;
	private $arrestDate;
	private $complaintDate;
	private $judge;
	private $DOB;
	private $dispositionDate;
	private $firstName;
	private $lastName;
	private $charges = array();
	private $costsTotal;
	private $costsPaid;
	private $costsCharged;
	private $costsAdjusted;
	private $bailTotal;
	private $bailCharged;
	private $bailPaid;
	private $bailAdjusted;
	private $bailTotalTotal;
	private $bailChargedTotal;
	private $bailPaidTotal;
	private $bailAdjustedTotal;
	private $isCP;
	private $isCriminal;
	private $isARDExpungement;
	private $isExpungement;
	private $isRedaction;
	private $isSealable;
	private $isHeldForCourt;
	private $isOnlyHeldForCourt;
	private $isSummaryArrest = FALSE;
	private $isArrestSummaryExpungement;
	private $isArrestOver70Expungement;
    private $isArrestConviction;
	private $pdfFile;
	private $pdfFileName;
	private $aliases = array();
	private $pastAliases = FALSE; // used to stop checking for aliases once we have reached a certain point in the docket sheet

	// isMDJ = 0 if this is not an mdj case at all, 1 if this is an mdj case and 2 if this is a CP case that decended from MDJ
	private $isMDJ = 0;
    private $isJuvenilePhilly = false;
	private $cleanSlateEligible = array();
	private $isCleanSlateEligible;

	public static $expungementTemplate = "790ExpungementTemplate.docx";
	public static $act5Template = "791SealingTemplate.docx";
	public static $juvenileExpungementTemplate = "JuvenileExpungementTemplate.docx";
	public static $IFPTemplate = "IFPTemplate.docx";
    public static $COSTemplate = "MontcoCertificateofServiceTemplate.docx";

	public static $overviewTemplate = "overviewTemplate.docx";

	protected static $unknownInfo = "N/A";
	protected static $unknownOfficer = "Unknown officer";
	protected static $unknownAgency = "Unknown agency";


	protected static $mdjDistrictNumberSearch = "/Magisterial District Judge\s(.*)/i";
	protected static $countySearch = "/\sof\s(\w+)\sCOUNTY/i";
	protected static $mdjCountyAndDispositionDateSearch = "/County:\s+(.*)\s+Disposition Date:\s+(.*)/";
	protected static $OTNSearch = "/OTN:\s+(\D(\s)?\d+(\-\d)?)/";
	protected static $DCSearch = "/District Control Number\s+(\d+)/";
	protected static $juvenileNumberSearch = "/Juvenile ID\s+(\d+)/";
	protected static $docketSearch = "/Docket Number:\s+((MC|CP)\-\d{2}\-(\D{2})\-\d*\-\d{4})/";
	protected static $mdjDocketSearch = "/Docket Number:\s+(MJ\-\d{5}\-(\D{2})\-\d*\-\d{4})/";
	protected static $arrestingAgencyAndOfficerSearch = "/Arresting Agency:\s+(.*)\s+Arresting Officer: (\D+)/";
	protected static $mdjArrestingOfficerSearch = "/^\s*Arresting Officer (\D+)\s*$/";
	protected static $mdjArrestingAgencyAndArrestDateSearch = "/Arresting Agency:\s+(.*)\s+Arrest Date:\s+(\d{1,2}\/\d{1,2}\/\d{4})?/";
	protected static $arrestDateSearch = "/Arrest Date:\s+(\d{1,2}\/\d{1,2}\/\d{4})/";
	protected static $complaintDateSearch = "/Complaint Date:\s+(\d{1,2}\/\d{1,2}\/\d{4})/";
	protected static $mdjComplaintDateSearch = "/Issue Date:\s+(\d{1,2}\/\d{1,2}\/\d{4})/";
	protected static $judgeSearch = "/Final Issuing Authority:\s+(.*)/";
	protected static $judgeAssignedSearch = "/Judge Assigned:\s+(.*)\s+(Date Filed|Issue Date):/";

	#note that the alias name search only captures a maximum of six aliases.
	# This is because if you do this: /^Alias Name\r?\n(?:(^.+)\r?\n)*/m, only the last alias will be stored in $1.
	# What a crock!  I can't figure out a way around this
	protected static $aliasNameStartSearch = "/^Alias Name/"; // \r?\n(?:(^.+)\r?\n)(?:(^.+)\r?\n)?(?:(^.+)\r?\n)?(?:(^.+)\r?\n)?(?:(^.+)\r?\n)?(?:(^.+)\r?\n)?/m";
	protected static $aliasNameEndSearch = "/CASE PARTICIPANTS/";
	protected static $endOfPageSearch = "/(CPCMS|AOPC)\s\d{4}/";

	// there are two special judge situations that need to be covered.  The first is that MDJ dockets sometimes say
	// "magisterial district judge xxx".  In that case, there could be overflow to the next line.  We want to capture that
	// overflow.  The second is that sometimes the judge assigned says "migrated judge".  We want to make sure we catch that.
	protected static $magisterialDistrictJudgeSearch = "/Magisterial District Judge (.*)/";
	protected static $judgeSearchOverflow = "/^\s+(\w+\s*\w*)\s*$/";
	protected static $migratedJudgeSearch = "/migrated/i";
	protected static $DOBSearch = "/Date Of Birth:?\s+(\d{1,2}\/\d{1,2}\/\d{4})/i";
	protected static $SIDSearch = "/SID:?\s+(\d{1,3}-\d{1,3}-\d{1,3}-\d{1,3})/i";
	protected static $PIDSearch = "/PID\s+(\d+)/i";
	protected static $nameSearch = "/^Defendant\s+(.*), (.*)/";
    protected static $juvenileNameSearch = "/In the interest of:\s+(.*), a Minor/i";

	// ($1 = charge, $2 = disposition, $3 = grade, $4 = code section
	protected static $chargesSearch = "/\d\s+\/\s+(.*[^Not])\s+(Not Guilty|Guilty|Nolle Prossed|Nolle Prossed \(Case Dismissed\)|Nolle Prosequi - Administrative|Guilty Plea|Guilty Plea - Negotiated|Guilty Plea - Non-Negotiated|Withdrawn|Withdrawn - Administrative|Charge Changed|Held for Court|Community Court Program|Dismissed - Rule 1013 \(Speedy|Dismissed - Rule 600 \(Speedy|Dismissed - LOP|Dismissed - LOE|Dismissed - Rule 546|Dismissed - Rule 586|Dismissed|Demurrer Sustained|ARD - County Open|ARD - County|ARD|Transferred to Another Jurisdiction|Transferred to Juvenile Division|Quashed|Summary Diversion Completed|Judgment of Acquittal \(Prior to)\s+(\w{0,2})\s+(\w{1,2}\s?\x{A7}\s?\d+(\-|\x{A7}|\w+)*)/u"; // removed "Replacement by Information"
	// explanation: .+? - the "?" means to do a lazy match of .+, so it isn't greedy.  THe match of 12+ spaces handles the large space after the charges and after the disposition before the next line.  The final part is to match the code section that is violated.
	/* longer explanation
		\d\s+\/\s+ - matches "1 / "

		(.+)\s{12,} - matches the charge name followed by a very large space (up to the disposition) and captures the charge name

		(\w.+?)(?=\s\s) - matches the disposition.  A word character followed by ANY string of characters (non-greedy), that is ultimately followed by two spaces.  In other words, we capture "Nolle prossed   " and "Withdrawn" both.

		\s{12,} - big space

		\w{0,2})\s+ - If there is a grading, it will be caught here.  If there isn't, this area will be ignored

		(\w{1,2}\s?\x{A7}\s? - 1-2 word characters followed by the section symbol, potentially with spaces around the section symbol.  This is to capture the beginning of a statute - "18 s "

		\d+ - a number or series of numbers

		(\-|\x{A7}|\w+)*) - followed by either a "-" a section symbol, or a series of word characters, all 0+ times.  This is the clean up at the end of the code section, for example, a code section may be listed as 18 § 2701 §§ A.  This would capture the final ss and the A
	*/
	protected static $chargesSearch2 = "/\d\s+\/\s+(.+)\s{12,}(\w.+?)(?=\s\s)\s{12,}(\w{0,2})\s+(\w{1,2}\s?\x{A7}\s?\d+(\-|\x{A7}|\w+)*)/u";
	protected static $ignoreDisps = array("Proceed to Court", "Proceed to Court (Complaint", "Proceed to Court (Complaint Refiled)", "Proceed to Court (SDP)");

	// $1 = code section, $3 = grade, $4 = charge, $5 = offense date, $6 = disposition
	protected static $mdjChargesSearch = "/^\s*\d\s+((\w|\d|\s(?!\s)|\-|\x{A7}|\*)+)\s{2,}(\w{0,2})\s{2,}([\d|\D]+)\s{2,}(\d{1,2}\/\d{1,2}\/\d{4})\s{2,}(\D{2,})/u";

	protected static $chargesSearchOverflow = "/^\s+(\w+\s*\w*)\s*$/";
	// disposition date can appear in two different ways (that I have found) and a third for MDJ cases:
	// 1) it can appear on its own line, on a line that looks like:
	//    Status   mm/dd/yyyy    Final  Disposition
	//    Trial   mm/dd/yyyy    Final  Disposition
	//    Preliminary Hearing   mm/dd/yyyy    Final  Disposition
	//    Migrated Dispositional Event   mm/dd/yyyy    Final  Disposition
	// 2) on the line after the charge disp
	// 3) for MDJ cases, disposition date appears on a line by itself, so it is easier to find
	protected static $dispDateSearch = "/(?:Plea|Status|Status of Restitution|Status - Community Court|Status Listing|Migrated Dispositional Event|Trial|Preliminary Hearing|Pre-Trial Conference)\s+(\d{1,2}\/\d{1,2}\/\d{4})\s+Final Disposition/";
	protected static $dispDateSearch2 = "/(.*)\s(\d{1,2}\/\d{1,2}\/\d{4})/";

	protected static $dischargeDateSearch = "/^\d?\s*(\d{1,2}\/\d{1,2}\/\d{4})$/";

	// this is a crazy one.  Basically matching whitespace then $xx.xx then whitespace then
	// -$xx.xx, etc...  The fields show up as Assesment, Payment, Adjustments, Non-Monetary, Total
	protected static $costsSearch = "/Totals:\s+\\$([\d\,]+\.\d{2})\s+-?\\$([\d\,]+\.\d{2})\s+-?\\$([\d\,]+\.\d{2})\s+-?\\$([\d\,]+\.\d{2})\s+-?\\$([\d\,]+\.\d{2})/";
	protected static $bailSearch = "/Bail.+\\$([\d\,]+\.\d{2})\s+-?\\$([\d\,]+\.\d{2})\s+-?\\$([\d\,]+\.\d{2})\s+-?\\$([\d\,]+\.\d{2})\s+-?\\$([\d\,]+\.\d{2})/";
	public function __construct () {}


	//getters
	public function getMDJDistrictNumber() { return $this->mdjDistrictNumber; }
	public function getCounty() { if (!isset($this->county)) $this->setCounty(self::$unknownInfo); return $this->county; }
	public function getOTN() { if (!isset($this->OTN)) $this->setOTN(self::$unknownInfo); return $this->OTN; }
	public function getDC() { if (!isset($this->DC)) $this->setDC(self::$unknownInfo); return $this->DC; }
	public function getJNumber() { if (!isset($this->jNumber)) $this->setJNumber(self::$unknownInfo); return $this->jNumber; }
	public function getJDischargeDate() { return $this->jDischargeDate; }
	public function getSID() { if (!isset($this->SID)) $this->setSID(self::$unknownInfo); return $this->SID; }
	public function getPID() { if (!isset($this->PID)) $this->setPID(self::$unknownInfo); return $this->PID; }
	public function getDocketNumber() { return $this->docketNumber; }
	public function getArrestingOfficer() { if (!isset($this->arrestingOfficer)) $this->setArrestingOfficer(self::$unknownOfficer); return $this->arrestingOfficer; }
	public function getArrestingAgency() { if (!isset($this->arrestingAgency)) $this->setArrestingAgency(self::$unknownAgency); return $this->arrestingAgency; }
	public function getArrestDate() { return $this->arrestDate; }
	public function getComplaintDate() { return $this->complaintDate; }
	//  getDispositionDate() exists elsewhere
	public function getJudge() { return $this->judge; }
	public function getDOB() { return $this->DOB; }
	public function getfirstName() { return $this->firstName; }
	public function getLastName() { return $this->lastName; }
	public function getCharges() { return $this->charges; }
	public function getCostsTotal() { if (!isset($this->costsTotal)) $this->setCostsTotal("0"); return $this->costsTotal; }
	public function getCostsPaid()  { if (!isset($this->costsPaid)) $this->setCostsPaid("0");return $this->costsPaid; }
	public function getCostsCharged() { if (!isset($this->costsCharged)) $this->setCostsCharged("0"); return $this->costsCharged; }
	public function getCostsAdjusted()  { if (!isset($this->costsAdjusted)) $this->setCostsAdjusted("0");return $this->costsAdjusted; }
	public function getBailTotal() { if (!isset($this->bailTotal)) $this->setBailTotal("0"); return $this->bailTotal; }
	public function getBailPaid()  { if (!isset($this->bailPaid)) $this->setBailPaid("0");return $this->bailPaid; }
	public function getBailCharged() { if (!isset($this->bailCharged)) $this->setBailCharged("0"); return $this->bailCharged; }
	public function getBailAdjusted()  { if (!isset($this->bailAdjusted)) $this->setBailAdjusted("0");return $this->bailAdjusted; }
	public function getBailTotalTotal() { if (!isset($this->bailTotalTotal)) $this->setBailTotalTotal("0"); return $this->bailTotalTotal; }
	public function getBailPaidTotal()  { if (!isset($this->bailPaidTotal)) $this->setBailPaidTotal("0");return $this->bailPaidTotal; }
	public function getBailChargedTotal() { if (!isset($this->bailChargedTotal)) $this->setBailChargedTotal("0"); return $this->bailChargedTotal; }
	public function getBailAdjustedTotal()  { if (!isset($this->bailAdjustedTotal)) $this->setBailAdjustedTotal("0");return $this->bailAdjustedTotal; }
	public function getIsCP()  { return $this->isCP; }
	public function getIsCriminal()  { return $this->isCriminal; }
	public function getIsARDExpungement()  { return $this->isARDExpungement; }
	public function getIsExpungement()  { return $this->isExpungement; }
	public function getIsRedaction()  { return $this->isRedaction; }
	public function getIsHeldForCourt()  { return $this->isHeldForCourt; }
	public function getIsOnlyHeldForCourt()  { return $this->isOnlyHeldForCourt; }
	public function getIsSummaryArrest()  { return $this->isSummaryArrest; }
	public function getIsMDJ() { return $this->isMDJ; }
	public function getIsJuvenilePhilly() { return $this->isJuvenilePhilly; }
	public function getPDFFile() { return $this->pdfFile;}
	public function getPDFFileName() { return $this->pdfFileName;}
	public function getAliases() { return $this->aliases; }
	public function getIsCleanSlateEligible() { return $this->isCleanSlateEligible; }

	//setters
	public function setMDJDistrictNumber($mdjDistrictNumber) { $this->mdjDistrictNumber = $mdjDistrictNumber; }
	public function setCounty($county) { $this->county = ucwords(strtolower($county)); }
	public function setOTN($OTN)
	{
		// OTN could have a "-" before the last digit.  It could also have unnecessary spaces.
		// We want to chop that off since it isn't important and messes up matching of OTNs
		$this->OTN = str_replace(" ", "", str_replace("-", "", $OTN));
	}
	public function setDC($DC) { $this->DC = $DC; }
	public function setJNumber($jNumber) { $this->jNumber = $jNumber; }
	public function setJDischargeDate($jDischargeDate) { $this->jDischargeDate = $jDischargeDate; }
	public function setSID($SID) { $this->SID = $SID; }
	public function setPID($PID) { $this->PID = $PID; }
	public function setDocketNumber($docketNumber) { $this->docketNumber = $docketNumber; }
	public function setIsSummaryArrest($isSummaryArrest)  { $this->isSummaryArrest = $isSummaryArrest; }
	public function setArrestingOfficer($arrestingOfficer) {  $this->arrestingOfficer = ucwords(strtolower($arrestingOfficer)); }

	// when we set the arresting agency, replace any string "PD" with "Police Dept"
	public function setArrestingAgency($arrestingAgency) {  $this->arrestingAgency = preg_replace("/\bpd\b/i", "Police Dept",$arrestingAgency); }

	public function setArrestDate($arrestDate) {  $this->arrestDate = $arrestDate; }
	public function setComplaintDate($complaintDate) {  $this->complaintDate = $complaintDate; }
	public function setJudge($judge) { $this->judge = $judge; }
	public function setDispositionDate($dispositionDate) { $this->dispositionDate = $dispositionDate; }
	public function setDOB($DOB) { $this->DOB = $DOB; }
	public function setFirstName($firstName) { $this->firstName = $firstName; }
	public function setLastName($lastName) { $this->lastName = $lastName; }
	public function setCharges($charges) {  $this->charges = $charges; }
	public function setCostsTotal($costsTotal) {  $this->costsTotal = $costsTotal; }
	public function setCostsPaid($costsPaid)  {  $this->costsPaid = $costsPaid; }
	public function setCostsCharged($costsCharged) {  $this->costsCharged = $costsCharged; }
	public function setCostsAdjusted($costsAdjusted)  {  $this->costsAdjusted = $costsAdjusted; }
	public function setBailTotal($bailTotal) {  $this->bailTotal = $bailTotal; }
	public function setBailPaid($bailPaid)  {  $this->bailPaid = $bailPaid; }
	public function setBailCharged($bailCharged) {  $this->bailCharged = $bailCharged; }
	public function setBailAdjusted($bailAdjusted)  {  $this->bailAdjusted = $bailAdjusted; }
	public function setBailTotalTotal($bailTotal) {  $this->bailTotalTotal = $bailTotal; }
	public function setBailPaidTotal($bailPaid)  {  $this->bailPaidTotal = $bailPaid; }
	public function setBailChargedTotal($bailCharged) {  $this->bailChargedTotal = $bailCharged; }
	public function setBailAdjustedTotal($bailAdjusted)  {  $this->bailAdjustedTotal = $bailAdjusted; }
	public function setIsCP($isCP)  {  $this->isCP = $isCP; }
	public function setIsCriminal($isCriminal)  {  $this->isCriminal = $isCriminal; }
	public function setIsARDExpungement($isARDExpungement)  {  $this->isARDExpungement = $isARDExpungement; }
	public function setIsExpungement($isExpungement)  {  $this->isExpungement = $isExpungement; }
	public function setIsRedaction($isRedaction)  {  $this->isRedaction = $isRedaction; }
	public function setIsArrestSummaryExpungement($isSummaryExpungement) { $this->isArrestSummaryExpungement = $isSummaryExpungement; }
	public function setIsArrestOver70Expungement($isOver70Expungement) { $this->isArrestOver70Expungement = $isOver70Expungement; }
	public function setIsHeldForCourt($isHeldForCourt)  {  $this->isHeldForCourt = $isHeldForCourt; }
	public function setIsOnlyHeldForCourt($isOnlyHeldForCourt)  {  $this->isOnlyHeldForCourt = $isOnlyHeldForCourt; }
	public function setIsMDJ($isMDJ)  {  $this->isMDJ = $isMDJ; }
	public function setIsJuvenilePhilly($isJuvenile)  {  $this->isJuvenilePhilly = $isJuvenile; }
	public function setPDFFile($pdfFile) { $this->pdfFile = $pdfFile; }
	public function setPDFFileName($pdfFileName) { $this->pdfFileName = $pdfFileName; }
	public function addAlias($a) { $this->aliases[] = $a; }
	public function setIsCleanSlateEligible($a) { $this->isCleanSlateEligible = $a; }

	// add a Bail amount to an already created bail figure
	public function addBailTotal($bailTotal)
	{
		$this->bailTotal = $this->getBailTotal() + $bailTotal;
		$this->bailTotalTotal = $this->getBailTotalTotal() + $bailTotal;
	}
	public function addBailPaid($bailPaid)
	{
		$this->bailPaid = $this->getBailPaid() + $bailPaid;
		$this->bailPaidTotal = $this->getBailPaidTotal() + $bailPaid;
	}
	public function addBailCharged($bailCharged)
	{
		$this->bailCharged = $this->getBailCharged() + $bailCharged;
		$this->bailChargedTotal = $this->getBailChargedTotal() + $bailCharged;
	}
	public function addBailAdjusted($bailAdjusted)
	{
		$this->bailAdjusted = $this->getBailAdjusted() + $bailAdjusted;
		$this->bailAdjustedTotal = $this->getBailAdjustedTotal() + $bailAdjusted;
	}

	// push a single chage onto the charge array
	public function addCharge($charge) {  $this->charges[] = $charge; }

	// @return the first docket number on the array, which should be the CP or lead docket num
	public function getFirstDocketNumber()
	{

		if (count($this->getDocketNumber()) > 0)
		{
			$docketNumber = $this->getDocketNumber();
			return $docketNumber[0];
		}
		else
			return NULL;
	}

	// @return true if the arrestRecordFile is a docket sheet, false if it isn't
	public function isDocketSheet($arrestRecordFile)
	{
		if (preg_match("/Docket/i", $arrestRecordFile))
			return true;
		else
			return false;
	}

	// @return true if the arrestRecordFile is a docket sheet, false if it isn't
	public function isMDJDocketSheet($arrestRecordFile)
	{
		if (preg_match("/Magisterial District Judge/i", $arrestRecordFile))
			return true;
		else
			return false;
	}

    // sets isJuvenilePhilly = true if the arrest RecordFile is a Philly Juvenile Case
    public function checkIsJuvenilePhilly($line)
    {
        if (preg_match("/Family Court of Philadelphia/i", $line))
        {
            $this->setIsJuvenilePhilly(TRUE);
            return TRUE;
        }
        else
          return FALSE;
    }


	// reads in a record and sets all of the relevant variable.
	// assumes that the record is an array of lines, read through the "file" function.
	// the file should be created by running pdftotext.exe on a pdf of the defendant's arrest.
	// this does not read the summary.
	public function readArrestRecord($arrestRecordFile, $person)
	{
		// check to see if this is an MDJ docket sheet.  If it is, we have to
		// read it a bit differently in places
		if ($this->isMDJDocketSheet($arrestRecordFile[0]))
		{
			$this->setIsMDJ(1);
			if (preg_match(self::$mdjDistrictNumberSearch, $arrestRecordFile[0], $matches))
				$this->setMDJDistrictNumber(trim($matches[1]));

		}

		foreach ($arrestRecordFile as $line_num => $line)
		{
			// print "$line_num: $line<br/>";

			// do all of the searches that are common to the MDJ and CP/MC docket sheets

			// figure out which county we are in
			if (preg_match(self::$countySearch, $line, $matches))
				$this->setCounty(trim($matches[1]));
			elseif (preg_match(self::$mdjCountyAndDispositionDateSearch, $line, $matches))
			{
				$this->setCounty(trim($matches[1]));
				$this->setDispositionDate(trim(($matches[2])));
			}

			// find the docket Number
			else if (preg_match(self::$docketSearch, $line, $matches))
			{
				$this->setDocketNumber(array(trim($matches[1])));

				// we want to set this to be a summary offense if there is an "SU" in the
				// docket number.  The normal docket number looks like this:
				// CP-##-CR-########-YYYY or CP-##-SU-#######-YYYYYY; the latter is a summary
				// so is CP-##-SU-#######-YYYYYY, a summary appeal of a traffic offense
				if (preg_match("(SU|SA)", trim($matches[3]), $dummy))
					$this->setIsSummaryArrest(TRUE);

				else
					$this->setIsSummaryArrest(FALSE);
			}
			else if (preg_match(self::$mdjDocketSearch, $line, $matches))
			{
				$this->setDocketNumber(array(trim($matches[1])));
			}

			else if (preg_match(self::$OTNSearch, $line, $matches))
				$this->setOTN(trim($matches[1]));

			else if (preg_match(self::$DCSearch, $line, $matches))
				$this->setDC(trim($matches[1]));

			// find the arrest date.  First check for agency and arrest date (mdj dockets).  Then check for arrest date alone
			else if (preg_match(self::$mdjArrestingAgencyAndArrestDateSearch, $line, $matches))
			{
				$this->setArrestingAgency(trim($matches[1]));
				if (isset($matches[2]))
					$this->setArrestDate(trim($matches[2]));
			}

			else if (preg_match(self::$arrestDateSearch, $line, $matches))
				$this->setArrestDate(trim($matches[1]));

			// find the complaint date
			else if (preg_match(self::$complaintDateSearch, $line, $matches))
				$this->setComplaintDate(trim($matches[1]));

			// for non-mdj, aresting agency and officer are on the same line, so we have to find
			// them together and deal with them together.
			else if (preg_match(self::$arrestingAgencyAndOfficerSearch, $line, $matches))
			{
				// first set the arresting agency
				$this->setArrestingAgency(trim($matches[1]));

				// then deal with the arresting officer
				$ao = trim($matches[2]);

				// if there is no listed affiant or the affiant is "Affiant" then set arresting
				// officer to "Unknown Officer"
				// CHANGED 9/5/2013 to be the arresting agency if no known officer
				if ($ao == "" || !(stripos("Affiant", $ao)===FALSE))
					// $ao = self::$unknownOfficer;
					$ao = $this->getArrestingAgency();

				$this->setArrestingOfficer($ao);
			}

			// mdj dockets have the arresting office on a line by himself, as last name, first
			else if (preg_match(self::$mdjArrestingOfficerSearch, $line, $matches))
			{
				$officer = trim($matches[1]);
				// find the comma and switch the order of the names
				$officerArray = explode(",", $officer, 2);
				if (sizeof($officerArray) > 0)
					$officer = trim($officerArray[1]) . " " . trim($officerArray[0]);

				$this->setArrestingOfficer($officer);
			}


			// the judge name can appear in multiple places.  Start by checking to see if the
			// judge's name appears in the Judge Assigned field.  If it does, then set it.
			// Later on, we'll check in the "Final Issuing Authority" field.  If it appears there
			// and doesn't show up as "migrated," we'll reassign the judge name.
			else if (preg_match(self::$judgeAssignedSearch, $line, $matches))
			{
				$judge = trim($matches[1]);

				// check to see if this line has "magisterial district judge" in it.  If it does,
				// lop off that phrase and then check the next line to see if anything important is on it
				if (preg_match(self::$magisterialDistrictJudgeSearch, $judge, $judgeMatch))
				{
					// first catch the judge
					$judge = trim($judgeMatch[1]);

					// then check the next line to see if there is anything of interest
					$i = $line_num+1;
					if (preg_match(self::$judgeSearchOverflow, $arrestRecordFile[$i], $judgeOverflowMatch))
						$judge .= " " . trim($judgeOverflowMatch[1]);
				}

				if (!preg_match(self::$migratedJudgeSearch, $judge, $junk))
					$this->setJudge($judge);

				// if this is an mdj docket, the complaint date will also be on this same line, so we want to search for that as well
				if (preg_match(self::$mdjComplaintDateSearch, $line, $matches))
				$this->setComplaintDate(trim($matches[1]));
			}

			else if (preg_match(self::$judgeSearch, $line, $matches))
			{
				// make sure the judge field isn't blank or doesn't equal "migrated"
				if (!preg_match(self::$migratedJudgeSearch, $matches[1], $junk) && trim($matches[1]) != "")
					$this->setJudge(trim($matches[1]));
			}


			else if  (preg_match(self::$DOBSearch, $line, $matches))
				$this->setDOB(trim($matches[1]));

			else if (preg_match(self::$nameSearch, $line, $matches))
			{
				$this->setFirstName(trim($matches[2]));
				$this->setLastName(trim($matches[1]));

				// we also want to add all of the names that are on the docket sheets to the alias list.
				$person->addAliases(array($this->getLastName() . ", " . $this->getFirstName()));
			}

			else if (preg_match(self::$dispDateSearch, $line, $matches))
				$this->setDispositionDate($matches[1]);

			// find aliases.  Only try to do so if we haven't looked for aliases previously.
			else if (!$this->pastAliases && preg_match(self::$aliasNameStartSearch, $line))

			{

				$i = $line_num+1;
				while (!preg_match(self::$aliasNameEndSearch, $arrestRecordFile[$i]))
				{
					// once in a while, the aliases are at the end of a page, which means we get to the footer information
					// before we get to the regular marker of the end of the aliases.  We have to watch out for this
					// and break if we find it
					if (preg_match(self::$endOfPageSearch, $arrestRecordFile[$i]))
						break;

					// parse out the name from the rest of the alias and then push the alias onto the array of aliases
					if (preg_match("/\w/", $arrestRecordFile[$i]))
					{
						// first parse out the name from the rest of the alias
						// The name can be in two forms: one form is just the name on a line; the other is the name, followed by the SSN and SID.
						$aliasName = explode("  ", trim($arrestRecordFile[$i]),2);

						// then push this onto the alias array
						$this->addAlias(trim($aliasName[0]));
					}

					$i++;
				}

				// once we match the CASE PARTICIPANTS line, we know we are done with this iteration
				$this->pastAliases = TRUE;
				// set the aliases on the person object
				$person->addAliases($this->getAliases());
			}
			// charges can be spread over two lines sometimes; we need to watch out for that
			else if (preg_match(self::$chargesSearch2, $line, $matches))
			{
				// ignore this charge if it is in the exclusion array
				if (in_array(trim($matches[2]), self::$ignoreDisps))
					continue;

				$charge = trim($matches[1]);
				// we need to check to see if the next line has overflow from the charge.
				// this happens on long charges, like possession of controlled substance
				$i = $line_num+1;

				if (preg_match(self::$chargesSearchOverflow, $arrestRecordFile[$i], $chargeMatch))
				{
					$charge .= " " . trim($chargeMatch[1]);
					$i++;
				}

				// also, knock out any strange multiple space situations in the charge, which comes up sometimes.
				$charge = preg_replace("/\s{2,}/", " ", $charge);

				// need to grab the disposition date as well, which is on the next line
				if (isset($this->dispositionDate))
					$dispositionDate = $this->getDispositionDate();
				else if (preg_match(self::$dispDateSearch2, $arrestRecordFile[$i], $dispMatch))
					// set the date;
					$dispositionDate = $dispMatch[2];
				else
					$dispositionDate = NULL;
				$charge = new Charge($charge, $matches[2], trim($matches[4]), trim($dispositionDate), trim($matches[3]));
				$this->addCharge($charge);
			}

			// match a charge for MDJ
			else if ($this->getIsMDJ() && preg_match(self::$mdjChargesSearch, $line, $matches))
			{
				$charge = trim($matches[4]);

				// we need to check to see if the next line has overflow from the charge.
				// this happens on long charges, like possession of controlled substance
				$i = $line_num+1;

				if (preg_match(self::$chargesSearchOverflow, $arrestRecordFile[$i], $chargeMatch))
				{
					$charge .= " " . trim($chargeMatch[1]);
					$i++;
				}


				// add the charge to the charge array
				if (isset($this->dispositionDate))
					$dispositionDate = $this->getDispositionDate();
				else
					$dispositionDate = NULL;
				$charge = new Charge($charge, trim($matches[6]), trim($matches[1]), trim($dispositionDate), trim($matches[3]));
				$this->addCharge($charge);
			}

			else if (preg_match(self::$bailSearch, $line, $matches))
			{
				$this->addBailCharged(doubleval(str_replace(",","",$matches[1])));
				$this->addBailPaid(doubleval(str_replace(",","",$matches[2])));  // the amount paid
				$this->addBailAdjusted(doubleval(str_replace(",","",$matches[3])));
				$this->addBailTotal(doubleval(str_replace(",","",$matches[5])));  // tot final amount, after all adjustments
			}

			else if (preg_match(self::$costsSearch, $line, $matches))
			{
				$this->setCostsCharged(doubleval(str_replace(",","",$matches[1])));
				$this->setCostsPaid(doubleval(str_replace(",","",$matches[2])));  // the amount paid
				$this->setCostsAdjusted(doubleval(str_replace(",","",$matches[3])));
				$this->setCostsTotal(doubleval(str_replace(",","",$matches[5])));  // tot final amount, after all adjustments
			}

            // search for things that only have to do with juvenile cases in philly
            else if ($this->isJuvenilePhilly && preg_match(self::$juvenileNumberSearch, $line, $matches))
                $this->setJNumber(trim($matches[1]));

            else if ($this->isJuvenilePhilly && preg_match(self::$SIDSearch, $line, $matches))
                $this->setSID(trim($matches[1]));

            else if ($this->isJuvenilePhilly && preg_match(self::$PIDSearch, $line, $matches))
                $this->setPID(trim($matches[1]));

            else if ($this->isJuvenilePhilly && preg_match(self::$juvenileNameSearch, $line, $matches))
            {
                list($first,$last) = explode(" ", trim($matches[1]),2);
                $this->setFirstName($first);
                $this->setLastName($last);
            }

            // this is sort of a funny one.  the very last bare date entry in the docket is the date
            // that the case was discharged.  It is on a line by itself and there isn't any
            // consistent context around the date that could be used to find this final date entry.
            // So rather than search for it, I am going to search for EVERY bare date entry and store
            // each as the discharge date.  The final one will override all previous and we will have a
            // discharge date!
            else if ($this->isJuvenilePhilly && preg_match(self::$dischargeDateSearch, trim($line), $matches))
               $this->setJDischargeDate(trim($matches[1]));
		}

	}

	// Compares two arrests to see if they are part of the same case.  Two arrests are part of the
	// same case if the DC or OTNs match; first check DC, then check OTN.
	// There are some cases where the OTNs match, but not the DC.  This can happen when:
	// someone is arrest and charged with multiple sets of crimes; all of these cases go to CP court
	// but they aren't consolidated.  B/c the arrests happened at the same time, OTN will
	// be the same on all cases, but the DC numbers will only match from the MC to the CP that
	// follows
	// Don't match true if we match ourself
	public function compare($that)
	{
		// return false if we match ourself
		if ($this->getFirstDocketNumber() == $that->getFirstDocketNumber())
			return FALSE;
		else if ($this->getDC() != self::$unknownInfo && $this->getDC() == $that->getDC())
			return TRUE;
		else if ($this->getDC() == self::$unknownInfo && ($this->getOTN() != self::$unknownInfo && $this->getOTN() == $that->getOTN()))
		  	return TRUE;
		else
			return FALSE;
	}

	// combines the $this and $that. We assume for the purposes of this function that
	// $this and $that are the same docket number as that was previously checked
	// @param $that is an Arrest, but obtained from the Summary docket sheet, so it doesn't
	// have charge information with, just judge, arrest date, etc...
	public function combineWithSummary($that)
	{
		if (!empty($that->getJudge()))
			$this->setJudge($that->getJudge());
		if (!isset($this->arrestDate) || $this->getArrestDate() == self::$unknownInfo)
			$this->setArrestDate($that->getArrestDate());
		if (!isset($this->dispositionDate) || $this->getDispositionDate() == self::$unknownInfo)
			$this->setDispositionDate($that->getDispositionDate());
	}

	//gets the first docket number on the array
	// Compares $this arrest to $that arrest and determines if they are actually part of the same
	// case.  Two arrests are part of the same case if they have the same OTN or DC number.
	// If the two arrests are part of the same case, combines them by taking all of the information
	// from one case and adding it to the other case (unless that information is already there.
	// It is important to note that you can only combine a CP case with an MC case.  You cannot
	// two MC cases together without a CP.
	// @param $that = Arrest to combine with $this
	public function combine($that)
	{

		// if $this isn't a CP case, then don't combine.  If $that is a CP case, don't combine.
		if (!$this->isCP() || $that->isCP())
		{
			return FALSE;
		}

		// return false if we don't find something with the same DC or OTN number
		if (!$this->compare($that))
			return FALSE;

		// if $that (the MC case) is an expungement itself, then we don't want to combine.
		// If the MC case was an expungement, then no charges will move up from the MC case
		// to the associated CP case.  This happens in the following situation:
		// Person is arrested and charged with three different sets of crimes that show up on
		// 3 different MC cases.  One of the MC cases is completely resolved at the prelim hearing
		// and charges are dismissed.  The other two MC cases have "held for court" charges
		// which are brought up to a CP case.  THe CP case OTN will match all three MC cases, but
		// will only have charges from the two MC cases that were "held for court"
		if ($that->isArrestExpungement())
			return FALSE;

		// combine docket numbers
		$this->setDocketNumber(array_merge($this->getDocketNumber(),$that->getDocketNumber()));

		// combine charges.  Only include $that charges that are not "held for court"
		// The reason for this is that held for court charges will already appear on the CP,
		// they will just appear with a disposition.  We don't want to include held for court
		// charges and then assume that this isn't an expungement in our later logic.
		// This is a possible future thing to change.  Perhaps held for court should be put on
		// And something should be "expungeable" regardless of whether "held for court"
		// charges are on there.
		$thatChargesNoHeldForCourt = array();
		foreach ($that->charges as $charge)
		{
			$thatDisp = $charge->getDisposition();

			// note strange use of strpos.  strpos returns the location of the first occurrence of the string
			// or boolean false.  you have to check with === FALSE b/c the first occurence of the strong could
			// be position 0 or 1, which would otherwise evaluate to true and false!
			if (strpos($thatDisp, "Held for Court")===FALSE && strpos($thatDisp, "Waived for Court")===FALSE)
				$thatChargesNoHeldForCourt[] = $charge;
		}

		// if $thatChargesNoHeldForCourt[] has less elements than $that->charges, we know that
		// some charges were disposed of at the lower court level.  In that case, we need to
		// add the lower court judges in as well on the expungement sheet.
		// @todo add judges here
		$this->setCharges(array_merge($this->getCharges(),$thatChargesNoHeldForCourt));

		// combine bail amounts.  This isn't used for the petitions, but it is helpful for later
		// when we print out the overview of bail.
		// Generally speaking, an individual could have a bail assessment on an MC case, even if
		// all charged went to CP court (this would happen if they failed to appear for a hearing
		// and then later appeared, were sent to CP court, and were tried there.
		// generally speaking, there are not fines on an MC case that is ultimately combined with
		// a CP case.
		$this->setBailChargedTotal($this->getBailChargedTotal()+$that->getBailChargedTotal());
		$this->setBailTotalTotal($this->getBailTotalTotal()+$that->getBailTotalTotal());
		$this->setBailAdjustedTotal($this->getBailAdjustedTotal()+$that->getBailAdjustedTotal());
		$this->setBailPaidTotal($this->getBailPaidTotal()+$that->getBailPaidTotal());

		// set MDJ as "2" if that is an an mdj.  "2" means that this is a case descending from MDJ
		// also set the mdj number
		if ($that->getIsMDJ())
		{
			$this->setIsMDJ(2);
			$this->setMDJDistrictNumber($that->getMDJDistrictNumber());
		}
		return TRUE;
	}


	// @return a comma separated list of all of the dispositions that are on the "charges" array
	// @param if redactableOnly is true (default) returns only redactable offenses

	public function getDispList($redactableOnly=TRUE)
	{
		$disposition = "";
		foreach ($this->getCharges() as $charge)
		{
			// if we are only looking for redactable charges, skip this charge if it isn't redactable
			if ($redactableOnly && !$charge->isRedactable())
				continue;
			if ((stripos($disposition,$charge->getDisposition())===FALSE))
			{
				if ($disposition != "")
					$disposition .= ", ";
				$disposition .= $charge->getDisposition();
			}
		}
		return $disposition;
	}

	// @param redactableOnly - boolean defaults to false; if set to true, only returns redactable charges
	// @return a string holding a comma separated list of charges that are in the charges array;
	// @return if "redactableOnly" is TRUE, returns only those charges that are expungeable

	public function getChargeList($redactableOnly=FALSE)
	{
		$chargeList = "";
		foreach ($this->getCharges() as $charge)
		{
			// if we are trying to only get the list of "Expungeable" offenses, then
			// continue to the next charge if this charge is not Expungeable
			if ($redactableOnly && !$charge->isRedactable())
				continue;
			if ($chargeList != "")
				$chargeList .= ", ";
			$chargeList .= ucwords(strtolower($charge->getChargeName()));
		}
		return $chargeList;
	}



	// returns the age based off of the DOB read from the arrest record
	public function getAge()
	{
		$birth = new DateTime($this->getDOB());
		$today = new DateTime();
		return dateDifference($today, $birth);
	}

	// @return the disposition date of the first charge on the charges array
	// @return if no disposition date exists on the first chage, then sets the dipsositionDate to the migrated disposition date
	public function getDispositionDate()
	{
		if (!isset($this->dispositionDate))
		{
			if (count($this->charges))
			{
				$firstCharge = $this->getCharges();
				$this->setDispositionDate($firstCharge[0]->getDispDate());
			}
			else
				$this->setDispositionDate(self::$unknownInfo);
		}

		return $this->dispositionDate;
	}

	// @function getBestDispotiionDate returns a dispotition date if available.  Otherwise returns
	// the arrest date.
	// @return a date
	public function getBestDispositionDate()
	{
		if ($this->getDispositionDate() != self::$unknownInfo)
			return $this->getDispositionDate();
		else
			return $this->getArrestDate();
	}

	// returns true if this is a criminal offense.  this is true if we see CP|MC-##-CR|SU,
	// not SA or MD
	public function isArrestCriminal()
	{
		if (isset($this->isCriminal))
			return  $this->getIsCriminal();
		else
		{
			$criminalMatch = "/CR|SU|SA|MJ|JV|MD/";
			if (preg_match($criminalMatch, $this->getFirstDocketNumber()))
			{
					$this->setIsCriminal(TRUE);
					return TRUE;
			}
			$this->setIsExpungement(FALSE);
			return FALSE;
		}
	}

	// returns true if this arrest includes ARD offenses.
	public function isArrestARDExpungement()
	{
		if (isset($this->isARDExpungement))
			return  $this->getIsARDExpungement();
		else
		{
			foreach ($this->getCharges() as $num=>$charge)
			{
				if($charge->isARD())
				{
					$this->setIsARDExpungement(TRUE);
					return TRUE;
				}
			}
			$this->setIsARDExpungement(FALSE);
			return FALSE;
		}
	}

	// @function isArrestOver70Expungement() - returns true if the petition is > 70yo and they have been arrest
	// free for at least the last 10 years.
	// @param arrests - an array of all of the other arrests that we are comparing this to to see if they are
	// 10 years arrest free
	//@ return TRUE if the conditions above are me; FALSE if not
	public function isArrestOver70Expungement($arrests, $person)
	{
		// if already set, then just return the member variable
		if (isset($this->isArrestOver70Expungement))
			return $this->isArrestOver70Expungement;

		// return false right away if the petition is younger than 70
		if ($person->getAge() < 70)
		{
			$this->setIsArrestOver70Expungement(FALSE);
			return FALSE;
		}

		// also return false right away if there aren't any charges to actually look at
		if (count($this->getCharges())==0)
		{
			$this->setIsArrestOver70Expungement(FALSE);
			return FALSE;
		}

		// do an over 70 exp if at least one is not redactible; if this is a regular exp, just do a regular exp
		// NOTE: THis may be a problem for HELD FOR COURT charges; keep this in mind
		if ($this->isArrestExpungement())
		{
			$this->setIsArrestOver70Expungement(FALSE);
			return FALSE;
		}

		// at this point we know two things: we are over 70 and we need to get non-redactable charges off of
		// the record
		// Loop through all of the arrests passed in to get the disposition dates or the
		// arrest dates if the disposition dates don't exist.
		// return false if any of them are within 10 years of today

		$dispDates = array();
		$dispDates[] = new DateTime($this->getBestDispositionDate());
		foreach ($arrests as $arrest)
		{
			$dispDates[] = new DateTime($arrest->getBestDispositionDate());
		}

		// look at each dispDate in the array and make sure it was more than 10 years ago
		$today = new DateTime();
		foreach ($dispDates as $dispDate)
		{
			if (abs(dateDifference($dispDate, $today)) < 10)
			{
				$this->setIsArrestOver70Expungement(FALSE);
				return FALSE;
			}
		}

		// if we got here, it means there are no five year periods of freedom
		$this->setIsArrestOver70Expungement(TRUE);
		return TRUE;

	}

    // returns true if any charges on this case ended in conviction
	function isArrestConviction()
    {
        if (isset($this->isArrestConviction))
          return $this->isArrestConviction;
        else
        {
            foreach ($this->getCharges() as $charge)
            {
                if ($charge->isConviction())
                {
                    $this->isArrestConviction = true;
                    return true;
                }
            }
            return false;
        }
    }

	// @function isArrestSummaryExpungement - returns true if this is an expungeable summary
	// arrest.
	// This is true in a slightly more complicated sitaution than the others.  To be a
	// summary expungement a few things have to be true:
	// 1) This has to be a summary offense, characterized by "SU" in the docket number.
	// 2) The person must have been found guilty or plead guilty to the charges (if they were
	// not guilty or dismissed, then there is nothing to worry about - normal expungmenet.
	// 3) The person must have five years arrest free at any time AFTER the arrest.
    // Previously, Commonwealth v. Giulian (Super. Ct. 2015) found that only the 5 years immediately
    // the following conviction matters, but the PA SCt reversed that in July 2016.
	// @note - a problem that might come up is if someone has a summary and then is confined in jail
	// for a long period of time (say 10 years).  This will apear eligible for a summary exp, but
	// is not.
	// @param arrests - an array of all of the other arrests that we are comparing this too to see
	// if they are 5 years arrest free
	// @return TRUE if the conditions above are met; FALSE if not.
	public function isArrestSummaryExpungement($arrests)
	{
		// if already set, then just return the member variable
		if (isset($this->isArrestSummaryExpungement))
			return $this->isArrestSummaryExpungement;

		// return false right away if this is not a summary arrest
		if (!$this->getIsSummaryArrest())
		{
			$this->setIsArrestSummaryExpungement(FALSE);
			return FALSE;
		}

		// also return false right away if there aren't any charges to actually look at
		if (count($this->getCharges())==0)
		{
			$this->setIsArrestSummaryExpungement(FALSE);
			return FALSE;
		}

		// loop through all of the charges; only do a summary exp if none are redactible
		// NOTE: THis may be a problem for HELD FOR COURT charges; keep this in mind
		// NOTE: Is it possible that someone has some not guilty and some guilty for summary charges?
		// NOTE: 9/25/13: The answer is YES: you can have cases where there are some summary expungements
		// under 9122(b)(3) and some expungements b/c there are summary not guilties, all mixed
		// together on the same petition.  I am going to leave the section below in, because to do
		// otherwise would be a pain in the ass.  If I remove the below, I have to
		// make sure that even if there is not a 5 year arrest free period, we are still doing expungements
		// where possible.


		$anyConviction = FALSE;
		// Here is the situation we need to deal with: multiple summary charges, some convictions, some non convictions
		// In that situation, we still want to call this a summary expungement.
		// In another situation, all charges are expungeable.  THis is not a summary expungement.
		// In another situation, all of the charges are convictions.  This is potentially a summary expungement.
		// So we track whether there is any conviction at all.  If there is not,
		foreach ($this->getCharges() as $num=>$charge)
		{

			if ($charge->isSummaryRedactable())
				$anyConviction = TRUE;
/*
			if(!$anyConviction && $charge->isSummaryRedactable() )
			{
				$this->setIsArrestSummaryExpungement(FALSE);
				return FALSE;
			}
			else
				$anyConviction = TRUE;
*/
		}

		if (!$anyConviction)
		{
			$this->setIsArrestSummaryExpungement(FALSE);
			return FALSE;
		}

		// at this point we know two things: summary arrest and the charges are all guilties.
		// now we need to check to see if they are arrest free for five years.
		// Loop through all of the arrests passed in to get the disposition dates or the
		// arrest dates if the disposition dates don't exist.
		// Drop dates that are before this date.
		// Make a sorted array of all of the dates and find the longest gap.
		$thisDispDate = new DateTime($this->getBestDispositionDate());
		$dispDates = array();

		$dispDates[] = $thisDispDate;
		$dispDates[] = new DateTime(); // add today onto the array as well
		foreach ($arrests as $arrest)
		{
			$thatDispDate = new DateTime($arrest->getBestDispositionDate());
			// if the disposition date of that arrest was before this arrest, ignore it
			if ($thatDispDate <= $thisDispDate)
				continue;
			else
				$dispDates[] = $thatDispDate;
		}
		// sort array
		asort($dispDates);

		/******* FOR METHOD WHERE ANY 5 YEAR PERIOD MATTERS.  *****/
		// sort through the first n-1 members of the dateArray and compare them to the next
		// item in the array to see if there is more than 5 years between them
		for ($i=0; $i<(sizeof($dispDates)-1); $i++)
		{
			if (abs(dateDifference($dispDates[$i+1], $dispDates[$i])) >= 5)
			{
				$this->setIsArrestSummaryExpungement(TRUE);
				return TRUE;
			}
		}


		/********** OLD GIULIAN STUFF BEFORE SCT DECISION RESTORING SANITY TO SUMMARIES***************/
		// sort the disp array.  Compare the first date (which should be the disp date of this crime)
		// to the second disp date.  If this is > 5 years, we are in the clear and expungement is proper.
        /*
		usort($dispDates, function($a, $b) {
			if ($a == $b)
				return 0;
			return $a < $b ? -1 : 1;
		});

		if (abs(dateDifference($dispDates[1], $dispDates[0])) >= 5)
		{
			$this->setIsArrestSummaryExpungement(TRUE);
			return(TRUE);
		}
		/**************/

		// if we got here, it means there are no five year periods of freedom
		$this->setIsArrestSummaryExpungement(FALSE);
		return FALSE;

	}


	// returns true if this is an expungeable arrest.  this is true if no charges are guilty
	// or guilty plea or held for court.
	// TODO: There is a problem here, although it rarely comes up.  On MC-51-CR-0040172-2012, which sharon is expunging
	// right now, there are charges listed twice on the MC: once in an "arraignment"  where the charges are "proceed to court"
	// and then again under a prelim hearing where the charges are dismissed.  This function sees the proceed
	// to court charges and believes that this is a redaction even though it is an expungement.  The question is:
	// how do I fix this?  This is a rare case, so maybe I don't need to.  I want to be careful of doing something like
	// ignoring "Proceed to court/held for court" on an MC, b/c then it makes any MC that goes to CP court look like an
	// expungement (b/c the EG will ignore the MC's charges that are going up to CP court).  What this does is force the
	// EG to keep the MC case separate from the CP rather than combine them.  If MC and CP aren't combined, should I do a
	// different check for expungement that ignores held for court like charges?  I don't know, but this is a question
	// for another day.  What I really need to do is rewrite ALL of the logic for the EG expungement engine.
	public function isArrestExpungement()
	{

		if (isset($this->isExpungement))
			return  $this->getIsExpungement();

		else
		{
			foreach ($this->getCharges() as $num=>$charge)
			{
				// the quirky case where on a CP, the held for court charges are listed from the MC
				// case.
				if ($this->isCP() && $charge->isHeldForCourt())
					continue;
				if(!$charge->isRedactable())
				{
					$this->setIsExpungement(FALSE);
					return FALSE;
				}

			}

			// deal with the quirky case where there are no charges on the array.  This happens
			// rarely where there is a docket sheet that lists charges, but doesn't list
			// dispositions at all.
			if (count($this->getCharges()) == 0)
			{
					$this->setIsExpungement(FALSE);
					return FALSE;
			}

			$this->setIsExpungement(TRUE);
			return TRUE;
		}
	}

	// returns true if the first docket number starts with "CP"
	public function isCP()
	{
		if (isset($this->isCP))
			return $this->getIsCP();
		else
		{
			$match = "/^CP/";
			if (preg_match($match, $this->getFirstDocketNumber()))
			{
				$this->setIsCP(TRUE);
				return TRUE;
			}

			$this->setIsCP(FALSE);
			return FALSE;
		}
	}


	// returns true if this is a redactable offense.  this is true if there are charges that are NOT
	// guilty or guilty plea or held for court.  returns true for expungements as well.
	public function isArrestRedaction()
	{
		if (isset($this->isRedaction))
			return  $this->getIsRedaction();

		else
		{
			foreach ($this->getCharges() as $charge)
			{
				// if we don't match Guilty|Guilty Plea|Held for court, this is redactable
				if ($charge->isRedactable())
				{
					$this->setIsRedaction(TRUE);
					return TRUE;
				}
			}
			// if we ever get here, we have no redactable offenses, so return false
			$this->setIsRedaction(FALSE);
			return FALSE;
		}
	}

    // return true only if ALL of the charges are held for court.
    public function isArrestOnlyHeldForCourt()
    {
		if (isset($this->isOnlyHeldForCourt))
        	return  $this->getIsOnlyHeldForCourt();
		else
		{
            $charges = $this->getCharges();
            if (count($charges) == 0)
            {
                $this->isOnlyHeldForCourt=false;
                return false;
            }
			foreach ($charges as $charge)
			{
                $disp = $charge->getDisposition();
                if (!$charge->isHeldForCourt() || empty($disp))
                {
                        $this->isOnlyHeldForCourt=false;
                    return false;
                }
            }
            $this->isOnlyHeldForCourt=true;
            return true;
        }
    }

	// return true if any of the charges are held for court.  this means we are ripe for
	// consolodating with another arrest
	public function isArrestHeldForCourt()
	{
		if (isset($this->isHeldForCourt))
				return  $this->getIsHeldForCourt();
		else
		{
			$heldForCourtMatch = "/[Held for Court|Waived for Court]/";
			foreach ($this->getCharges() as $num=>$charge)
			{
				// if we match Held for court, setheldforcourt = true
				if (preg_match($heldForCourtMatch,$charge->getDisposition()))
				{
					$this->setIsHeldForCourt(TRUE);
					return TRUE;
				}
			}
			// if we ever get here, we have no heldforcourt offenses, so return false
			$this->setIsHeldForCourt(FALSE);
			return FALSE;

		}
	}

	// @returns an associative array with court information based on the county name
	public function getCourtInformation($db)
	{
		// $sql is going to be different based on whether this is an mdj case or a regular case
		$table = "court";
		$column = "county";
		$value = $this->getCounty();

		if ($this->getIsMDJ() == 1)
		{
			$table = "mdjcourt";
			$column = "district";
			$value = $this->getMDJDistrictNumber();
		}

		// sql statements are case insensitive by default
		$query = "SELECT * FROM $table WHERE $table.$column='$value'";
		$result = $db->query($query);

		if (!$result)
		{
			if ($GLOBALS['debug'])
				die('Could not get the court information from the DB:' . $db->error);
			else
				die('Could not get the court Information from the DB');
		}
		$row = $result->fetch_assoc();
		$result->close();
		return $row;
	}


	public function writeExpungement($inputDir, $outputDir, $person, $attorney, $expungeRegardless, $db)
	{
        // act 5 petitions use a different template since the wording is very different
	    if ($_SESSION['sealingRegardless'])
          $docx = new \PhpOffice\PhpWord\TemplateProcessor($inputDir . Arrest::$act5Template);
        else if ($this->isJuvenilePhilly)
            $docx = new \PhpOffice\PhpWord\TemplateProcessor($inputDir . Arrest::$juvenileExpungementTemplate);
        else
            $docx = new \PhpOffice\PhpWord\TemplateProcessor($inputDir . Arrest::$expungementTemplate);

		if ($GLOBALS['debug'])
			print ($this->isArrestExpungement() || $this->isArrestSummaryExpungement)?("Performing Expungement"):("Performing Partial Expungement");

		// set attorney variables
		$docx->setValue("ATTORNEY_HEADER",  htmlspecialchars($attorney->getPetitionHeader(),  ENT_COMPAT, 'UTF-8'));
		$docx->setValue("ATTORNEY_SIGNATURE",  htmlspecialchars($attorney->getPetitionSignature(), ENT_COMPAT, 'UTF-8'));


		// note - we have two name fields - first/last and "real" first/last.  This is because in many cases
		// the petitioner's name does not appear correctly on the docket sheet.  attorneys complained that they were
		// being required to assert the incorrect name for their client in the petition.  the caption gets
		// the name on the docket; the petition body gets the real first name of the individual
		$docx->setValue("FIRST_NAME",  htmlspecialchars($this->getFirstName(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("LAST_NAME", htmlspecialchars($this->getLastName(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("REAL_FIRST_NAME", htmlspecialchars($person->getFirst(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("REAL_LAST_NAME", htmlspecialchars($person->getLast(), ENT_COMPAT, 'UTF-8'));

        if (!$this->isJuvenilePhilly)
        {
    		$docx->setValue("STREET", htmlspecialchars($person->getStreet(), ENT_COMPAT, 'UTF-8'));
	    	$docx->setValue("CITY", htmlspecialchars($person->getCity(), ENT_COMPAT, 'UTF-8'));
		    $docx->setValue("STATE", htmlspecialchars($person->getState(), ENT_COMPAT, 'UTF-8'));
    		$docx->setValue("ZIP", htmlspecialchars($person->getZip(), ENT_COMPAT, 'UTF-8'));
        }

		// if we are not an anon petition, we have to modify the petition a bit
		if (!$attorney->getIsAnon())
		{
			$docx->setValue("ATTORNEY_FOR", htmlspecialchars("Attorney for " . $this->getFirstName() . " " . $this->getLastName(), ENT_COMPAT, 'UTF-8'));
			$docx->setValue("FILING_ATTORNEY", "through counsel " .  $attorney->getFirstName() . " " . $attorney->getLastName() . ", Esquire");
			$docx->setValue("COUNSELOR_FOR_PETITIONER", "Counsel for Petitioner");
			$docx->setValue("ATTORNEY_ELEC_SIG", htmlspecialchars($attorney->getElectronicSig(), ENT_COMPAT, 'UTF-8'));
		}
		else
		{
			$docx->setValue("ATTORNEY_FOR", "");
			$docx->setValue("FILING_ATTORNEY", "filing pro se");
			$docx->setValue("COUNSELOR_FOR_PETITIONER", "");
			$docx->setValue("ATTORNEY_ELEC_SIG", "______________");
		}


		// set the date.  Format = Month[word] Day[number], Year[4 digit number]
		$docx->setValue("PETITION_DATE", htmlspecialchars(date("F j, Y"), ENT_COMPAT, 'UTF-8'));
		// set the type of petition
		// NOTE: Should I just keep the "else clause" and get rid of the if clause?  I think this handles every case for redaction or expungement.  Why not just test
		// the expungements and then move on to redaction?  Or test the redaction and write "Expungement" otherwise.

        if (!$this->isJuvenilePhilly)
        {
    		if (($this->isArrestRedaction() && !$this->isArrestExpungement()) && !$this->isArrestOver70Expungement && !$expungeRegardless && !$_SESSION['sealingRegardless'])
	    		$docx->setValue("EXPUNGEMENT_OR_REDACTION","Partial Expungement");
    		else if (!$_SESSION['sealingRegardless'] && ($this->isArrestExpungement() || $this->isArrestSummaryExpungement || $this->isArrestOver70Expungement || $expungeRegardless))
	    		$docx->setValue("EXPUNGEMENT_OR_REDACTION", "Expungement");

    		if ($attorney->getIFP()==1)
	    		$docx->setValue("IFP_MESSAGE", htmlspecialchars($attorney->getIFPMessage(), ENT_COMPAT, 'UTF-8'));
    		else
	    		$docx->setValue("IFP_MESSAGE", "");
        }

        // set the docket numbers on the petition and order.  I have four different places where docket
        // numbers live, so I have to clone the CP row four different times.  The setValue command
        // actually finds all instances of the variable in question, so I don't have to worry about
        // seting value a number of times.
        $aDocketNumbers = $this->getDocketNumber();
        $docx->cloneRow("CP",count($aDocketNumbers));
        $docx->cloneRow("CP",count($aDocketNumbers));
        $docx->cloneRow("CP",count($aDocketNumbers));

        if (!$this->isJuvenilePhilly)
            $docx->cloneRow("CP",count($aDocketNumbers));

        for ($i=0; $i < count($aDocketNumbers); $i++)
        {
            $j = $i+1;
            $docx->setValue("CP#" . $j, htmlspecialchars($aDocketNumbers[$i], ENT_COMPAT, 'UTF-8'));
        }

        if (!$this->isJuvenilePhilly)
        {
    		$aliases = $person->getAliasCommaList();
	    	if (trim($aliases) == FALSE)
		    	$aliases = "None";
    		$docx->setValue("ALIASES", htmlspecialchars($aliases, ENT_COMPAT, 'UTF-8'));
        }

		$docx->setValue("OTN", htmlspecialchars($this->getOTN(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("DC", htmlspecialchars($this->getDC(), ENT_COMPAT, 'UTF-8'));
		// $docx->setValue("PP", $person->getPP()); // PP/PID not needed anymore and we lost secure access
		$docx->setValue("SSN", htmlspecialchars($person->getSSN(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("DOB", htmlspecialchars($this->getDOB(), ENT_COMPAT, 'UTF-8'));


		// setting the disposition list is different dependingo on what type of expungement
		// this is.  An ARD will say that the ard was completed; a summary might say something else
		// and a regular expungement/redaction will say the actual dispositions
		// if this is an ard expungement

		if ($this->isArrestARDExpungement())
		{
			$docx->setValue("DISPOSITION_LIST", htmlspecialchars($this->getDispList(), ENT_COMPAT, 'UTF-8'));
			$docx->setValue("ARD_EXTRA", htmlspecialchars(" and the petitioner successfully completed ARD. The ARD completion letter is attached to this petition", ENT_COMPAT, 'UTF-8'));
			$docx->setValue("SUMMARY_EXTRA", "");
		}
		else if ($this->isArrestOver70Expungement)
		{
			// print the disposition list with all offenses, not just redactable ones
			$docx->setValue("DISPOSITION_LIST", htmlspecialchars($this->getDispList(FALSE), ENT_COMPAT, 'UTF-8'));
			$docx->setValue("ARD_EXTRA", "");
			$docx->setValue("SUMMARY_EXTRA", htmlspecialchars(" and the Petitioner is over 70 years old has been free of arrest or prosecution for ten years following from completion the sentence", ENT_COMPAT, 'UTF-8'));
		}
        // these fields don't exist on act5 petitions
	    else if ($_SESSION['sealingRegardless'])
          ;

        // these fields don't exist on juvenile expungement petitions
        else if ($this->isJuvenilePhilly)
          ;

		else if ($this->isArrestSummaryExpungement)
		{
			$docx->setValue("DISPOSITION_LIST", htmlspecialchars("summary convictions", ENT_COMPAT, 'UTF-8'));
			$docx->setValue("ARD_EXTRA", "");
			$docx->setValue("SUMMARY_EXTRA", htmlspecialchars(".  The petitioner has been arrest free for more than five years since this summary conviction", ENT_COMPAT, 'UTF-8'));
		}
		else
		{
			$docx->setValue("DISPOSITION_LIST", htmlspecialchars($this->getDispList(), ENT_COMPAT, 'UTF-8'));
			$docx->setValue("ARD_EXTRA", "");
			$docx->setValue("SUMMARY_EXTRA", "");
		}


		// for costs, we have to subtract out any effect that bail may have had on the costs and fines.  The rules only require
		// that we tell the court costs and fines accrued and paid off, not bail accrued and paid off
        if (!$this->isJuvenilePhilly)
        {
    		$docx->setValue("TOTAL_FINES", htmlspecialchars("$" . number_format($this->getCostsCharged() - $this->getBailCharged(),2), ENT_COMPAT, 'UTF-8'));
	    	$docx->setValue("FINES_PAID", htmlspecialchars("$" . number_format($this->getCostsPaid() + $this->getCostsAdjusted() - $this->getBailPaid() - $this->getBailAdjusted(),2), ENT_COMPAT, 'UTF-8'));
		}

		// if judge exists, then write "Judge $judge"; otherwise write "Unknown Judge"
		$tempJudge = (isset($this->judge) && $this->getJudge() != "") ? "Judge " . $this->getJudge() : "Unknown Judge";
		$docx->setValue("JUDGE", htmlspecialchars($tempJudge, ENT_COMPAT, 'UTF-8'));

        if (!$this->isJuvenilePhilly)
        {
            // sometimes there is no arrest date available; if there is a complaint date, use that on the order
            // otherwise just put in N/A
		    if (!empty($this->arrestDate))
        		$docx->setValue("ARREST_COMPLAINT_DATE", "Arrest Date: " . htmlspecialchars($this->getArrestDate(), ENT_COMPAT, 'UTF-8'));
	    	elseif (!empty($this->complaintDate))
    	    	$docx->setValue("ARREST_COMPLAINT_DATE", "Complaint Date: " . htmlspecialchars($this->getComplaintDate(), ENT_COMPAT, 'UTF-8'));
            else
        		$docx->setValue("ARREST_COMPLAINT_DATE", "Arrest Date: N/A");
        }


		$docx->setValue("ARREST_DATE", htmlspecialchars($this->getArrestDate(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("AFFIANT", htmlspecialchars($this->getArrestingOfficer(), ENT_COMPAT, 'UTF-8'));

//		if ($this->isArrestRedaction() && !$this->isArrestExpungement())
//			$docx->setValue("EXPUNGEABLE_CHARGE_LIST", $this->getChargeList(TRUE));


        if (!$this->isJuvenilePhilly)
    		$docx->setValue("COUNTY", htmlspecialchars($this->getCounty(), ENT_COMPAT, 'UTF-8'));

        $docx->setValue("ARRESTING_AGENCY", htmlspecialchars($this->getArrestingAgency(), ENT_COMPAT, 'UTF-8'));

        // in montgomery county, we have to put the actual address of the arresting agency, which is
        // stored in the db.  in other counties, we just need to write the name of the county as the
        // address of the arresting agency
        if ($this->getCounty()=="Montgomery")
    		$docx->setValue("AGENCY_ADDRESS", htmlspecialchars($this->getAgencyAddress(), ENT_COMPAT, 'UTF-8'));
        else
        	$docx->setValue("AGENCY_ADDRESS", htmlspecialchars($this->getCounty(), ENT_COMPAT, 'UTF-8') . " County, PA");

        if (!$this->isJuvenilePhilly)
    		$docx->setValue("COMPLAINT_DATE", htmlspecialchars($this->getComplaintDate(), ENT_COMPAT, 'UTF-8'));

		$mdjNumberForTemplate = "";

        if (!$this->isJuvenilePhilly)
        {
    		if ($this->getIsMDJ() == 1)
	    		// if this is an mdj, set the below var so that it inputs onto the shset the district number
		    	// if this isn't an mdj, then we set nothing and this field will be blanked out on the petition.
			    $mdjNumberForTemplate = "Magisterial District Number: {$this->getMDJDistrictNumber()}";
    		$docx->setValue("MDJ_DISTRICT_NUMBER", htmlspecialchars($mdjNumberForTemplate, ENT_COMPAT, 'UTF-8'));

	    	// set the agencies for receiving orders.  As silly as this is, I need to do this through
		    // php since there are different agencies that are notified if this is an mdj case.
    		$agencies = array(	"The Clerk of Courts of {$this->getCounty()} County, Criminal Division",
	    						"The {$this->getCounty()} County District Attorney`s Office",
		    					"The Pennsylvania State Police, Central Records",
			    				"A.O.P.C. Expungement Unit",
				    			$this->getArrestingAgency(),
					    		"{$this->getCounty()} County Department of Adult Probation and Parole");
    		if ($this->getIsMDJ())
	    		// if this is an mdj case, put the MDJ court in the list, right after the clerk of courts
		    	array_splice($agencies,1,0,"Magisterial District Court {$this->getMDJDistrictNumber()}");


            $docx->cloneRow("AGENCY_NAME",count($agencies));
	    	for ($i=0; $i < count($agencies); $i++)
		    {
                $j = $i+1;
    			$docx->setValue("AGENCY_NAME#" . $j, htmlspecialchars($j . ". " . $agencies[$i], ENT_COMPAT, 'UTF-8'));
	    	}


    		$courtInformation = $this->getCourtInformation($db);

            // put all court information together into one block.  If there isn't any court information,
            // this allows as few newlines as possible in the petition and order that are generated
            $formattedCourtInformation = "";
            if (!empty($courtInformation['courtName']))
            {
                $formattedCourtInformation = $courtInformation['courtName'] . "\r\n";
                $formattedCourtInformation .= $courtInformation['address'] . ' ' . $courtInformation['address2'] . "\r\n";
                $formattedCourtInformation .= $courtInformation['city'] . ", " . $courtInformation['state'] . ' ' . $courtInformation['zip'];
            }
    		$docx->setValue("COURT_INFORMATION", htmlspecialchars($formattedCourtInformation, ENT_COMPAT, 'UTF-8'));


            // if this isn't philadelphia, say that the CHR is attached.
    		if ($this->getCounty()!="Philadelphia")
            {
                // we also don't include the CHR for ARD expungements in Montco
                if ($this->getCounty()=="Montgomery" && $this->isArrestARDExpungement())
        			$docx->setValue("INCLUDE_CHR", "");
                $docx->setValue("INCLUDE_CHR", htmlspecialchars("I have attached a copy of my Pennsylvania State Police Criminal History which I have obtained within 60 days before filing this petition.", ENT_COMPAT, 'UTF-8'));
            }
    		else
    			$docx->setValue("INCLUDE_CHR", "Pursuant to local practice, the Commonwealth agrees to waive the requirement of attachment to this Petition of a current copy of the petitioner’s Pennsylvania State Police criminal history report.  This waiver may be revoked by the Commonwealth in any case and at any time prior to the granting of the relief requested.");

	    	// if this is a summary arrest or this is an MDJ case,  this is a 490 petition
		    // otherwise it is a 790 petition
    		// NOTE: 12/2013: Previously the first part of the if statement checked if
	    	// this was a summary expungement, not a summary arrest.  There is some uncertainty regarding
    		// whether the 490 or 790 rule is the proper one under which to do expungements of summary offenses
	    	// that are dropped, not convictions.  But the court wants 490 petitions in that case
    		// so now all SU cases are going to be summary expungements under 490.
            if (!$_SESSION['sealingRegardless'])
            {
	    	    if ($this->getIsSummaryArrest() || $this->getIsMDJ() == 1)
		    	    $docx->setValue("490_OR_790", "490");
		        else
    			    $docx->setValue("490_OR_790", "790");
    /*
        		// add in extra order information for CREP
	        	if ($attorney->getProgramId() == 2)
    		    {
	    		    $crepOrderLanguage = "All criminal justice agencies upon whom this order is served also are enjoined from disseminating to any non-criminal justice agency any and all criminal history record information ordered to be expunged/redacted pursuant to this Order unless otherwise permitted to do so pursuant to the Criminal History Information Records Act.  ";
    	    		$docx->setValue("CREP_EXTRA_ORDER_LANGUAGE", htmlspecialchars($crepOrderLanguage, ENT_COMPAT, 'UTF-8'));
	    	    }

    		    else
     */
	    		    $docx->setValue("CREP_EXTRA_ORDER_LANGUAGE", "");
            }
        }

        // hacky; should create a getExpungeable Charges function instead
		$i=0;

	    // first count the number of expungeable charges
	    foreach ($this->getCharges() as $charge)
		{

			if (!$this->isArrestOver70Expungement && !$expungeRegardless && !$_SESSION['sealingRegardless'] && !$this->isJuvenilePhilly)
			{
				if (!$this->isArrestSummaryExpungement && !$charge->isRedactable())
					continue;
				// removed 9/25/2013 to make it so summary expungements can have summary redactable and
				// regular redactable charges on them.
				//if ($this->isArrestSummaryExpungement && !$charge->isSummaryRedactable())
				//	continue;
			}
		    $i = $i+1;
		}

        // now clone the table row the correct number of times and make substitutions
        // Do the clone twice--once to get the table in the petition and once in the order
		if ($i>0)
        {
	          $docx->cloneRow('CODE_SEC',$i);
	          $docx->cloneRow('CODE_SEC',$i);
        }

		$j = 1;
        foreach ($this->getCharges() as $charge)
	    {
		    if (!$this->isArrestOver70Expungement && !$expungeRegardless && !$_SESSION['sealingRegardless'] && !$this->isJuvenilePhilly)
		    {
			    if (!$this->isArrestSummaryExpungement && !$charge->isRedactable())
			        continue;
		    }


		    // sometimes disp date isn't associated with a charge.  If not, just use the disposition
		    // date of the whole shebang.  It is a good guess, at the very least.
		    $dispDate = $charge->getDispDate();
		    if ($dispDate == null || $dispDate == "")
		      $dispDate = $this->getDispositionDate();

            // if this is a juvenile case, we just put the discharge date in the matrix
            if ($this->isJuvenilePhilly)
              $dispDate = $this->getJDischargeDate();

		    // after cloning, the variables are named CHARGES#1, CHARGES#2, CHARGES#3, etc...
		    $docx->setValue("CHARGE#" . $j, htmlspecialchars($charge->getChargeName(), ENT_COMPAT, 'UTF-8'));
		    $docx->setValue("GRADE#" . $j, htmlspecialchars($charge->getGrade(), ENT_COMPAT, 'UTF-8'));
		    $docx->setValue("CODE_SEC#" . $j, htmlspecialchars($charge->getCodeSection(), ENT_COMPAT, 'UTF-8'));
		    $docx->setValue("DISP#" . $j, htmlspecialchars($charge->getDisposition(), ENT_COMPAT, 'UTF-8'));
		    $docx->setValue("DISP_DATE#" . $j, htmlspecialchars($dispDate, ENT_COMPAT, 'UTF-8'));
		    $j = $j+1;
		}


        // update the variables specific to juvenile cases
        if ($this->isJuvenilePhilly)
        {
		    $docx->setValue("JNUMBER", htmlspecialchars($this->jNumber, ENT_COMPAT, 'UTF-8'));
		    $docx->setValue("AGE", htmlspecialchars($this->getAge(), ENT_COMPAT, 'UTF-8'));
		    $docx->setValue("JUVENILE_DISCHARGE_DATE", htmlspecialchars($this->getJDischargeDate(), ENT_COMPAT, 'UTF-8'));
            $docx->setValue("SID", htmlspecialchars($this->getSID(), ENT_COMPAT, 'UTF-8'));
            $docx->setValue("PPID", htmlspecialchars($this->getPID(), ENT_COMPAT, 'UTF-8'));
            //print "PID: " . $this->getPID();
        }

		// save template to file
		$outputFile = $outputDir . $this->getFirstName() . $this->getLastName() . $this->getFirstDocketNumber();
		if ($_SESSION['sealingRegardless'])
            $outputFile .= "Sealing";
        else if ($this->isArrestARDExpungement())
			$outputFile .= "ARDExpungement";
        else if ($this->isJuvenilePhilly)
            $outputFile .= "JuvenileExpungement";
		else if ($this->isArrestExpungement() || $expungeRegardless)
			$outputFile .= "Expungement";
		else  if ($this->isArrestOver70Expungement)
			$outputFile .= "ExpungementOver70";
        else if ($this->isArrestSummaryExpungement)
          $outputFile .= "SummaryExpungement";
		else
			$outputFile .= "PartialExpungement";
		$docx->saveAs($outputFile . ".docx");
		return $outputFile . ".docx";

	}


	public function writeIFP($templateDir, $person, $attorney)
	{
        $docx = new \PhpOffice\PhpWord\TemplateProcessor($templateDir . Arrest::$IFPTemplate);

		if ($GLOBALS['debug'])
			print "Writing IFP Template.";

        $this->writeHeader($docx, $person, $attorney);

		// output the file for later pickup
		$outputFile = $GLOBALS["dataDir"] . $this->getFirstName() . $this->getLastName() . $this->getFirstDocketNumber() . "IFP.docx";
		$docx->saveAs($outputFile);
		return $outputFile;
	}

	// write a certificate of service for montco
    public function writeCOS($templateDir, $person, $attorney)
	{
        $docx = new \PhpOffice\PhpWord\TemplateProcessor($templateDir . Arrest::$COSTemplate);

		if ($GLOBALS['debug'])
			print "Writing COS Template.";

        $this->writeHeader($docx, $person, $attorney);
		$docx->setValue("ATTORNEY_SIGNATURE",  htmlspecialchars($attorney->getPetitionSignature(), ENT_COMPAT, 'UTF-8'));
    	$docx->setValue("ATTORNEY_ELEC_SIG", htmlspecialchars($attorney->getElectronicSig(), ENT_COMPAT, 'UTF-8'));

		// output the file for later pickup
        $outputFile = $GLOBALS["dataDir"] . $this->getFirstName() . $this->getLastName() . $this->getFirstDocketNumber() . "CertificateOfService.docx";
		$docx->saveAs($outputFile);
		return $outputFile;
    }

    // takes a docx object and writes out the basic petition header information
    private function writeHeader($docx, $person, $attorney)
    {
		// set attorney and client vars
        $docx->setValue("ATTORNEY_HEADER", htmlspecialchars($attorney->getPetitionHeader(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("ATTORNEY_FIRST", htmlspecialchars($attorney->getFirstName(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("ATTORNEY_LAST", htmlspecialchars($attorney->getLastName(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("PROGRAM_NAME", htmlspecialchars($attorney->getProgramName(), ENT_COMPAT, 'UTF-8'));
    	$docx->setValue("ATTORNEY_FOR", htmlspecialchars("Attorney for " . $this->getFirstName() . " " . $this->getLastName(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("PETITION_DATE", date("F j, Y"));
		$docx->setValue("FIRST_NAME", htmlspecialchars($this->getFirstName(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("LAST_NAME", htmlspecialchars($this->getLastName(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("STREET", htmlspecialchars($person->getStreet(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("CITY", htmlspecialchars($person->getCity(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("STATE", htmlspecialchars($person->getState(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("ZIP", htmlspecialchars($person->getZip(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("OTN", htmlspecialchars($this->getOTN(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("SSN", htmlspecialchars($person->getSSN(), ENT_COMPAT, 'UTF-8'));
		$docx->setValue("DOB", htmlspecialchars($this->getDOB(), ENT_COMPAT, 'UTF-8'));
		// $docx->setValue("SID", $person->getSID());  // SID not needed anymore and CLS lost secure access
		$docx->setValue("COUNTY", htmlspecialchars($this->getCounty(), ENT_COMPAT, 'UTF-8'));
		$today = new DateTime();
		$docx->setValue("ORDER_YEAR", $today->format('Y'));

        // set the docket numbers on the petition.
        $aDocketNumbers = $this->getDocketNumber();
        $docx->cloneRow("CP",count($aDocketNumbers));
        for ($i=0; $i < count($aDocketNumbers); $i++)
        {
            $j = $i+1;
            $docx->setValue("CP#" . $j, htmlspecialchars($aDocketNumbers[$i], ENT_COMPAT, 'UTF-8'));
	    }

	}


	public function simplePrint()
	{
		echo "<div>Docket #:";
		foreach ($this->getDocketNumber() as $value)
			print "$value | ";
		echo "</div><div>";
		echo "Name: " . $this->getFirstName() . ". " . $this->getLastName();
		echo "</div><div>";
		echo "DOB: " . $this->getDOB();
		echo "</div><div>";
		echo "age: " . $this->getAge();
		echo "</div><div>";
		echo "OTN: " . $this->getOTN();
		echo "</div><div>";
		echo "DC: " .$this->getDC();
		echo "</div><div>";
		echo "arrestingOfficer: " .$this->getArrestingOfficer();
		echo "</div><div>";
		echo "arrestDate: " . $this->getArrestDate();
		echo "</div><div>";
		echo "judge: " .$this->getJudge();
		echo "</div>";
		foreach ($this->getCharges() as $num=>$charge)
		{
			echo "<div>";
			echo "charge $num: " . $charge->getChargeName() . "|".$charge->getDisposition()."|".$charge->getCodeSection()."|".$charge->getDispDate();
			echo "</div>";
		}
		echo "<div>";
		echo "Total Costs: " .$this->getCostsTotal();
		echo "</div><div>";
		echo "Costs Paid: " . $this->getCostsPaid();
		echo "</div>";
	}


	public function writeExpungementToDatabase($person, $attorney, $db, $saveDocketSheet)
	{
		// the defendant has already been inserted
		// next insert the arrest, which includes the defendant ID
		// next insert each charge, which includes the arrest id and the defendant ID
		// finally insert the expungement, which includes the arrest id, the defendant id, the chargeid, the userid, and a timestamp

		$defendantID = $person->getPersonID();
		$attorneyID = $attorney->getUserID();
		$arrestID = $this->writeArrestToDatabase($defendantID, $db);
		// we only want to write an expungement to the database if this is a redactable arrest
		if ($this->isArrestExpungement() || $this->isArrestRedaction() || $this->isArrestSummaryExpungement)
			$expungementID = $this->writeExpungementDataToDatabase($arrestID, $defendantID, $attorneyID, $db);
		else
			$expungementID = "NULL";

		$numRedactableCharges = 0;
		foreach ($this->getCharges() as $charge)
		{
			// if the charge isn't redactable, we don't want to include an expungement ID
			// The expungementID may be placed on other charges from the same arrest.
			// We use a tempID so that we don't change the value of the main variable each time
			// through the loop.
			// If we are a redactable charge, increment the counter.
			$tempExpungementID = $expungementID;
			if (!$charge->isRedactable() && (!$this->isArrestSummaryExpungement && !$this->isArrestOver70Expungement))
				$tempExpungementID = "NULL";
			else
				$numRedactableCharges++;

			$chargeID = $this->writeChargeToDatabase($charge, $arrestID, $defendantID, $tempExpungementID, $db);
		}

		$this->updateExpungementWithNumCharges($expungementID, $numRedactableCharges, $db);

		// finally, save the PDF to the database, if there was a pdf file to save
		if ($saveDocketSheet)
			$this->writePDFToDatabase($expungementID, $db);


	}

	// @return the id of the arrest just inserted into the database
	// @param $defendantID - the id of the defendant that this arrest concerns
	// @param $db - the database handle
	public function writeArrestToDatabase($defendantID, $db)
	{
		$sql = "INSERT INTO arrest (`defendantID`, `OTN` ,`DC` ,`docketNumPrimary` ,`docketNumRelated`,
			`arrestingOfficer` ,`arrestDate` ,`dispositionDate` ,`judge` ,`costsTotal` ,`costsPaid` ,
			`costsCharged` ,`costsAdjusted` ,`bailTotal` ,`bailCharged` ,`bailPaid` ,`bailAdjusted` ,
			`bailTotalToal` ,`bailChargedTotal` ,`bailPaidTotal` ,`bailAdjustedTotal` ,`isARD` ,`isSummary`,
			`county` ,`policeLocality`) VALUES ('$defendantID', '" . $this->getOTN() .
			"', '" . $this->getDC() . "', '" . $this->getFirstDocketNumber() . "',
			'" . implode("|", $this->getDocketNumber()) . "', '" . $db->real_escape_string($this->getArrestingOfficer()) .
			"', '" . dateConvert($this->getArrestDate()) . "', '" . dateConvert($this->getDispositionDate()) .
			"', '" . $db->real_escape_string($this->getJudge()) . "', '" . $this->getCostsTotal() . "', '"
			. $this->getCostsPaid() . "', '" . $this->getCostsCharged() . "', '" . $this->getCostsAdjusted() .
			"', '" . $this->getBailTotal() . "', '" . $this->getBailCharged() . "', '" . $this->getBailPaid() .
			"', '" . $this->getBailAdjusted() . "', '" . $this->getBailTotalTotal() . "', '" .
			$this->getBailChargedTotal() . "', '" . $this->getBailPaidTotal() . "', '" .
			$this->getBailAdjustedTotal() . "', '" . $this->getIsARDExpungement() . "', '" .
			$this->getIsSummaryArrest() . "', '" . $this->getCounty() . "', '" .
			$db->real_escape_string($this->getArrestingAgency()) . "')";

		if ($GLOBALS['debug'])
			print $sql;
		if (!$db->query($sql))
		{
			if ($GLOBALS['debug'])
				die('Could not add the arrest to the DB:' . $db->error);
			else
				die('Could not add the arrest to the DB');
		}
		return $db->insert_id;
	}

	// @return the id of the charge just inserted into the database
	// @param $defendantID - the id of the defendant that this arrest concerns
	// @param $db - the database handle
	// @param $charge - the charge that we are inserting
	// @param $arrestID - the id of the arrest that we are innserting
	public function writeChargeToDatabase($charge, $arrestID, $defendantID, $expungementID, $db)
	{
		$sql = "INSERT INTO charge (`arrestID`, `defendantID`, `expungementID`, `chargeName`, `disposition`, `codeSection`, `dispDate`, `isARD`, `isExpungeableNow`, `grade`, `arrestDate`) VALUES ('$arrestID', '$defendantID', $expungementID, '" . $db->real_escape_string($charge->getChargeName())  . "', '" . $db->real_escape_string($charge->getDisposition()) . "' , '" . $charge->getCodeSection() . "', '" . dateConvert($charge->getDispDate()) . "', '" . $charge->getIsARD() . "', '" . $charge->getIsRedactable() . "', '" . $charge->getGrade() . "', '" . dateConvert($this->getArrestDate()) . "')";

		if (!$db->query($sql))
		{
			if ($GLOBALS['debug'])
				die('Could not add the charge to the DB:' . $db->error);
			else
				die('Could not add the charge to the DB');
		}
		return $db->insert_id;
	}

	// @return the expungementID
	// @param $defendantID - the id of the defendant that this arrest concerns
	// @param $db - the database handle
	// @param $arrestID - the id of the arrest that we are innserting
	// @param $chargeID - the id of the charge that we are innserting
	public function writeExpungementDataToDatabase($arrestID, $defendantID, $attorneyID, $db)
	{
		$sql  = "INSERT INTO  expungement (`arrestID`, `defendantID`, `userid`, `isExpungement`, `isRedaction`, `isSummaryExpungement`, `timestamp`) VALUES ('$arrestID',  '$defendantID', '$attorneyID', '" . $this->isArrestExpungement() . "', '" . $this->isArrestRedaction() . "', '" . $this->isArrestSummaryExpungement ."', CURRENT_TIMESTAMP)";

		if (!$db->query($sql))
		{
			if ($GLOBALS['debug'])
				die('Could not add the expungement to the DB:' . $db->error);
			else
				die('Could not add the expungement to the DB');
		}
		return $db->insert_id;

	}

	// @return none
	// @function updateExpungementWithNumCharges - updates the expungement table with the
	// number of charges that were expungeable
	// @param $expungementID The id of the expungement that we want to update.  Will be "NULL" if there is no expungement to update.
	// @param $numRedactableCharges The number of charges that we want to redact.  Will be zero if there are no charges.
	// @param $db - the database handle
	public function updateExpungementWithNumCharges($expungementID, $numRedactableCharges, $db)
	{
		if ($expungementID != "NULL" && $numRedactableCharges > 0)
		{
			$sql  = "UPDATE expungement SET numRedactableCharges=$numRedactableCharges WHERE expungementID=$expungementID";

			if (!$db->query($sql))
			{
				if ($GLOBALS['debug'])
					die('Could not update the expungement with the number of charges:' . $db->error);
				else
					die('Could not update the expungement with the number of charges');
			}
		}
		return;
	}

    // @return string
    // @function getAgencyAddress() returns the address of the arresting agency.
    // If there is no match, returns the county and state.

    public function getAgencyAddress()
    {

        // if there is no arresting agency for some reason, just return the county and exit
        if (empty($this->getArrestingAgency()))
            return ($this->getCounty() . " County, PA");

        $query = 'SELECT * FROM Police WHERE MATCH(name) AGAINST("' . $this->getArrestingAgency() . '") LIMIT 1;';
        $result = $GLOBALS['db']->query($query);

        if (!$result)
        {
            if ($GLOBALS['debug'])
                die('Could not get the Police information from the DB:' . $db->error);
            else
            {
                //$result->close();
                return ($this->getCounty() . " County, PA");
            }
        }

        $address;
        // if there are no results returned, just get the county, otherwise get the full address
        if($result->num_rows > 0)
        {
            $row = $result->fetch_assoc();
            $address = $row['street'] . ", " . $row['city'] . ", " . $row['state'] . " " . $row['zip'];
        }
        else
            $address = $this->getCounty() . " County, PA";
        $result->close();
        return $address;
    }

	// @return none
	// @function writePDFToDatabase - writes the PDF docket sheet to the database
	// @param $arrestID -  The id of the particular arrest that we are uploading a pdf for.
	// @param $db - the database handle
	public function writePDFToDatabase($expungementID, $db)
	{
		$file = $this->getPDFFile();

		if (isset($file))
		{
		/*
			$sql = "INSERT INTO arrestPDFDocketSheet (arrestID, size, data) VALUES ('$arrestID', '" .  filesize($file) . "', '" . mysql_real_escape_string (file_get_contents ($file)) . "')";
			$result = mysql_query($sql, $db);
			if (!$result)
			{
				if ($GLOBALS['debug'])
					die('Could not insert the pdf into the DB:' . mysql_error());
				else
					die('Could not insert the PDF into the DB.');
			}
		*/
			$destination = $GLOBALS['docketSheetsDir'] . $expungementID;
			copy($file, $destination);
		}
		return;
	}

	/**
	* @function checkCleanSlateMurderFelony
	* @param: none
	* @return: an associative array in the format charge: message,
  * 				 or a list of null values.
	**/
	public function checkCleanSlateMurderFelony()
	{
		// if we've done this before for this case, just return what we have
		if (isset($this->cleanSlateEligible['MurderF1'])) {
			return $this->cleanSlateEligible['MurderF1'];
		} else {
			$this->cleanSlateEligible['MurderF1'] = Array();
		}

		foreach ($this->getCharges() as $charge)
		{
			$this->cleanSlateEligible['MurderF1'][] = $charge->checkCleanSlateMurderFelony();
		}
		return $this->cleanSlateEligible['MurderF1'];
	}

	/**
	* @function checkCleanSlatePast10MFConviction
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast10MFConviction()
	{
		// if we've done this before for this case, just return what we have
		if (isset($this->cleanSlateEligible['Past10MFConviction']))
			return $this->cleanSlateEligible['Past10MFConviction'];

		// check to see if this conviction is more or less than 10 years old
		// if it is less than 10, check to see if there is a conviction
		// if more than 10, return
		$thisDispDate = new DateTime($this->getBestDispositionDate());

		$dateDiff=($thisDispDate->diff(new DateTime(), TRUE))->y;

		if ($dateDiff < 10 && $this->isArrestConviction() && !$this->getIsSummaryArrest())
			$this->cleanSlateEligible['Past10MFConviction'][] = "This case happened less than 10 years ago (".$dateDiff.").";

		else
			$this->cleanSlateEligible['Past10MFConviction'][] = null ;

		return $this->cleanSlateEligible['Past10MFConviction'];
	}


	/**
	* @function checkCleanSlatePast15ProhibitedConviction
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast15ProhibitedConviction()
	{
		// if we've done this before for this case, just return what we have
		if (isset($this->cleanSlateEligible['Past15ProhibitedConviction']))
			return $this->cleanSlateEligible['Past15ProhibitedConviction'];

		// first check  to see if this is a conviction arrest in the last 15
		// years
		$thisDispDate = new DateTime($this->getBestDispositionDate());

		$dateDiff=($thisDispDate->diff(new DateTime(), TRUE))->y;
		if ($dateDiff < 15 && $this->isArrestConviction() && !$this->getIsSummaryArrest())
		{
			foreach ($this->getCharges() as $charge)
			{
				$this->cleanSlateEligible['Past15ProhibitedConviction'][] = $charge->checkCleanSlatePast15ProhibitedConviction();
			}
		}
		else
			$this->cleanSlateEligible['Past15ProhibitedConviction'][] = null;

		return $this->cleanSlateEligible['Past15ProhibitedConviction'];
	}

	/**
	* @function checkCleanSlatePast15MoreThanOneM1F
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast15MoreThanOneM1F()
	{
		// if we've done this before for this case, just return what we have
		if (isset($this->cleanSlateEligible['Past15MoreThanOneM1F']))
			return $this->cleanSlateEligible['Past15MoreThanOneM1F'];

		// first check  to see if this is a conviction arrest in the last 15
		// years
		$thisDispDate = new DateTime($this->getBestDispositionDate());

		$dateDiff=($thisDispDate->diff(new DateTime(), TRUE))->y;
		if ($dateDiff < 15 && $this->isArrestConviction() && !$this->getIsSummaryArrest())
		{
			foreach ($this->getCharges() as $charge)
			{
				$this->cleanSlateEligible['Past15MoreThanOneM1F'][] = $charge->checkCleanSlatePast15MoreThanOneM1F();
			}
		}
		else
			$this->cleanSlateEligible['Past15MoreThanOneM1F'][] = null;

		return $this->cleanSlateEligible['Past15MoreThanOneM1F'];
	}

	/**
	* @function checkCleanSlatePast20MoreThanThreeM2M1F
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast20MoreThanThreeM2M1F()
	{
		// if we've done this before for this case, just return what we have
		if (isset($this->cleanSlateEligible['Past20MoreThanThreeM2M1F']))
			return $this->cleanSlateEligible['Past20MoreThanThreeM2M1F'];

		// first check  to see if this is a conviction arrest in the last 20
		// years
		$thisDispDate = new DateTime($this->getBestDispositionDate());

		$dateDiff=($thisDispDate->diff(new DateTime(), TRUE))->y;
		if ($dateDiff < 20 && $this->isArrestConviction() && !$this->getIsSummaryArrest())
		{
			foreach ($this->getCharges() as $charge)
			{
				$this->cleanSlateEligible['Past20MoreThanThreeM2M1F'][] = $charge->checkCleanSlatePast20MoreThanThreeM2M1F();
			}
		}
		else
			$this->cleanSlateEligible['Past20MoreThanThreeM2M1F'][] = null;

		return $this->cleanSlateEligible['Past20MoreThanThreeM2M1F'];
	}

	/**
	* @function checkCleanSlatePast15ProhibitedConviction
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast20FProhibitedConviction()
	{
		// if we've done this before for this case, just return what we have
		if (isset($this->cleanSlateEligible['Past20FProhibitedConviction']))
			return $this->cleanSlateEligible['Past20FProhibitedConviction'];

		// first check  to see if this is a conviction arrest in the last 20
		// years
		$thisDispDate = new DateTime($this->getBestDispositionDate());

		$dateDiff=($thisDispDate->diff(new DateTime(), TRUE))->y;
		if ($dateDiff < 20 && $this->isArrestConviction() && !$this->getIsSummaryArrest())
		{
			foreach ($this->getCharges() as $charge)
			{
				$this->cleanSlateEligible['Past20FProhibitedConviction'][] = $charge->checkCleanSlatePast20FProhibitedConviction();
			}
		}
		else
			$this->cleanSlateEligible['Past20FProhibitedConviction'][] = null;

		return $this->cleanSlateEligible['Past20FProhibitedConviction'];
	}

	/**
	* @function checkCleanSlateHasOutstandingFinesCosts
	* @param: none
	* @return: true if there are unpaid costs/fines, false otherwise
	**/
	public function checkCleanSlateHasOutstandingFinesCosts()
	{
		// if we've done this before for this case, just return what we have
		if (isset($this->cleanSlateEligible['HasOutstandingFinesCosts']))
			return $this->cleanSlateEligible['HasOutstandingFinesCosts'];

		// first check  to see if this is a conviction arrest in the last 20
		// years
		if (intval($this->getCostsTotal()) > 0)
			$this->cleanSlateEligible['HasOutstandingFinesCosts'] = TRUE;
		else
			$this->cleanSlateEligible['HasOutstandingFinesCosts'] = FALSE;

		return $this->cleanSlateEligible['HasOutstandingFinesCosts'];
	}


	/**
	* @function checkCleanSlateIsUnsealableOffense
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlateIsUnsealableOffense()
	{
		// if we've done this before for this case, just return what we have
		if (isset($this->cleanSlateEligible['IsUnsealableOffense']))
			return $this->cleanSlateEligible['IsUnsealableOffense'];

		// first check  to see if this is a conviction arrest in the last 20
		// years
		if ($this->isArrestConviction())
		{
			foreach ($this->getCharges() as $charge)
			{
				$this->cleanSlateEligible['IsUnsealableOffense'][] = $charge->checkCleanSlateIsUnsealableOffense();
			}
		}
		else
			$this->cleanSlateEligible['IsUnsealableOffense'][] = null;

		return $this->cleanSlateEligible['IsUnsealableOffense'];
	}


}  // end class arrest

?>
