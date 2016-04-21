<?php

/**
 * Created by IntelliJ IDEA.
 * User: gerard
 * Date: 11.04.16
 * Time: 15:05
 */
define('START_DOWNLOAD', 'startdownload');
define('CANCEL_DOWNLOAD', 'canceldownload');
define('GET_PROGRESS', 'getprogress');
define('GET_LOG', 'getlog');
define('ERROR', 'ERROR');
define('OK', 'OK');


class YoutubeDownloader
{
    private $playlist;
    private $playlistID;
    private $unique;
    private $pid;
    private $childpid;
    private $request;
    private $action;
    private $ytlog;//Youttube dowload output
    private $pidfile;//Process pid
    private $errorlog;//Process Errorlog ;
    private $debuglog;//Debuglog ;
    private $tempdir;

    private $targetdir;

    private $command;//YTDL command ;

    public function __construct()
    {
        session_start();
        $this->initSession();
        $this->debug("-------------------------------------------");
        $this->debug("YoutubeDownload initializes current ID $this->unique");
        /*
         * Check for running processes (if possible)
         */
        $this->getPID();
    }

    public function executeRequest($request)
    {
        $action = $request["action"];
        $this->debug("ACTION $action");
        if (!isset($action)) {
            $this->reportResult(false, json_encode($request));
            return;
        }
        $this->request = $request;
        $this->action = $action;
        switch ($action) {
            case START_DOWNLOAD:
                $this->startDownload($request["youtubeurl"]);
                break;
            case CANCEL_DOWNLOAD:
                $this->cancelDownload();
                break;
            case GET_PROGRESS:
                $this->checkProgress();
                break;
            case GET_LOG:
                $this->getLog();
                break;
            default:
                $this->reportResult(false, json_encode($request));
        }
    }

    private function startDownload($playlist)
    {
        // shell script is run  as backgroundprocess
        // check for running process
        $this->playlist = $playlist;
        if (isset($this->pid)) {
            if ($this->isProcessRunning($this->pid)) {
                $this->killAllProcesses();
                $this->clearFiles();
            }
        }
        $this->resetSession();
        $this->command = "./downloadytpl.sh $this->tempdir $this->playlist $this->ytlog> $this->pidfile 2>$this->errorlog &";
        exec($this->command);
        $this->debug("executed " . $this->command);
        //The shell script pid is stored in the $pidfile
        $this->checkExecResult(0);
    }

    private function cancelDownload()
    {
        $this->debug("cancel download");
        $this->cleanUp();
        $this->reportResult(true, "download canceled");
    }

    private function checkExecResult($i)
    {
        $this->getPID();
        $this->debug("checkExecResult " . $i);
        if ($this->pid && $this->isProcessRunning($this->pid)) {
            if ($this->childpid) {
                $this->reportResult(true, "download started $this->playlist");
                return;
            }
        }
        if ($this->pid && !$this->isProcessRunning($this->pid)) {
            $errors = file_get_contents($this->errorlog);
            $errors = $errors . $this->getLogErrors();
            //$this->cleanUp();
            $this->reportResult(false, "Not started : $errors");
            return;

        }
        if ($i > 3) {
            //$this->cleanUp();
            $errors = file_get_contents($this->errorlog);
            $errors = $errors . $this->getLogErrors();
            $this->reportResult(false, "Not started  : $errors");
            return;
        }
        sleep(1);
        $i++;
        $this->checkExecResult($i);
    }

    private function getPID()
    {
        if (!file_exists($this->pidfile)) {
            $this->debug("getPID $this->pidfile no file");
            return;
        }
        $pid = file_get_contents($this->pidfile);
        $pid = trim($pid, "\n\r");
        $this->debug("getPID $this->pidfile ");
        if (isset($pid)) {
            $this->pid = $pid;
            $cpid = $this->getChildProcess($pid);
            $this->childpid = $cpid;
            $this->debug("PID $pid CPID $cpid");
        } else {
            $this->debug("No PID   $pid ");
        }
    }

    private function getChildProcess($pid)
    {
        $command = "pgrep -P $pid";
        exec($command, $op);
        return array_shift($op);
    }

