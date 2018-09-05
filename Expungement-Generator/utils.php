<?php

/************************************************************************
*
*	utils.php
*
*	a collection of utilities used by the rest of the expungement generator
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
************************************************************************/

// checks to see if a person is logged in
// @return TRUE if the person is logged in and false if not
function isLoggedIn()
{
	if(isset($_SESSION['loginUserID']))
		return TRUE;
	else
		return FALSE;
}

// @return if the user is logged in, returns the logged in user; otherwise returns null

function getLoggedInUserName()
{
	return ($_SESSION['loginUserFirst'] . " " . $_SESSION['loginUserLast']);
}

// print a key only if it is set in the "GET" variables
function printIfSet($key)
{
	if(isset($_GET[$key]))
		print $_GET[$key];
    else if (isset($_POST[$key]))
      print $_POST[$key];
}

	// gets a "person" from the post vars; adds the person to the session
    // @return a hash with each value from the get vars escaped etc... to be html and sql safe
function getPersonFromPostOrSession($data = FALSE)
{
        if($data===FALSE)
           $data = $_POST;

	if ($GLOBALS['debug'])
	{
		print "POST VARS: <br />";
		foreach ($_POST as $name=>$value)
		{
			print "$name: $value <br/>";
		}
	}

    if (isset($data["personFirst"]))
  	    $_SESSION['urlPerson']['First'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($data["personFirst"])));
    if (isset($data["personLast"]))
        $_SESSION['urlPerson']['Last'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($data["personLast"])));
    if (isset($data["personDOB"]))
        $_SESSION['urlPerson']['DOB'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($data["personDOB"])));

    if (isset($data["personStreet"]))
        $_SESSION['urlPerson']['Street'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($data["personStreet"])));
    if (isset($data["personStreet"]))
    	$_SESSION['urlPerson']['City'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($data["personCity"])));
    if (isset($data["personStreet"]))
    	$_SESSION['urlPerson']['State'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($data["personState"])));
    if (isset($data["personStreet"]))
    	$_SESSION['urlPerson']['Zip'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($data["personZip"])));
    if (isset($data["personStreet"]))
    	$_SESSION['urlPerson']['SSN'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($data["personSSN"])));

	return $_SESSION['urlPerson'];
}

// there is some informaiton that we want to get from getVars.  The last page of the EG
// where the cases are displayed allows for a person to click on a result and
// output an Act 5 or pardon petition.  The vars used to do that are stored here
function getInfoFromGetVars($data = FALSE)
{
    if($data===FALSE)
      $data = $_POST;

    if (isset($_GET['docket']))
    	$_SESSION['docket'] = explode("|", $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_GET["docket"]))));
    $_SESSION['sealingRegardless'] = isset($_GET['sealingRegardless']) || isset($data['sealingRegardless']);
    $_SESSION['expungeRegardless'] = isset($_GET['expungeRegardless']) || isset($data['expungeRegardless']);
    $_SESSION['zipOnly'] = isset($data['zipOnly']) || isset($_GET['zipOnly']);

}

	// gets vars passed in from LS
// @return a hash with each value from the get vars escaped etc... to be html and sql safe
function getLSVars()
{
	//
	if ($GLOBALS['debug'])
	{
		print "POST VARS: <br />";
		foreach ($_POST as $name=>$value)
		{
			print "$name: $value <br/>";
		}
	}

	$LSVars = array();

	if (isSet($_POST["personFirst"])) $LSVars['First'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personFirst"])));
	if (isSet($_POST["personLast"])) $LSVars['Last'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personLast"])));
	if (isSet($_POST["personStreet"])) $LSVars['Street'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personStreet"])));
	if (isSet($_POST["personCity"])) $LSVars['City'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personCity"])));
	if (isSet($_POST["personState"])) $LSVars['State'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personState"])));
	if (isSet($_POST["personZip"])) $LSVars['Zip'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personZip"])));
	if (isSet($_POST["personSID"])) $LSVars['SID'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personSID"])));
	if (isSet($_POST["personPP"])) $LSVars['PP'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personPP"])));
	if (isSet($_POST["personSSN"])) $LSVars['SSN'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["personSSN"])));
	if (isSet($_POST["personLSUser"])) $LSVars['LSUser'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["LSUser"])));
	if (isSet($_POST["personLSPass"])) $LSVars['LSPass'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["LSPass"])));
	if (isSet($_POST["Token"])) $LSVars['Token'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["Token"])));
	if (isSet($_POST["debug"])) $LSVars['debug'] = $GLOBALS['db']->real_escape_string(htmlspecialchars(stripslashes($_POST["debug"])));

	return $LSVars;
}


// resets all of the EG based session vars except login info
function resetSession()
{
    unset($_SESSION['urlPerson']);
    unset($_SESSION['docket']);
    unset($_SESSION['scrapedDockets']);
}

// print index if exists
function printVar($array, $index)
{
	if (isSet($array["$index"]))
		print $array["$index"];
}

