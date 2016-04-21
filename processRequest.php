<?php
/**
 * Created by IntelliJ IDEA.
 * User: gerard
 * Date: 06.04.16
 * Time: 09:46
 */
error_reporting(1);
define('TEMP_DIR', '/Users/gerard/youtubedownload/temp');
define('TARGET_DIR', '/Users/gerard/youtubedownload/target');
///define('TARGET_DIR', 'Users/gerard/smt/upload');
include_once("php/YoutubeDownloader.php");

///// POST PARAMETER /////
$headers = getallheaders();
if ($headers["Content-Type"] == "application/json") {
    $request = json_decode(file_get_contents("php://input"), true);

}
if(!isset($request))
{
    $request['action']="getstatus";
}
$youtubeDownLoader=new YoutubeDownloader();
$youtubeDownLoader->executeRequest($request);
?>
