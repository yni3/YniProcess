<?php

require "SplClassLoader.php";

$classLoader = new SplClassLoader('YniProcess', '../vendor');
$classLoader->register();

class Test extends \YniProcess\YniBase implements \YniProcess\YniRunnable {

    private $i = 0;

    public function __construct($i) {
        $this->i = $i;
    }

    public function onHandledMessage($message_data, \YniProcess\YniProcess $recivedObject) {
        
    }

    public $sub_result;

    public function onHandledMessageAtParentProcess($message_data) {
        $pid = getmypid();
        echo "onHandledMessageAtParentProcess {$message_data} {$pid}\n";
        $this->sub_result = $message_data;
    }

    public function onHandledMessageAtSubProcess($message_data) {
        $pid = getmypid();
        echo "onHandledMessageAtSubProcess {$message_data} {$pid}\n";
    }

    public function run(\YniProcess\YniProcess $parent) {
        $sum = $this->i;
        usleep(1000);
        $parent->sendMessage(pow($sum, 2));
        //echo pow($sum, 2),"\n";
    }

} {
    for ($i = 0; $i < 500; $i++) {
        $t[$i] = new Test($i);
        $sub[$i] = new YniProcess\YniProcess($t[$i]);
        $sub[$i]->sendMessage($i);
        //echo $sub[$i]->getPid(), "\n";
    }
    while (count($sub) > 0) {
        foreach ($sub AS $index => $p) {
            if (!$p->isAlive()) {
                $sub[$index]->terminate();
                unset($sub[$index]);
            }
        }
        usleep(1000);
    }
    foreach ($t AS $ent) {
        echo $ent->sub_result, "\n";
    }
}
for ($i = 0; $i < 500; $i++) {
    $t[$i] = new Test($i);
    $sub[$i] = new YniProcess\YniProcess($t[$i]);
    $sub[$i]->sendMessage($i);
    //echo $sub[$i]->getPid(), "\n";
}
while (count($sub) > 0) {
    foreach ($sub AS $index => $p) {
        if (!$p->isAlive()) {
            $sub[$index]->terminate();
            unset($sub[$index]);
        }
    }
    usleep(1000);
}
foreach ($t AS $ent) {
    echo $ent->sub_result, "\n";
}