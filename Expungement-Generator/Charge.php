<?php

/*************************************************
*
*	Charge.php
*	The class that describes a criminal charge and has helper functions for detarmining redactibility.
*
*	Copyright 2011-2015 Community Legal Services
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
 * TODO: Edga case to consider: CP-26-CR-0001501-2015: all charges are dismissed or held for court
 * so it is an expungement, but it appears as a sealable redaction, which is wrong.
*
**************************************************************************/


class Charge
{
	private $chargeName;
	private $disposition;
	private $codeSection;
	private $dispDate;
	private $isRedactable;
	private $isSummaryRedactable;
    private $isSealable;
    private $sealableMessage;
	private $isARD;
	private $grade;

	private static $cleanSlateExcludedOffenses = array("3127", //indecent exposure
														 "3129", //sexual intercourse with animals
														 "4915.1", // failure to comply with reg requirements
														 "4915.2", // failure to comply with reg requirements
														 "5122", //weapons or implements for escape
														 "5510", // abuse of corpse
														 "5515"); // probhibiting paramilitary training

	// these are the offenses defined in 42 PaCS 9799.14 and 9799.55 (there is significant overlap)
	private static $cleanSlateTieredSexOffenses = array("2901a.1", "2902b",
					"2903b", "2904", "2910b", "3011b", "3121", "3122.1b", "3123",
					"3124.1", "3124.2a", "3124.2a.1", "3124.2a2", "3124.2a3",
					"3125", "3126a1", "3126a2", "3126a3", "3126a4", "3126a5",
					"3126a6", "3126a7", "3126a8", "4302b", "5902b", "5902b.1",
					"5903a3ii", "5903a4ii", "5903a5ii", "5903a6", "6301a1ii",
					"6312", "6318", "6320", "7507.1");

	public function __construct($chargeName, $disposition, $codeSection, $dispDate, $grade)
	{
		$this->setChargeName($chargeName);
		$this->setDisposition($disposition);
		$this->setCodeSection($codeSection);
		$this->setDispDate($dispDate);
		$this->setGrade($grade);
	}

	public function setChargeName($chargeName) { $this->chargeName=$chargeName; }
	public function setDisposition($disposition) { $this->disposition=$disposition; }
	public function setCodeSection($codeSection) { $this->codeSection=$codeSection; }
	public function setDispDate($dispDate) { $this->dispDate=$dispDate; }
	public function setIsRedactable($isRedactable) { $this->isRedactable=$isRedactable; }
	public function setIsSummaryRedactable($isRedactable) { $this->isSummaryRedactable=$isRedactable; }
	public function setIsSealable($isSealable) { $this->isSealable=$isSealable; }
	public function setIsARD($isARD) { $this->isARD=$isARD; }
	public function setGrade($grade) { $this->grade=$grade; }

	public function getChargeName() { return $this->chargeName; }
	public function getDisposition() { return $this->disposition; }
	public function getCodeSection() { return $this->codeSection; }
	public function getDispDate() { return $this->dispDate; }
	public function getIsRedactable() { return $this->isRedactable; }
	public function getIsSummaryRedactable() { return $this->isSummaryRedactable; }
	public function getIsSealable() { return $this->isSealable; }
    public function getSealablePercent() { return $this->sealablePercent; }
	public function getSealableMessage() { return $this->sealableMessage; }
	public function getIsARD() { return $this->isARD; }
	public function getGrade() { if (!isset($this->grade) || $this->grade == "") $this->setGrade("unk"); return $this->grade; }

	public function isRedactable()
	{

		if (isset($this->isRedactable)) { return $this->getIsRedactable(); }
		$disp = $this->getDisposition();

		// "waived for court" appears on some MDJ cases. It means the same as held for court.
		$nonRedactableDisps = array("Guilty" , "Guilty - Rule 1002", "Guilty Plea", "Guilty Plea - Negotiated", "Guilty Plea - Non-Negotiated", "Guilty Plea (Lower Court)", "Guilty by Trial (Lower Court)", "Held for Court", "Waived for Court", "Waived for Court (Lower Court)", "Held for Court (Lower Court)", "Nolo Contendere", "Found in Contempt");
		if (in_array($disp, $nonRedactableDisps))
			$this->setIsRedactable(FALSE);
		else
			$this->setIsRedactable(TRUE);

		return $this->getIsRedactable();
	}

