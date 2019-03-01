<?php

/*****************************************
*
*	mailDockets.php
*	Accepts a form with a legalserver case id and docket numbers
* and sends an email to legalserver(@clsphila) with the numbers and a link
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
require "vendor/autoload.php";
require_once("CPCMS.php");
include("head.php");

?>
    <div class='main'>
    <div class='pure-u-8-24'>&nbsp;</div>
    <div class='pure-u-12-24'>

<?php
if (!empty($_POST))
{
    // make a request to our emailing URL and print a message
    $ch = curl_init();

    // set url
    curl_setopt($ch, CURLOPT_URL, $baseURL . "mail.php");


    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_TIMEOUT, 1);
   // curl_setopt($ch, CURLOPT_NOSIGNAL, 1);

    // post request
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTREDIR, 3);

    //build the post request
    $emailAddress = $_POST['lsNumber'] . "@clsphila.legalserver.org";
    $postFields = array('bestDocket'=>$_POST['bestDocket'], 'dockets'=>$_POST['dockets'], 'email'=>$emailAddress);
    $postFieldsString = http_build_query($postFields);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFieldsString);

    // $output contains the output string
    $output = curl_exec($ch);

    // close curl resource to free up system resources
    curl_close($ch);

    print "Email successfully sent to $emailAddress";
}

else
{
    print "You didn't properly submit anything for me to mail.  Blech!";
}

?>
    </div>
	<div class="content-right">&nbsp;</div>
</div>

<?php
	include("foot.php");
?>
