<?php
/*************************************
* config.php
* configuration settings for the expungement generator
*
*  THIS VERSION IS FOR USE WITH A DOCKER CONTAINER.
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
**************************/
$debug=false;
// for a linux system
$basedir = join(DIRECTORY_SEPARATOR, array("", "var", "www"));
$toolsDir = join(DIRECTORY_SEPARATOR, array("", "usr", "local", "bin"));
$includeDir = join(DIRECTORY_SEPARATOR, array("", "usr", "local", "include"));
$wwwdir = join(DIRECTORY_SEPARATOR, array ($basedir, "html"));
$docketScraperAPIURL = getenv("docketscaperAPIURL");
$baseURL = getenv("BASE_URL");
$pdftotext = $toolsDir . DIRECTORY_SEPARATOR . "pdftotext";
$sendGridApiKey = getenv("SENDGRID_KEY");
// these shouldn't ever need to change
$dataDir = join(DIRECTORY_SEPARATOR, array($wwwdir, "data")) . DIRECTORY_SEPARATOR;
$templateDir = join(DIRECTORY_SEPARATOR, array($wwwdir, "templates")) . DIRECTORY_SEPARATOR;
$docketSheetsDir = join(DIRECTORY_SEPARATOR, array($wwwdir, "docketsheets")) . DIRECTORY_SEPARATOR;
// db information
$dbPassword = getenv("DB_PASS");
$dbUser = getenv("DB_USER");
$dbName = getenv("DB_NAME");
$dbHost = getenv("DB_HOST");
//charge DB information
$chargeDBHost = getenv("CHARGE_DB_HOST");
$chargeDBUser = getenv("CHARGE_DB_USER");
$chargeDBPassword = getenv("CHARGE_DB_PASS");
$chargeDBName = getenv("CHARGE_DB_NAME");
// this is only needed in the CLS production environmnet
/*
$crepDBPassword = "fakepassword";
$crepDBUser = "fakeusername";
$crepDBName = "eg";
$crepDBHost = "mydburl.org";
*/
$tempFile = tempnam($dataDir, "FOO");
// setup DB
require_once("dbconnect.php");
// set up the Message handler
require_once("Message.php");
$errorMessages = new Message();
// this logs a user in; must happen early on b/c of header requirements with session vars
require_once("doLogin.php");
?>