	public function isSummaryRedactable()
	{
		if (isset($this->isSummaryRedactable)) { return $this->getIsSummaryRedactable(); }
		$disp = $this->getDisposition();

		$redactableDisps = array("Guilty" , "Guilty - Rule 1002", "Guilty Plea", "Guilty Plea - Negotiated", "Guilty Plea - Non-Negotiated", "Guilty Plea (Lower Court)", "Nolo Contendere", "Found in Contempt");
		if (in_array($disp, $redactableDisps))
			$this->setIsSummaryRedactable(TRUE);
		else
			$this->setIsSummaryRedactable(FALSE);
		return $this->getIsSummaryRedactable();
	}

    public function isConviction()
    {
        if (in_array($this->getDisposition(), array("Guilty" , "Guilty - Rule 1002", "Guilty Plea", "Guilty Plea - Negotiated", "Guilty Plea - Non-Negotiated", "Guilty Plea (Lower Court)", "Nolo Contendere", "Found in Contempt")))
            return true;
        else
            return false;

    }

    public function isHeldForCourt()
    {
        if (in_array($this->getDisposition(), array("Held for Court", "Waived for Court", "Waived for Court (Lower Court)", "Held for Court (Lower Court)")))
          return true;
        else
          return false;
    }

	// queries the database to see what percentage of time this charge is
	// of $gradeList %; gradeList shoudl be in format ('F1', 'F2', 'F3', 'F', 'M1')
	public function getCodeSectionGradePercent($gradeList)
	{
		// look in the database to find this particular code section and see whetherh or not
		// it is potential and F1
		$codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);
		// this means that therew as a problem splitting the codeSection, so return 2
		if (count($codeSection) == 1)
			return 'Unknown/unreadable code section';

		if (count($codeSection) > 2)
			$sql = "SELECT SUM(Percent_w_Subsection) as P FROM crimes_w_subsection WHERE GRADE in $gradeList AND title='".trim($codeSection[0])."' AND section='".trim($codeSection[1])."' AND subsection like '".trim($codeSection[2])."%'";
		else
			$sql = "SELECT SUM(Percent) as P FROM crimes_wo_subsection WHERE Grade in $gradeList AND title='".trim($codeSection[0])."' AND section='".trim($codeSection[1])."'";

		$result = $GLOBALS['chargeDB']->query($sql);
		if (!$result)
			return 'There was a problem querying the DB for this charge';

