<?php

/*****************************************
*
*	index.php
*	The head page for the expungement generator
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
*********************************************/

//	require_once("config.php");
//	require_once("utils.php");
require_once("CPCMS.php");
include("head.php");

// the page header, including the menu bar, etc...
//	include("header.php");

$submit = false;

// if this is from a submit, grab the post vars and place them into the form fields
// also prepare to do the CPCMS search
$first = "";
$last = "";
$dob = "";
if (!empty($_POST))
{
    $submit = true;
    $first = trim(htmlspecialchars(stripslashes($_POST['personFirst'])));
    $last = trim(htmlspecialchars(stripslashes($_POST['personLast'])));
    $dob = trim(htmlspecialchars(stripslashes($_POST['personDOB'])));
}
?>

<div class="main">
	<div class="pure-u-8-24">&nbsp;</div>
	<div class="pure-u-12-24">
		<form action="lookup.php" method="post" enctype="multipart/form-data">
		<div class="form-item">
			<label for="personFirst">Client's Name</label> <!--'-->
			<div class="form-item-column">
				<input type="text" name="personFirst" id="personFirst" class="form-text" value="<?php echo $first?> " />
			</div>
			<div class="form-item-column">
				<input type="text" name="personLast" id="personLast" class="form-text" value="<?php echo $last?>" />
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
		</div>
		<div class="form-item">
		<label for="personDOB">Date of Birth</label>
			<input type="date" name="personDOB" id='date' value="<?php echo $dob?>" maxlength="10"/>
			<div class="description">MM/DD/YYYY</div>
		</div>

    	<div class="form-item">
			<input type="submit" name="submit" value="Search CPCMS" />
		</div>
		</form>
<?php
// do the CPCMS search and print the results
if ($submit)
{
    $cpcms = new cpcms($first, $last, $dob);
    $status = $cpcms->cpcmsSearch();
    if ((count($cpcms->getResults()) == 0)) {
        print "<br/><b>Your search returned no results.  This is probably because there is no one with the name $first $last in the court database.  You can try searching <a href='http://ujsportal.pacourts.us' target='_blank'>CPCMS directly</a> if you think that there is some error.";
    } else {
        //only integrate the summary information if we have a DOB; otherwise what is the point?
        if (!empty($dob))
            $cpcms->integrateSummaryInformation();

        // display a small form that will send a quick email to legalserver with the docket numbers
        // and links to all of the dockets
        print "
        		<form action='mailDockets.php' target='_blank' method='post' enctype='multipart/form-data'>
                    <div class='form-item'>
                        <label for='email'>Legal Server Case #</label>
                        <input type='text' name='lsNumber' id='lsnumber' value='' maxlength='10'/>
               ";

        // include all of the docket urls as hidden inputs,
        // including the best docket
        // post these to mailDockets.php to mail them.
        $bestdocket = $cpcms->findBestSummaryDocketNumber();
        $dockets = array_merge($cpcms->getResults(), $cpcms->getMDJResults());
        print (
            "<input type='hidden' name='bestDocket' value='" .
            $bestdocket . "' />");
        foreach ($dockets as $docket)
        {
            print "<input type='hidden' name='dockets[]' value='" . $docket["docket_sheet_url"] . "' />";
        }

        print "
            			<input type='submit' name='submit' value='Email Results to Legal Server' />
                    </div>
                </form>
        ";
        $cpcms->displayResultsInTable(basename(__FILE__), false);
    }
}
?>
	</div>
	<div class="content-right">&nbsp;</div>
</div>

<?php
	include("foot.php");
?>