// zips all of the files in the array and returns the location of the zipfile.
// @return the name of the zipfile archive or null if there was a problem making the zipfile.
function zipFiles($files, $dataDir, $dockets, $fileName)
{
	$zip = new ZipArchive();
	$zipFileName = $dataDir . $fileName . ".zip";

	if ($zip->open($zipFileName, ZipArchive::OVERWRITE|ZipArchive::CREATE)===TRUE )
	{
		foreach ($files as $index=>$file)
		{
			if($zip->addFile($file, basename($file)) && $GLOBALS['debug'])
				print "added $file to archive <br />";
		}

        foreach ($dockets['userFile']['tmp_name'] as $key=>$docket)
        {
            $zip->addFile($docket, "dockets" . DIRECTORY_SEPARATOR . $dockets['userFile']['name'][$key]);
        }

		if ($zip->close())
			return $zipFileName;
	}

	// if we couldn't open the zip file or save the zip file, return null
	return NULL;
}


// removes all of the files in $files from the OS
function cleanupFiles($files)
{
	foreach ($files as $file)
	{
		if (file_exists($file))
		{
			try
			{
				unlink($file);
			}
			catch (Exception $e) {}
		}
	}
}

// calculates date1-date2 in years
function dateDifference($date1, $date2)
{
	$difference = ((int)$date1->format('Y')) - ((int)$date2->format('Y'));
	// now check to see if date2 is later in the year than date1
	// (z = number of days since jan 1)
	if (((int)$date1->format('z')) > ((int)$date2->format('z')))
		return $difference;
	else
		return $difference-1;
}

// @return a date in the form YYYY-MM-DD
// @param a date in the form MM/DD/YYYY
function dateConvert($docketDate)
{
	if (preg_match("/\d{1,2}\/\d{1,2}\/\d{2,4}/",$docketDate))
	{
		$mysqlDate = new DateTime($docketDate);
		return $mysqlDate->format('Y-m-d');
	}
	else
		return ("0000-00-00");
}

// @return bool True if a file with the case id passed in exists in the pdf file dir, false if not
// @param id - the ID of the expungement that we are looking for a PDF for
function doesPDFExistForCaseId($id)
{
	$filename = $GLOBALS['docketSheetsDir']	. $id;
	if(file_exists($filename))
		return TRUE;
	else
		return FALSE;
}

// @return serves a binary file from the data directory
// @param filename - the relative filename
function serveFile($filename)
{

    # run basename on filename to make sure we aren't getting any directory input, like ../config.php or something
    $file = $GLOBALS['dataDir'] . basename($filename);

    if(!file_exists($file))
    {
        header ("HTTP/1.0 404 Not Found");
        return;
    }

    else
    {
        $size=filesize($file);
        header('HTTP/1.0 200 OK');
        header('Content-Description: File Transfer');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Accept-Ranges: bytes');
        header('Content-Length:' . $size);
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=" . basename($file));
        header("Content-Transfer-Encoding: binary");
        ob_clean();
        flush();
        readfile($file);
    }
}

// takes a list of names in the form "('First', 'Last'), ('First', 'Last'),..." and runs a query to get all
// of the expungements prepared for those people
function createTrackingSpreadsheet($names, $programid)
{
    // remove newlines from teh name list
    $namelist = preg_split('/\r\n|[\r\n]/', $names);

    // create a temporary file and open it; insert the headers for teh CSV
    $filename = $GLOBALS['dataDir'] . time() . ".csv";
    $csv = fopen($filename, 'w');
    fputcsv($csv, array("First", "Last", "Case", "OTN", "Arrest Date", "E/R", "Order", "PSP", "Philly", "Docket", "Summary"));


    // prepare the sql statement; this will be run through for each name.
    if ($psql = $GLOBALS['db']->prepare("
            SELECT  d.firstname as 'First', d.lastname as 'Last', a.docketNumPrimary as 'Case', a.OTN as 'OTN',
             DATE_FORMAT(a.arrestDate, '%m/%d/%Y') as 'Arrest Date',
             if(e.isExpungement+e.isRedaction = 2, 'E', 'R') as 'E/R' FROM defendant as d left join
             expungement as e on d.defendantID = e.defendantID left join arrest as a on e.arrestid = a.arrestid
             LEFT JOIN userinfo on e.userid=userinfo.userid WHERE d.firstname=? AND d.lastname=?
             AND e.isRedaction+e.isSummaryExpungement > 0 AND programid=" . $programid . "
             ORDER BY d.lastName, d.firstname"))
    {

        // bind the first and lst name as the variables that will be inserted
        $psql->bind_param("ss", $first, $last);

        // run through each of the names in the list and run the query on each
        foreach ($namelist as $name)
        {
            // be sure to first split the name into first and last, removing any whitespace
            list($first, $last) = array_map('trim', explode(",", $name));
            $psql->execute();
            $psql->bind_result($f, $l, $docket, $otn, $arrest_date, $type);

            // fetch each result for this person and put it into the CSV file
            while($psql->fetch())
            {
                fputcsv($csv, array($f,$l,$docket,$otn,$arrest_date,$type,"","","","",""));
            }
        }

        fclose($csv);
        return $filename;
    }


    // if there was some sort of problem preparing the statement, send an error to the screen
    else
        return "aasdf" . $GLOBALS['db']->error;



}
