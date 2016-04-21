<?php
$debug=true;

?>
<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <meta http-equiv="content-language" content="de">
    <link rel="stylesheet" href="css/ytdl.css">
    <script src="js/ytdl.js"></script>
    <title>Youtube Download</title>
</head>
<body onload="checkProgress()">
<h1>Youtube Download</h1>
<div>
    <input type='text' name='youtubeurl' id="youtubeurl" placeholder='Youtube-URL hier reinkopieren'
           value="https://www.youtube.com/playlist?list=PLtxNvvX5ewnJJ5clCQcnZhQ-jeGjLtu1V"><br>
    <input type='submit' value='Starten' onclick="startDownLoad()"><input type='submit' value='Cancel'
                                                                          onclick="cancelDownLoad()">
    <br>Log
    <br><textarea id="log" cols="120" rows="15"></textarea><br><input type='submit' value='Clear' onclick="clearLog()">
    <input type='submit' value='getlog' onclick="getLog()" <?php if(!$debug)echo 'hidden'?>>

</body>
</html>