    private function checkProgress()
    {
        $this->debug("start checkProgress $this->ytlog");

        $pid = $this->childpid;
        $isrunning = $this->isProcessRunning($pid);
        $lines = $this->getYTDLLog();
        $lastline = array_pop($lines);
        if ($isrunning) {
            if (strlen($lastline) == 0) {
                $lastline = "Waiting for $pid  ... ";
            }
        }
        $status = $isrunning ? "running" : "ready";
        $this->debug("checkProgress lastline  $lastline running $status $pid");
        $error = $this->getLogErrors();
        if ($error) {
            $this->debug("checkProgress error  $error");
        }
        if (!$isrunning) {
            if ($error == null) {
                $this->processYTFiles();
            } else {
                $this->reportResult(false, "EXECUTION_COMPLETE : $error");
            }
            return;
        }
        $this->reportResult(true, $lastline);
    }

    private function isProcessRunning($pid)
    {
        $command = 'ps -p ' . $pid;
        exec($command, $op);
        if (!isset($op[1])) return false;
        else return true;
    }

    private function killProcess($pid)
    {
        $command = "kill $pid";
        exec($command, $output);
        $this->debug("kill process " . $pid . " " . json_encode($output));
    }


    private function reportResult($ok, $data)
    {
        $result['action'] = $this->action;
        $result['status'] = $ok === false ? ERROR : OK;
        $result['data'] = $data;
        $this->debug("reportResult " . json_encode($result));
        echo json_encode($result);
    }

    private function debug($msg)
    {
        $debuglog = $this->debuglog;
        if (!isset($debuglog)) {
            $debuglog = "Debug.log";
        }
        $fp = fopen($debuglog, 'a');
        $msg = date("Y-m-d H:i:s") . ":" . $msg;
        fwrite($fp, $msg . "\n");
        fclose($fp);
    }

    private function getLog()
    {
        $log = 'no log found';
        if (file_exists($this->debuglog)) {
            $log = file_get_contents($this->debuglog);
        }
        $this->reportResult(true, $log);
    }

    private function getYTDLLog()
    {
        $this->debug("getYTDLLog");
        $lines = array();
        if (file_exists($this->ytlog)) {
            $log = file_get_contents($this->ytlog);
            $array = explode("\n", $log);
            foreach ($array as $line) {
                $lines = array_merge($lines, explode("\r", $line));
            }
        }
        return $lines;
    }

    private function processYTFiles()
    {
        $this->debug("processYTFiles");
        if ($this->createFiles()) {
            $result = "EXECUTION_COMPLETE";
        } else {
            $result = "EXECUTION_COMPLETE:ERROR files not created";
        };
        $this->cleanUp();
        $this->reportResult(true, $result);
    }

    function createFiles()
    {
        /*
         * When something goes wrong false is returned
         */
        $this->debug("start createFiles $this->tempdir");

        if (!file_exists($this->tempdir)) {

            return false;
        }
        $fh = opendir($this->tempdir);
        if (!$fh) {
            return false;
        }
        $this->debug("start processFiles $fh");
        $jsonfiles = array();
        while (false !== ($file = readdir($fh))) {

            if (strpos($file, 'json') !== false) {
                $jsonfiles[] = $file;
            }
        }
        closedir($fh);
        unset($this->playlistID);
        //Playlist

        if (count($jsonfiles) > 0) {
            $this->debug("process playlist $this->playlistID");
            //TODO Create folder xml
            $videos = array();
            foreach ($jsonfiles as $jsonfile) {
                $this->debug("start processFiles file $jsonfile");
                if (!$this->createVideoXMLAndCopy($jsonfile)) {
                    return false;
                }
            }
        }
        if (count($jsonfiles) > 1 && isset($this->playlistID)) {
            //Playlist
            foreach ($jsonfiles as $jsonfile) {
                $json = json_decode(file_get_contents("$this->tempdir/$jsonfile"));
                $playlistname = $json->playlist;
                $nr = $json->playlist_index;
                $id = $json->id;
                $videos[$nr] = $id;
            }
            $plfile = "$this->tempdir/$this->playlistID.xml";
            $data = "<playlist xmlns=\"http://www.make.tv/smt/playout/library\" id=\"$this->playlistID\">\n<title><![CDATA[$playlistname]]></title>";
            foreach ($videos as $video) {
                $data .= "\n<video id=\"$video\"   libraryId=\"\"  status=\"0\" />";
            };
            $data .= "\n</playlist>";
            if (file_put_contents($plfile, $data, LOCK_EX) === false) {
                return false;
            }
            $this->deleteJSONFiles();
            rename($this->tempdir, "$this->targetdir/$this->playlistID");
        } else {
            //single videofile
            $this->debug("rename files");
            $fh = opendir($this->tempdir);
            while (false !== ($file = readdir($fh))) {
                $this->debug("rename $file");
                if (strpos($file, '.xml') !== false || strpos($file, '.mp4') !== false) {
                    rename("$this->tempdir/$file", "$this->targetdir/$file");
                }
            }
            closedir($fh);
            $this->deleteJSONFiles();
        }

        $this->debug("end createFiles ");
        return true;
    }

