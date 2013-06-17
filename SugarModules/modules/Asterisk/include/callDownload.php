<?php
/**
 * Asterisk SugarCRM Integration
 * (c) KINAMU Business Solutions AG 2009
 *
 * Parts of this code are (c) 2011 Vladimir Sibirov contact@kodigy.com
 * Parts of this code are (c) 2013 Callinize - Blake Robertson http://www.blakerobertson.com
 * http://www.sugarforge.org/projects/yaai/
 *
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more
 * details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact Callinize at hello@callinize.com
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 */

if(!defined('sugarEntry'))define('sugarEntry', true);

chdir("../");
chdir("../");
chdir("../");
chdir("../");

require_once('include/utils.php');
require_once('include/export_utils.php');

global $sugar_config;

require_once('include/entryPoint.php');
require_once('modules/Contacts/Contact.php');
require_once('modules/Users/User.php');

session_start();

$current_language = $_SESSION['authenticated_user_language'];
if(empty($current_language)) {
	$current_language = $sugar_config['default_language'];
}
require("custom/modules/Asterisk/language/" . $current_language . ".lang.php");
$cUser = new User();
$cUser->retrieve($_SESSION['authenticated_user_id']);

if (!$cUser) {
    http_response_code(403);
    header('403 Forbidden');
    echo '<h1>Forbidden</h1>';
    exit;
}


$astId = '';

// --- STEP 1:  Find the Asterisk Call ID, The recordings have the asterisk id in the filename.
if( !empty($_GET['callId']) && empty($_GET['astId']) ) {
    $qry = "SELECT asterisk_call_id_c from calls_cstm WHERE id_c = '" . mysql_real_escape_string($_GET['callId']) . "'";
    error_log($qry);

    $resultSet = $GLOBALS['current_user']->db->query($qry, false);
    if ($GLOBALS['current_user']->db->checkError()) {
        trigger_error("Find Remote Channel-Query failed: $query");
    }
    $row = $GLOBALS['current_user']->db->fetchByAssoc($resultSet);

    error_log( print_r($row,true) );
    if( !empty($row['asterisk_call_id_c']) ) {
        $astId = $row['asterisk_call_id_c'];
    }
}
else if( !empty( $_GET['astId'] ) ) {
    $astId = $_GET['astId'];
}
else {
    http_response_code(405);
    echo '<h1>Missing Required Arguments</h1>';
    exit;
}


// --- STEP 2: If we know the $astID, lets see if it's on the file system.
if( !empty($astId) ) {

	$found = false;
    error_log("REC Path: " . $sugar_config['asterisk_recordings_path']);
    $files_wav = glob($sugar_config['asterisk_recordings_path'] . '/*' . $astId . '.wav');
	$files_mp3 = glob($sugar_config['asterisk_recordings_path'] . '/*' . $astId . '.mp3');
	if (count($files_wav) == 1 && filesize($files_wav[0]) > 0) {
		$path = $files_wav[0];
		$content_type = 'audio/wav';
		$file_ext = 'wav';
		$found = true;
	} elseif (count($files_mp3) == 1 && filesize($files_mp3[0]) > 0) {
		$path = $files_mp3[0];
		$content_type = 'audio/mpeg';
		$file_ext = 'mp3';
		$found = true;
	}

    $action = $_GET['action'];
    if( $action == "doesRecordingExist" ) {
        $ajaxResult = array(
            "found" => $found,
            "Content-Type" => $content_type,
            "Content-Length" => filesize($path),
            "ext" => $file_ext,
            "asteriskId" => $astId,
            "playUrl" => "index.php?entryPoint=AsteriskCallDownload&astId=$astId&action=play",
            "downloadUrl" => "index.php?entryPoint=AsteriskCallDownload&astId=$astId&action=download",
        );
        print json_encode($ajaxResult);
        exit; // This is the ajax call that checks to see if it exists.
    }
    else if( $action == "download" || $action == "play" ) {
        if( $found ) {
            header('Content-Type: ' . $content_type);
            header('Content-Length: ' . filesize($path));
            if ($action == "download")
            {
                header('Content-Disposition: attachment; filename="call_'.$astId.'.'.$file_ext.'"');
            }
            readfile($path);
            // Add some error handling here... does readfile throw exceptions?
            exit;
        }
        else {
            $http_response_code(404);
            echo "<h1>Can't find recording</h1>";
        }
    }
}

http_response_code(404);
//header('404 Not Found');
echo '<h1>Unknown Error</h1>';
exit;

?>