		// return the result as a percentage of charges that were F1
		else
		{
			$p = mysqli_fetch_assoc($result);
			if (!empty($p['P'])) // if p is empty, then there are no cases that fit the query above
				return round($p['P'],1);
		}
	}

	public function isARD()
	{
		if (isset($this->isARD)) { return $this->getIsARD(); }
		$disp = $this->getDisposition();
		$ardRedactableDisps = array("ARD" , "ARD - County", "ARD - County Open");
		if (in_array($disp, $ardRedactableDisps))
			$this->setIsARD(TRUE);
		else
			$this->setIsARD(FALSE);
		return $this->getIsARD();
	}

	/**
	* Checks if the current charge is either murder or a possible F1
	* and returns an array in the format charge:message
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlateMurderFelony()
	{
		// if this charge is somehow redactable or if it isn't a conviction
		// then return null right away.
		if ($this->isRedactable() || !$this->isConviction())
			return null;

		// get the code section of this case
        $codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return 2
        if (count($codeSection) == 1)
          	return array($this->getCodeSection(), 'Unknown/unreadable code section; assuming the worse: possibly murder or an F1');

  		// 18 PaCS 2502 is murder, so this checks to see if we are dealing with a murder conviction
        if (trim($codeSection[0])=="18" && trim($codeSection[1])=="2502")
			return array($this->getCodeSection(), 'Murder Conviction');

		// finally, check if this is either an F1 or may be an F1
		if ($this->getGrade()=="F1")
			return array($this->getCodeSection(), 'F1 Conviction');

		$percent = $this->getCodeSectionGradePercent("('F1')");
		if ($this->getGrade()=="unk" && $percent > 0)
			return array($this->getCodeSection(), $percent . "% of charges like this were F1s.");

		// if we got to here, then the offense isn't F1 or Murder and we can return null
		return null;

	}

	/**
	* Checks if the current charge is a conviction for a 15 year prohibited offense
	* and returns an array in the format charge:message
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast15ProhibitedConviction()
	{
		// for now assume that this happened in the last 15 years, although
		// maybe we want to change this?

		// if this charge is somehow redactable or if it isn't a conviction
		// then return null right away.
		if ($this->isRedactable() || !$this->isConviction())
			return null;

		// get the code section of this case
        $codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return 2
        if (count($codeSection) == 1)
          	return array($this->getCodeSection(), 'Unknown/unreadable code section; assuming the worse: possibly a 15 year excluded offense');

		// if this is title 18 and we are in the list of excludable offenses, return 0
        elseif ((trim($codeSection[0])=="18") && in_array(trim($codeSection[1]), Charge::$cleanSlateExcludedOffenses))
        {
			return array($this->getCodeSection(), '15 Year Prohibited Offense under 9122.1(b)(2)(iii)(B)');
        }

		// if we got to here, then the offense isn't F1 or Murder and we can return null
		return null;

	}

	/**
	* Checks if the current charge is a conviction for an M1, or any F
	* and returns an array in the format charge:message
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast15MoreThanOneM1F()
	{
		// for now assume that this happened in the last 15 years, although
		// maybe we want to change this?

		// if this charge is somehow redactable or if it isn't a conviction
		// then return null right away.
		if ($this->isRedactable() || !$this->isConviction())
			return null;

		// first check the grade of the offense
		$grade = $this->getGrade();
		if (in_array($grade, array("F1", "F2", "F3", "F", "M1")))
			return array($this->getCodeSection(), "'" . $grade . "' conviction within the past 15 years.");

		$percent = $this->getCodeSectionGradePercent("('F1', 'F2', 'F3', 'F', 'M1')");
		if ($this->getGrade()=="unk" && $percent > 0)
			return array($this->getCodeSection(), $percent . "% of charges like this were M1s or Felonies.");

		// if we got to here, then the offense isn't F1 or Murder and we can return null
		return null;

	}


	/**
	* Checks if the current charge is a conviction for an M2, M1, or any F
	* and returns an array in the format charge:message
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast20MoreThanThreeM2M1F()
	{
		// for now assume that this happened in the last 20 years, although
		// maybe we want to change this?

		// if this charge is somehow redactable or if it isn't a conviction
		// then return null right away.
		if ($this->isRedactable() || !$this->isConviction())
			return null;

		// first check the grade of the offense
		$grade = $this->getGrade();
		if (in_array($grade, array("F1", "F2", "F3", "F", "M1", "M2")))
			return array($this->getCodeSection(), "'" . $grade . "' conviction within the past 20 years.");

		$percent = $this->getCodeSectionGradePercent("('F1', 'F2', 'F3', 'F', 'M1', 'M2')");
		if ($this->getGrade()=="unk" && $percent > 0)
			return array($this->getCodeSection(), $percent . "% of charges like this were M2s, M1s, or felonies.");

		// if we got to here, then the offense isn't F1 or Murder and we can return null
		return null;

	}

	/**
	* Checks if the current charge is a conviction for a 20 year prohibited offense
	* and returns an array in the format charge:message
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlatePast20FProhibitedConviction()
	{
		// for now assume that this happened in the last 15 years, although
		// maybe we want to change this?

		// if this charge is somehow redactable or if it isn't a conviction
		// then return null right away.
		if ($this->isRedactable() || !$this->isConviction())
			return null;

		// check the grade, if known. If it isn't an F (or isn't unknwon),
		// then we don't care about the conviction.
		$grade = $this->getGrade();
		if (!in_array($grade, array("F1", "F2", "F3", "F", "unk")))
			return null;

		$percent = $this->getCodeSectionGradePercent("('F1', 'F2', 'F3', 'F')");
		if ($percent==0)
			return null;

		// get the code section of this case
		$codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return 2
		if (count($codeSection) == 1)
			return array($this->getCodeSection(), 'Unknown/unreadable code section; assuming the worse: possibly a 20 year excluded offense');

		// if this is an Article B, D, etc.. crime, add it
		if ($this->isArticleBCrime())
		{
			if ($this->getGrade()=="unk")
				return array($this->getCodeSection(), 'Article B offense; ' . $percent . "% of the time this is a Felony.");
			else
				return array($this->getCodeSection(), "Article B offense with grade of '" . $grade . "'.");
		}

		if ($this->isArticleDCrime())
		{
			if ($this->getGrade()=="unk")
				return array($this->getCodeSection(), 'Article D offense; ' . $percent . "% of the time this is a Felony.");
			else
				return array($this->getCodeSection(), "Article D offense with grade of '" . $grade . "'.");
		}

		if ($this->isChapter61Crime())
		{
			if ($this->getGrade()=="unk")
				return array($this->getCodeSection(), 'Chapter 61 offense; ' . $percent . "% of the time this is a Felony.");
			else
				return array($this->getCodeSection(), "Chapter 61 offense with grade of '" . $grade . "'.");
		}

		if ($this->isSexRegCrime())
		{
			if ($this->getGrade()=="unk")
				return array($this->getCodeSection(), 'Sec 9799.14/.55 sexual registration offense; ' . $percent . "% of the time this is a Felony.");
			else
				return array($this->getCodeSection(), "Sec 9799.14/.55 sexual registration offense with grade of '" . $grade . "'.");
		}

		// if we got to here, then the offense isn't excluded
		return null;
	}



	/**
	* Checks if the current charge is a conviction for an unsealable crime
	* and returns an array in the format charge:message
	* @param: none
	* @return: an associative array in the format charge: message
	**/
	public function checkCleanSlateIsUnsealableOffense()
	{
		// for now assume that this happened in the last 15 years, although
		// maybe we want to change this?

		// if this charge is somehow redactable or if it isn't a conviction
		// then return null right away.
		if ($this->isRedactable() || !$this->isConviction())
			return null;

		// check the grade, if known. If it isn't an F, an M1 (or isn't unknwon),
		// then we don't care about the conviction. It is sealable.
		$grade = $this->getGrade();

		// first check to see if this is a felony, which would make the case
		// unsealable.
		if (in_array($grade, array("F1","F2","F3","F")))
		{
			$this->isSealable = FALSE;
			$this->sealableMessage = "Unsealable. $grade conviction.";
			return array($this->getCodeSection(), $this->sealableMessage);
		}

		if (!in_array($grade, array("F1", "F2", "F3", "F", "M1", "unk")))
		{
			$this->isSealable = TRUE;
			$this->sealableMessage = "Definitely Sealable.";
			return null;
		}

		// same as above--never an F/M1, so it is sealable.
		$percent = $this->getCodeSectionGradePercent("('F1', 'F2', 'F3', 'F', 'M1')");
		if ($percent==0)
		{
			$this->isSealable = TRUE;
			$this->sealableMessage = "Sealable. Grade is M2 or less serious.";
			return null;
		}

		// get the code section of this case
		$codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return 2
		if (count($codeSection) == 1)
		{
			$this->sealableMessage = 'Maybe sealable. Unknown/unreadable code section so assuming the worst.';
			$this->isSealable = FALSE;
			return array($this->getCodeSection(), $this->sealableMessage);
		}
		// if this is an Article B, D, etc.. crime, add it
		if ($this->isArticleBCrime())
		{
			if ($this->getGrade()=="unk" && $percent==100)
				$this->sealableMessage = 'Not sealable. Article B offense; ' . $percent . "% of the time this is an F or M1.";
			else if ($this->getGrade()=="unk")
				$this->sealableMessage = 'Maybe sealable. Article B offense; ' . $percent . "% of the time this is an F or M1.";
			else
				$this->sealableMessage = "Not sealable. Article B offense with grade of '" . $grade . "'.";

			$this->isSealable = FALSE;
			return array($this->getCodeSection(), $this->sealableMessage);
		}

		if ($this->isArticleDCrime())
		{
			if ($this->getGrade()=="unk" && $percent==100)
				$this->sealableMessage =  'Not sealable. Article D offense; ' . $percent . "% of the time this is an F or M1.";
			else if ($this->getGrade()=="unk")
				$this->sealableMessage =  'Maybe sealable. Article D offense; ' . $percent . "% of the time this is an F or M1.";
			else
				$this->sealableMessage = "Not sealable. Article D offense with grade of '" . $grade . "'.";

			$this->isSealable = FALSE;
			return array($this->getCodeSection(), $this->sealableMessage);
		}

		if ($this->isChapter61Crime())
		{
			if ($this->getGrade()=="unk" && $percent==100)
				$this->sealableMessage = 'Not sealable. Chapter 61 offense; ' . $percent . "% of the time this is an F or M1.";
			else if ($this->getGrade()=="unk")
				$this->sealableMessage = 'Maybe sealable. Chapter 61 offense; ' . $percent . "% of the time this is an F or M1.";
			else
				$this->sealableMessage = "Not sealable. Chapter 61 offense with grade of '" . $grade . "'.";

			$this->isSealable = FALSE;
			return array($this->getCodeSection(), $this->sealableMessage);
		}

		if ($this->isSexRegCrime())
		{
			if ($this->getGrade()=="unk" && $percent==100)
				$this->sealableMessage = 'Not sealable. Sec 9799.14/.55 sexual registration offense; ' . $percent . "% of the time this is an F or M1.";
			else if ($this->getGrade()=="unk")
				$this->sealableMessage = 'Maybe sealable. Sec 9799.14/.55 sexual registration offense; ' . $percent . "% of the time this is an F or M1.";
			else
				$this->sealableMessage = "Not sealable. Sec 9799.14/.55 sexual registration offense with grade of '" . $grade . "'.";

			$this->isSealable = FALSE;
			return array($this->getCodeSection(), $this->sealableMessage);
		}

		if ($this->isCorruptionOfMinorsCrime())
		{
			if ($this->getGrade()=="unk" && $percent==100)
				$this->sealableMessage = 'Not sealable. Conviction for 18 PaCS 6301a1; ' . $percent . "% of the time this is an F or M1.";
			if ($this->getGrade()=="unk")
				$this->sealableMessage = 'Maybe sealable. Conviction for 18 PaCS 6301a1; ' . $percent . "% of the time this is an F or M1.";
			else
				$this->sealableMessage = "Not sealable. Conviction for 18 PaCS 6301a1 with grade of '" . $grade . "'.";

			$this->isSealable = FALSE;
			return array($this->getCodeSection(), $this->sealableMessage);
		}

		// This isn't specifically unsealable.  If it is an M1, it is sealable
		// because it isn't a B or D offense (or the other excluded offenses)
		if ($grade=="M1")
		{
			$this->isSealable=TRUE;
			$this->sealableMessage = "Definitely Sealable. M1, but not offense from Article B, D etc...";
			return null;
		}

		// if it wasn't specifically unsealable, check to see if this is potentially
		// a felony.
		$percent = $this->getCodeSectionGradePercent("('F1', 'F2', 'F3', 'F')");
		if ($percent==100)
		{
			$this->isSealable = FALSE;
			$this->sealableMessage = "Not sealable. " . $percent . "% of the time this is an F.";
			return array($this->getCodeSection(), $this->sealableMessage);
		}
		else if ($percent>0)
		{
			$this->isSealable = FALSE;
			$this->sealableMessage = "Maybe sealable. " . $percent . "% of the time this is an F.";
			return array($this->getCodeSection(), $this->sealableMessage);
		}

		// if we got to here, then the offense isn't excluded
		$this->isSealable = TRUE;
		$this->sealableMessage = "Sealable.";
		return null;
	}



	public function isArticleBCrime()
	{
		$codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return false
		if (count($codeSection) == 1)
			return FALSE;

		$section = intval(trim($codeSection[1]));
		if (trim($codeSection[0])=="18" && $section > 2300 && $section < 3300)
			return TRUE;

		return FALSE;
	}

	public function isArticleDCrime()
	{
		$codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return false
		if (count($codeSection) == 1)
			return FALSE;

		$section = intval(trim($codeSection[1]));
		if (trim($codeSection[0])=="18" && $section > 4300 && $section < 4500)
			return TRUE;

		return FALSE;

	}

	public function isChapter61Crime()
	{
		$codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return false
		if (count($codeSection) == 1)
			return FALSE;

		$section = intval(trim($codeSection[1]));
		if (trim($codeSection[0])=="18" && $section > 6100 && $section < 6200)
			return TRUE;

		return FALSE;
	}

	public function isSexRegCrime()
	{
		$codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return false
		if (count($codeSection) == 1)
			return FALSE;


		// if the code section is 18 PaCS 2192(a)
		// section will be 2192a
		$section =trim($codeSection[1]);
		if (count($codeSection)==3)
			$section .= trim($codeSection[2]);

		if (trim($codeSection[0])=="18" && in_array($section, Charge::$cleanSlateTieredSexOffenses))
			return TRUE;
		else
			return FALSE;

	}

	public function isCorruptionOfMinorsCrime()
	{
		$codeSection = preg_split("/\x{A7}+/u", $this->getCodeSection(), -1, PREG_SPLIT_NO_EMPTY);

		// this means that therew as a problem splitting the codeSection, so return false
		if (count($codeSection) < 3)
			return FALSE;

		// only return tru eif this matches 18 PaCS 6301(a)(1)
		if(trim($codeSection[0])=="18" && trim($codeSection[1])=="6301" && trim($codeSection[2])=="a1")
			return TRUE;
		else
			return FALSE;
	}
}
?>
