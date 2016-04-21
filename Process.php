<?php
class Process{
    private $pid;
    private $command;
    private $outputfile="/dev/null 2>&1";
    public function __construct($cl,$output=false){

        $this->command = $cl;
        if ($output != false){
            $this->outputfile=$output;
        }
        $this->runCom();
    }
    private function runCom(){

        $command = 'nohup '.$this->command.' > '.$this->outputfile.' & echo $!';
        exec($command ,$op);
        $this->pid = (int)$op[0];
    }

    public function setPid($pid){
        $this->pid = $pid;
    }

    public function getPid(){
        return $this->pid;
    }

    public function status(){
        $command = 'ps -p '.$this->pid;
        exec($command,$op);
        if (!isset($op[1]))return false;
        else return true;
    }

    public function start(){
        if ($this->command != '')$this->runCom();
        else return true;
    }

    public function stop(){
        $command = 'kill '.$this->pid;
        exec($command);
        if ($this->status() == false)return true;
        else return false;
    }
}
?>