    private function deleteJSONFiles()
    {
        $fh = opendir($this->tempdir);
        if ($fh) {
            $this->debug("remove JSONfiles");

            while (false !== ($file = readdir($fh))) {

                if (strpos($file, 'json') !== false) {
                    $this->debug("remove $file");
                    unlink("$this->tempdir/$file");
                }
            }
            closedir($fh);
        }
    }

    function createVideoXMLAndCopy($file)
    {
        $this->debug("start createVideoXML $file");
        $name = array_shift(explode('.', $file));
        $videofile = "$this->tempdir/$name.mp4";
        if (!file_exists($videofile)) {
            return false;
        }
        $json = json_decode(file_get_contents("$this->tempdir/$file"));
        $this->playlistID = $json->playlist_id;
        $data = "<videoFile xmlns=\"http://www.make.tv/smt/playout/input\"><title><![CDATA[ $json->title]]>";
        $data .= "</title><description><![CDATA[' . $json->description]]></description>";
        $data .= "<gameData><title>Unknown</title></gameData></videoFile>";
        $file = "$this->tempdir/$json->id.xml";
        if (file_put_contents($file, $data, LOCK_EX) !== false) {
            return true;
        } else {
            return false;
        };
    }

    private function extractError($lines)
    {
        foreach ($lines as $line) {
            if (strpos($line, 'ERROR') !== false) {
                return $line;
            }
        }
        return false;
    }

    private function resetSession()
    {
        $this->cleanUp();
        $_SESSION['ytdlid'] = uniqid();;
        $this->initSession();
    }

    private function initSession()
    {
        if (!isset($_SESSION['ytdlid'])) {
            $_SESSION['ytdlid'] = uniqid();;
        }
        $uniq = $_SESSION['ytdlid'];
        $this->unique = $uniq;
        $this->debuglog = "logs/Debug$uniq.log";
        $this->pidfile = "PID$uniq";
        $this->ytlog = "YT$uniq.log";
        $this->errorlog = "Error$uniq.log";
        $this->debug('initSession');
        $this->targetdir = TARGET_DIR;
        $this->tempdir = TEMP_DIR . "/$uniq";
        if (!file_exists($this->targetdir)) {
            mkdir($this->targetdir);
        }
        if (!file_exists($this->targetdir)) {
            reportResult(false, " Dir  $this->targetdir doesn't exist");
        }
        $this->debug('End target initSession');
        if (!file_exists($this->tempdir)) {
            mkdir($this->tempdir);
        }
        if (!file_exists($this->tempdir)) {
            reportResult(false, " Dir  $this->tempdir doesn't exist");
        }

        // $datestamp = date("Ymd");
        if (!file_exists('logs')) {
            mkdir('logs');
        }
        $this->debug('End initSession');
    }

    private function getLogErrors()
    {
        $lines = $this->getYTDLLog();
        return $this->extractError($lines);
    }

    private function cleanUp()
    {
        $this->debug("CleanUp");
        $this->killAllProcesses();
        $this->clearFiles();

        unset($_SESSION['ytdlid']);
    }

    private function clearFiles()
    {
        unlink($this->ytlog);
        //unlink($this->debuglog);
        unlink($this->errorlog);
        unlink($this->pidfile);
        unset($this->childpid);
        unset($this->pid);
        if (file_exists($this->tempdir)) {
            $fh = opendir($this->tempdir);
            if ($fh) {
                while ($file = readdir($fh)) {
                    unlink("$this->tempdir/$file");
                }
                rmdir($this->tempdir);
            }
        }
    }

    private function killAllProcesses()
    {
        $this->getPID();
        if (isset($this->childpid)) {
            $this->killProcess($this->childpid);
        }
        if (isset($this->pid)) {
            $this->killProcess($this->pid);
        }
    }
}
