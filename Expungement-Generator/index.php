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

	require_once("config.php");
	require_once("utils.php");
	include("head.php");

	// the page header, including the menu bar, etc...
	include("header.php");
?>

<div class="main">
	<div class="pure-u-8-24">&nbsp;</div>
	<div class="pure-u-12-24">
<?php
// if the user isn't logged in, then don't display this page.  Tell them they need to log in.
if (!isLoggedIn())
	include("displayNotLoggedIn.php");
else
{
    // kill old session information that might screw things up on the search page
    resetSession();
?>
		<form action="expunge.php" method="post" enctype="multipart/form-data">
		<div class="form-item">
			<label for="personFirst">Client's Name</label>
			<div class="form-item-column">
				<input type="text" name="personFirst" id="personFirst" class="form-text" value="<?php printIfSet('personFirst');?>" /> 
			</div>
			<div class="form-item-column">
				<input type="text" name="personLast" id="personLast" class="form-text" value="<?php printIfSet('personLast');?>" />
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
			<input type="date" name="personDOB" id="date" value="<?php printIfSet('personDOB');?>" maxlength="10"/>
			<div class="description">MM/DD/YYYY</div>
		</div>
		<div class="form-item">
			<label for="personStreet">Address</label>
			<input type="text" name="personStreet" id="personStreet" class="form-text" value="<?php printIfSet('personStreet');?>" />
			<div class="description">Street Name, Number</div>
			<div class="space-line"></div>
			<div class="form-sub-item">
				<div class="form-item-column">
					<input type="text" name="personCity" class="form-text" value="<?php printIfSet('personCity');?>" />
				</div>
				<div class="form-item-column form-item-column-state">
					<input type="text" name="personState" class="form-text form-text-state" value="<?php printIfSet('personState');?>"/>
				</div>
				<div class="form-item-column">
					<input type="text" name="personZip" class="form-text form-text-zip" value="<?php printIfSet('personZip');?>"/>
				</div>
				<div class="space-line"></div>
				<div class="description">
					<div class="form-item-column">
						City
					</div>
					<div class="form-item-column form-item-column-state">
						State
					</div>
					<div class="form-item-column">
						Zip
					</div>
				</div>
				<div class="space-line"></div>
			</div> 
		</div>
		<div class="form-item">
		<label for="personSSN">Social Security Number</label>
			<input type="text" name="personSSN" id="personSSN" value="<?php printIfSet('personSSN');?>" />
			<div class="description">###-##-####</div>
		</div> 

        <div class="form-item">
            <input type="radio" name="cpcmsSearch" value="true" checked="checked" onclick="$('#addFiles').hide();"/>Search CPCMS for me
        <div>
        <div class="form-item">
            <input type="radio" name="cpcmsSearch" value="false" onclick="$('#addFiles').show();"/>I would like to upload my own dockets
       </div>
       <div class="space-line"></div>
        <div style="display:none" id="addFiles">
    		<div class="form-item">
    			<label for="userFile1">Send these files (contrl click to select multiple files)</label>
    			<input type="hidden" name="MAX_FILE_SIZE" value="300000" />
    			<input name="userFile[]" type="file" multiple="true" name="userFile1"/>
    		</div>
    		<div class="space-line">&nbsp;</div>
            <div class="form-item"> 
                <label for='addEntryOfAppearance'> Generate an Entry of Appearance? </label>
                <input id='addEntryOfAppearance' type='checkbox' name='addEntryOfAppearance' value='addEntryOfAppearance'/> 
                Generate an Entry of Appearance? 
            </div>
 
            <div class="form-item">
                <label for="expungeRegardless">File Expungements Regardless of Expungeability?"</label>
                <input type="checkbox" name="expungeRegardless" id="expungeRegardless" value="true" />File expungements regardless of expungeability? (Pardons, etc...)
            </div>
    		<div class="space-line">&nbsp;</div>
        </div>
    	<div class="form-item">
			<input type="submit" value="Start Expunging" />
		</div>
		</form>
<?php 
}  // else isloggedin()
?>
	</div>
	<div class="content-right">&nbsp;</div>
</div>

<?php 
	include("foot.php");
?>
