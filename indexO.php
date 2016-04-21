<?php

//print(var_dump($_POST));

///// CONFIG /////

$config["target_folder"]=  "/var/sftp/uploader/DATA/";
$config["temp_folder"] = "/tmp/";
///// POST PARAMETER /////

if (isset($_POST["youtubeurl"])) {
    startDownload($_POST["youtubeurl"]);
}

///// FUNCTIONS /////

function startDownload($youtubeurl) {

    ##TODO code injection?
    $youtubeurl = escapeshellcmd($youtubeurl);
    # escape first, than remove it again
    $youtubeurl = str_replace("\?v=", "?v=", $youtubeurl);

    // check if HTTPS-URL
    if (filter_var($youtubeurl, FILTER_VALIDATE_URL) && substr($youtubeurl, 0, 6) == "https:") {

        // check if already streaming this videos to this destination
        if (!checkRunning($youtubeurl)) {

            global $config;

            $output = array();
            $cmd = "youtube-dl --get-id " . $youtubeurl;
            exec($cmd, $output);
            $youtubeid = $output[0];

            $tempfilepath   = $config["temp_folder"]   . "/" . $youtubeid . ".mp4";
            $targetfilepath = $config["target_folder"] . "/" . $youtubeid . ".mp4";

            $cmd = "bash -c '(youtube-dl --rate-limit 50000k -f bestvideo[ext=mp4]+bestaudio --no-playlist --cache-dir "
                . $config["temp_folder"] . " --output " . $tempfilepath . " " . $youtubeurl . " && cp " . $tempfilepath . " "
                . $targetfilepath ." && rm ". $tempfilepath . ")' >/dev/null 2>/dev/null &";

            exec($cmd);
//print($cmd . "<br><br>");

            $output = array();
            $cmd = "youtube-dl --dump-json " . $youtubeurl;
            exec($cmd, $output);
//print($cmd . "<br><br>");

            $json        = json_decode($output[0]);
            $file = $config["target_folder"] . "/" . $youtubeid . ".xml";
            $data = '<videoFile xmlns="http://www.make.tv/smt/playout/input">
    <title><![CDATA[' . $json->{"title"} . ']]></title>
    <description><![CDATA[' . $json->{"description"} . ']]></description>
    <gameData>
        <title>Unknown</title>
    </gameData>
</videoFile>';

            file_put_contents($file, $data, LOCK_EX);
        }
    }
}

function checkRunning($youtubeurl="") {

    ##TODO code injection?
    $youtubeurl = escapeshellcmd($youtubeurl);
    # escape first, than remove it again
    $youtubeurl = str_replace("\?v=", "?v=", $youtubeurl);

    $out_array = array();

    $exec_output = array();
    exec("ps ax -o uname,pid,lstart,args | grep -e '[p]ython .*/youtube-dl .* " . $youtubeurl . "'", $exec_output);

    if ($exec_output) {

        //$phpuser = posix_getpwuid(posix_geteuid())['name'];
        $phpuser = 'gerard';
        foreach ($exec_output as $line) {
            $parts = preg_split('/\s+/', $line);

            $processuser      = $parts[0];
            $processpid       = $parts[1];
            $processstarttime = strtotime($parts[2] . " " . $parts[3] . " " . $parts[4] . " " . $parts[5] . " " . $parts[6]);
            $processurl = end($parts);

            if ($processuser == $phpuser) {

                array_push($out_array, array("pid" => $processpid, "starttime" => $processstarttime, "youtubeurl" => $processurl));
            }
        }

        return $out_array;

    } else {

        return False;

    }
}

function createDivs() {

    $output = "";

    $running_processes = checkRunning();

    if ($running_processes) {

        $output .= "<div>
<p>Laufende Downloads:</p>";

        foreach ($running_processes as $process) {

            $output .= "<div>
    <p> Download von '" . $process["youtubeurl"]  . "' gestartet um " . gmdate("H:i", $process["starttime"]) . ".</p>
</div>";

        }
    }

    $output .= "</div>";

    return $output;
}

///// WEBSITE /////

header('Content-Type: text/html; charset=utf-8');
?>

<html>
<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8">
<meta http-equiv="content-language" content="de">
<style type="text/css">
        <!--
        body {
            font-family: Arial;
            padding:     0pt;
            margin:      0pt;
        }
        div {
            background-color: #aaa;
                    padding:          5pt;
        }
        h1 {
            font-size:   18pt;
            font-weight: bold;
            line-height: 20pt;
                    margin:      10pt;
        }
        p {
            font-size:   12pt;
                    margin:       1pt;
        }
        input {
            border-style: none;
            font-size:    8pt;
            margin:       2pt;
            height:       16pt;
        }
        input[type="button"]{
                    background-color: #ddd;
                    width:            50pt;
        }
        input[type="text"]{
                    background-color: #fff;
                    width:            250pt;
        }
        form {
            margin: 0pt;
        }
        -->
        </style>
<title>Youtube Download</title>
</head>
<body>
<h1>Youtube Download</h1>
    <div>
         <form method='post' action='indexO.php'>
             <input type='text' name='youtubeurl' placeholder='Youtube-URL hier reinkopieren' value="https://youtu.be/R62iQvZ0bdQ">
             <input type='submit' value='Starten'>
         </form>
<?php print(createDivs()) ?>
    </div>
</body>
</html>
