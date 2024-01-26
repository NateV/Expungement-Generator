<?php

/************************************************
*
*	manage.php
*	Contains the main page to manage creating and editting users
*
**	Copyright 2011-2015 Community Legal Services
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
***************************************************/
	
	require_once("config.php");
	require_once("Attorney.php");
	require_once("utils.php");
	
	include('head.php');
	include('header.php');
?>

<div class="main">
	<div class="content-left">&nbsp;
		<?php include("messagedisplay.php"); ?>
	</div>
	<div class="content-center">

<?php
// if the user isn't logged in, then don't display this page.  Tell them they need to log in.
if (!isLoggedIn())
	include("displayNotLoggedIn.php");
else
{
	$attorney = new Attorney($_SESSION["loginUserID"], $db);
	if($GLOBALS['debug'])
		$attorney->printAttorneyInfo();

	// only certain users can see this page
	if ($attorney->getUserLevel() != 1)
		print "You must have permission to view this page.";

	else
	{	
		// create a new user?
		displayCreateUser();
        displayCreateProgram();
	
		displayAllUsers();
        
        print "<br/>&nbsp;<br /><B>Programs</b><br />&nbsp;<br/>";
        displayAllOrgs();
	} 	
}
?>	
	</div> <!-- content-center -->
	<div class="content-right"><?php // include right column? ?></div>
	</div>
<?php
	include ('foot.php');

function displayCreateUser()
{
?>
	<div class="guideStep guideStepCounter">Create New User</div>
	<div class="guideStepDesc">
		<form action="manage.php" method="post">
		<div class="form-item">
			<label for="createFirst">New User's Name</label>
			<div class="form-item-column">
				<input type="text" name="createFirst" id="createFirst" class="form-text" value="" /> 
			</div>
			<div class="form-item-column">
				<input type="text" name="createLast" id="createLast" class="form-text" value="" />
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
			<label for="createEmail">Email Address</label>
			<input type="text" name="createEmail" id="createEmail" class="form-text" value=""/> 
			<div class="description">The email address of the user.</div>
		</div> 
		<div class="form-item">
			<label for="createBarID">PA Bar ID</label>
			<input type="text" name="createBarID" id="createBarID" class="form-text" value=""/> 
			<div class="description">The email address of the user.</div>
		</div> 

		<div class="form-item">
			<label for="password">Password</label>
			<input type="password" name="createPassword" id="createPassword" class="form-text" />
		</div>
		<div class="form-item">
			<label for="retypePassword">Retype Password</label>
			<input type="password" name="createRetypePassword" id="createRetypePassword" class="form-text" />
		</div>
		
		<div class="form-item">
			<label for="createHeader">Header text for expungement petitions</label>
			<textarea name="createHeader" id="createHeader" class="form-text form-text-area-big">ORGANIZATION
BY: <first> <last>
Identification No.: <barid> 

<address> 
<phone></textarea>
			<div class="description">This will appear at the top of your petition, above the caption.</div>
		</div>
		<div class="form-item">
			<label for="createSignature">Expungement petition signature</label>
			<textarea name="createSignature" id="createSignature" class="form-text form-text-area-small"><Name>, Esquire</textarea>
			<div class="description">The signature line on your petition</div>
		</div>
		
		<div class="form-item">
			<label for="createProgram">Program</label>
			<select name="createProgram" id="createProgram" class="">
<?php
	// get all of the programs from the database and list them "
	$sql = "SELECT * from program";
	$result = $GLOBALS['db']->query($sql);
	if (!$result) 
	{
		if ($GLOBALS['debug'])
			die('Could not get the Expungement Information from the DB:' . $GLOBALS['db']->error);
		else
			die('Could not get the Expungement Information from the DB');
	}
	while ($row = $result->fetch_assoc())
		print "<option value='{$row['programID']}'>{$row['programName']}</option>";
	$result->close();
?>
			</select>
		</div>
		
		<div class="form-item">
			<input type="hidden" name="create" value="1" />
			<input type="submit" value="Create User" />
		</div>
		</form>
	</div>
<?php
}


