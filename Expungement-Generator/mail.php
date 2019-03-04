<?php

/*****************************************
 * *
 * *   mail.php
 * *   Accepts post data with an email address, docket numbers, a summary docket
 * *   downloads the dockets, and emails them to the email address.
 * *
 * *   Copyright 2011-2016 Community Legal Services
 * *
 * * Licensed under the Apache License, Version 2.0 (the "License");
 * * you may not use this file except in compliance with the License.
 * * You may obtain a copy of the License at
 * *
 * *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * * Unless required by applicable law or agreed to in writing, software
 * * distributed under the License is distributed on an "AS IS" BASIS,
 * * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * * See the License for the specific language governing permissions and
 * * limitations under the License.
 * *
 * *********************************************/

//  require_once("config.php");
//  require_once("utils.php");
require "vendor/autoload.php";
require_once("CPCMS.php");
require_once("helpers/docket_number_from_url.php");

if (!empty($_POST)) {
    // send a quick blank response so scripts don't wait for me to download dockets
    ob_end_clean();
    ignore_user_abort();
    ob_start();
    header("Connection: close");
    header("Content-Length: " . ob_get_length());
    ob_end_flush();
    flush();



    // build the email message
    $message = "The EG found the following cases for this individual:\r\n\r\n";
    $message .= (
      "Summary Docket: " . "\r\n" .
      " | " . docketNumberFromURL($_POST['bestDocket']) . " | " .
      "\r\n\r\n");
    $message .= "All dockets: \r\n\r\n";
    foreach ($_POST['dockets'] as $docket)
    {
        $message .= " | " . docketNumberFromURL($docket) . " | " . "\r\n";
    }

    // download the dockets
    $docketFiles = CPCMS::downloadDockets(
        $_POST['dockets'], $_POST['bestDocket'], true);


    // create the email
    $emailAddress = $_POST['email'];

    $from = new SendGrid\Email("Expungement Generator", "mhollander@clsphila.org");
    $subject = "EG Generated Docket Search";
    $to = new SendGrid\Email(null, $emailAddress);
    $content = new SendGrid\Content("text/plain", $message);
    $mail = new SendGrid\Mail($from, $subject, $to, $content);

    // attach the dockets to the email if they have size
    foreach($docketFiles["userFile"]["tmp_name"] as $key => $file)
    {
        if (filesize($file) > 0)
        {
            $attachment = new SendGrid\Attachment();
            $attachment->setContent(base64_encode(file_get_contents($file)));
            $attachment->setType("application/pdf");
            $attachment->setFilename($docketFiles['userFile']['name'][$key]);
            $attachment->setDisposition("attachment");

            $mail->addAttachment($attachment);
        }
    }


    $sg = new \SendGrid($sendGridApiKey);

    $response = $sg->client->mail()->send()->post($mail);

}
?>