function displayCreateProgram()
{
    
    $apiKey = bin2hex(openssl_random_pseudo_bytes(32));
?>
	<div class="guideStep guideStepCounter">Create New Program</div>
	<div class="guideStepDesc">
		<form action="manage.php" method="post">
		<div class="form-item">
			<label for="createProgramName">New Program's Name</label>
			<div class="form-item-column">
				<input type="text" name="createProgramName" id="createProgramName" class="form-text" value="" /> 
			</div>
			<div class="space-line"></div>
		</div> 
		<div class="form-item">
			<label for="createProgramIFP">IFP Status</label>
			<select name="createProgramIFP" id="createProgramIFP" class="form-text" >
                <option value="0">0</option>
                <option value="1" selected>1</option>
                <option value="2">2</option>
            </select>
		</div> 
		<div class="form-item">
			<label for="createProgramWriteToDB">Write Expungement Info to the Database for this Program?</label>
			<select name="createProgramWriteToDB" id="createProgramWriteToDB" class="form-text" >
                <option value="0">No</option>
                <option value="1" selected>Yes</option>
            </select>
		</div> 
		<div class="form-item">
			<label for="createProgramIFPLanguage">IFP Language</label>
			<textarea name="createProgramIFPLanguage" id="createProgramIFPLanguage" class="form-text form-text-area-big">_____  is a non-profit legal services organization that provides free legal assistance to low-income individuals. I, attorney for the petitioner, certify that petitioner meets the financial eligibility standards for representation by ________ and that I am providing free legal service to petitioner.</textarea>
			<div class="description">This will only be used if IFP is not 0.</div>
		</div>
		<div class="form-item">
			<label for="createProgramAPIKey">API Key</label>
			<input type="text" name="createProgramAPIKey" id="createProgramAPIKey" style="width: 550px;" class="form-text" value="<?=$apiKey?>"/>
		</div>
		
		<div class="form-item">
			<input type="hidden" name="createProgram" value="1" />
			<input type="submit" value="Create Program" />
		</div>
		</form>
	</div>
<?php
}


// nothing more than the code to display all users in a nice table	
function displayAllUsers() 
{
	// first, do a query of all expungements
	$query = "SELECT user.userid as userid, user.email as email, userinfo.firstName as firstName, userinfo.lastName as lastName, userinfo.userlevel as userLevel, program.programName as programName, program.ifp as ifp, userinfo.pabarid as pabarid, userinfo.totalPetitions as ptotal FROM user, userinfo, program WHERE user.userid=userinfo.userid AND program.programid=userinfo.programid";
	
	$result = $GLOBALS['db']->query($query);
	if (!$result) 
	{
		if ($GLOBALS['debug'])
			die('Could not get the user information from the DB:' . $GLOBALS['db']->error);
		else
			die('Could not get the user information from the DB');
	}
	
	print <<<END
	<table>
	<tr><th>Name</th><th>email</th><th>Userlevel</th><th>Program</th><th>Is IFP?</th><th>Bar ID</th><th>Petitions Prepped</th></tr>
END;
	while ($row = $result->fetch_assoc())
	{
		print "<tr>";
		print "<td><a href='editAttorney.php?id={$row['userid']}'>{$row['firstName']} {$row['lastName']}</a></td>";
		print "<td>{$row['email']}</td>";
		print "<td>{$row['userLevel']}</td>";
		print "<td>{$row['programName']}</td>";
		if ($row['ifp'])
			print "<td>Yes</td>";
		else
			print "<td>No</td>";
		print "<td>{$row['pabarid']}</td>";
		print "<td>{$row['ptotal']}</td>";
		print "</tr>";
	}
	$result->close();
    print "</table>";

}

function displayAllOrgs()
{
	// first, do a query of all orgs
	$query = "SELECT programid, programName, ifp, ifpLanguage FROM program ORDER BY programName";
	
    
    $result = $GLOBALS['db']->query($query);
	if (!$result) 
	{
		if ($GLOBALS['debug'])
			die('Could not get the program information from the DB:' . $GLOBALS['db']->error);
		else
			die('Could not get the program information from the DB');
	}
	
	print <<<END
	<table>
	<tr><th>Program Name</th><th>IFP Status</th><th>IFP Language</th></tr>
END;
    // now print the results 
	while ($row = $result->fetch_assoc())
	{
		print "<tr>";
        print "<td><a href='editProgram.php?id={$row['programid']}'>{$row['programName']}</a></td>";
        print "<td>{$row['ifp']}</td><td>{$row['ifpLanguage']}</td>";
        print "</tr>";
	}
	$result->close();
    print "</table>";
}
?>
