<?php

namespace YniProcess;

use YniProcess\YniBase;
use \RuntimeException;
use \BadMethodCallException;
use \DomainException;
use YniProcess\YniRunnable;
use \Exception;

/**
 * マルチプロセスの生成ユースリティ
 * メッセージのリアルタイム受信を有効にするには、
 * declare(ticks=1);
 * をYniRunnableの実装クラスのブロック内で実行する必要があります。
 * シグナルハンドラを上書きすると、メッセージのリアルタイム受信は利用不能になります。
 * 手動で、pcntl_signal_dispatch (  )をコールする必要があります。
 *
 * @version 1.0
 * @author yni3
 */
class YniProcess extends YniBase {

    const SUB_PROC_INITIALIZED_MESSAGE = "2001902";
    const sendMessageSignal = SIGUSR1;
    const FORK_FAILED = NULL;
    const MESSAGE_SUBPROC_FINISHED_WORK = '01101011';

    protected $domain = NULL;
    protected $pid = self::FORK_FAILED;
    protected $handler = NULL;
    protected $isSubProcess = false;
    protected $setBackGroud = false;

    /**
     * @var YniRunnable 
     */
    protected $job = NULL;
    //ログ表示
    private static $bLog = false;

    public static function isLog($enable) {
        self::$bLog = $enable;
    }

    protected static function logN($str) {
        if (self::$bLog) {
            echo $str, "\n";
        }
    }

    //シグナルハンドラ系の処理----------------------------------------------------

    protected static $handlerList = array();

    protected static function registorProcess(YniProcess $proc) {
        self::logN("registorProcess ({$proc->getObjectId()})");
        self::$handlerList[$proc->getObjectId()] = $proc;
    }

    protected static function unregistorProcess(YniProcess $proc) {
        if (isset(self::$handlerList[$proc->getObjectId()]) === true && !$proc->isSubProcess()) {
            $c = count(self::$handlerList) - 1;
            self::logN("unregistorProcess ({$proc->getObjectId()}) left({$c})");
            unset(self::$handlerList[$proc->getObjectId()]);
            //d::dumpError(self::$handlerList);
        }
    }

    protected static function unregistorALL() {
        foreach (self::$handlerList AS $proc) {
            $proc->setAsCopiedProc();
            unset($proc);
        }
    }

    public function signalHandler($signo) {
        switch ($signo) {
            case SIGTERM:
                exit;
                break;
            case self::sendMessageSignal: //メッセージ受信時
                $this->recivSocketParent();
                break;
            case SIGUSR2:
            default:
                exit;
                break;
        }
    }

    public function signalHandlerChild($signo) {
        switch ($signo) {
            case SIGTERM:
                exit;
                break;
            case self::sendMessageSignal: //メッセージ受信時
                $this->recivSocketSub();
                break;
            case SIGUSR2:
            default:
                exit;
                break;
        }
    }

    protected static function clearN($string) {
        $eol = PHP_EOL;
        return preg_replace("/{$eol}$/", '', $string);
    }

    //プロセス本体の処理---------------------------------------------------------
    /**
     * プロセスを生成します。\n
     * 生成したプロセスは、自動で走り出します。
     * @param YniRunnable $command
     * @throws RuntimeException
     */
    public function __construct(YniRunnable $command) {
        //実行チェック
        if (!function_exists('pcntl_fork')) {
            throw new RuntimeException('PCNTL functions not available on this PHP installation');
        }
        $this->domain = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' ? STREAM_PF_INET : STREAM_PF_UNIX);
        $this->job = $command;
        declare(ticks = 1);
        $this->fork();
    }

    protected $isCopiedProc = false;

    /**
     * オブジェクト破棄時の自動開放をOFFにします。
     */
    protected function setAsCopiedProc() {
        $this->isCopiedProc = true;
    }

    /**
     * サブプロセスを強制停止します。
     */
    public function terminate() {
        if ($this->isSubProcess === true) {
            throw new BadMethodCallException("this method is not allowed from sub process");
        }
        self::unregistorProcess($this);
        if ($this->isAlive()) {
            if ($this->handler !== NULL) {
                if (!fclose($this->handler)) {
                    throw new DomainException('fclose failed');
                }
                unset($this->handler);
                $this->handler = NULL;
            }
            $this->signal(SIGTERM);
            while ($this->isAlive()) {
                //停止するまで待つ。
            }
        }
    }

    /**
     * この関数は、terminateをYniProcessの生成スコープ内で使用しないと呼ばれません
     * 
     */
    public function __destruct() {
        if ($this->isSubProcess === false && !$this->isCopiedProc) {
            self::logN("__destruct ({$this->getObjectId()})");
            self::unregistorProcess($this);
        }
        if ($this->handler !== NULL && !$this->isCopiedProc) {
            if (!fclose($this->handler)) {
                throw new DomainException('fclose failed');
            }
            unset($this->handler);
            $this->handler = NULL;
            if ($this->isSubProcess === false) {
                self::logN("close handler ({$this->getObjectId()})");
            }
        }
        if (!$this->isSubProcess && !$this->setBackGroud && !$this->isCopiedProc) {
            $this->terminate();
        }
    }

    /**
     * called from __construct()
     */
    protected function fork() {
        //'stream_socket_pair(): failed to create sockets: [24]: Too many open files' スコープ内でデストラクタが呼ばれないと、handlerが解放されない。
        $sockets = stream_socket_pair($this->domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        if (empty($sockets)) {
            $this->pid = self::FORK_FAILED;
            throw new BadMethodCallException("stream_socket_pair failed. PHP may not destruct in your scope. try to call terminate() manually.");
        }
        if (count($sockets) > 2) {
            throw new \RuntimeException("stream_socket_pair is not compatitable");
        }
        self::logN("open handler\n");
        $this->pid = pcntl_fork();
        if ($this->pid < 0) { //プロセス生成失敗
            self::logN("process is unknown exit.");
            exit(1);
        } elseif ($this->pid) { //親プロセス
            pcntl_signal(self::sendMessageSignal, array($this, 'signalHandler'));
            self::registorProcess($this);
            if (!fclose($sockets[1])) {
                throw new DomainException('fclose failed');
            }
            $this->handler = &$sockets[0];
            //stream_set_blocking($this->handler, false);
            fwrite($this->handler, getmypid() . PHP_EOL);
            $data = fgets($this->handler);
            if ($data === self::SUB_PROC_INITIALIZED_MESSAGE . PHP_EOL) {
                
            } else {
                throw new DomainException("Process Initialization Failed");
            }
            $this->initialized = true;
        } else { //子プロセス
            //子プロセスは、ここからコンストラクトされることになる。
            pcntl_signal(self::sendMessageSignal, array($this, 'signalHandlerChild'));
            self::unregistorALL();
            $this->isSubProcess = true;
            if (!fclose($sockets[0])) {
                throw new DomainException('fclose failed');
            }
            $this->handler = &$sockets[1];
            //stream_set_blocking($this->handler, false);
            //$this->pid = getmypid();
            //$this->pid = posix_getppid(); //この関数は存在しない。
            $this->pid = self::clearN(fgets($this->handler)); //親プロセスのpidを取得
            $this->initialized = true;
            fwrite($this->handler, self::SUB_PROC_INITIALIZED_MESSAGE . PHP_EOL); //親プロセスとトランザクションを行う。
            $this->execute();
            exit;
        }
        return true;
    }

    protected function signal($signal) {
        if (function_exists('posix_kill')) {
            return posix_kill($this->pid, $signal);
        } else {
            system("kill -{$signal} {$this->pid}", $result);
            return $result === 0;
        }
    }

    /**
     * サブプロセスの優先度を設定します。
     */
    public function setPriority($priority) {
        if ($this->isSubProcess === true) {
            throw new BadMethodCallException("this method is not allowed from sub process");
        }
        pcntl_setpriority($priority, $this->pid);
    }

    /**
     * サブプロセスの優先度を設定します。
     */
    public function getPriority() {
        if ($this->isSubProcess === true) {
            throw new BadMethodCallException("this method is not allowed from sub process");
        }
        pcntl_getpriority($this->pid);
    }

    public function getPid() {
        return $this->pid;
    }

    protected function recivSocketParent() {
        if ($this->handler === NULL) {
            return;
        }
        $read = array($this->handler);
        $write = NULL;
        $except = NULL;
        try {
            if (stream_select($read, $write, $except, 0) > 0) {
                $data = unserialize(fgets($this->handler));
                $this->job->onHandledMessage($data, $this);
                $this->job->onHandledMessageAtParentProcess($data);
            }
        } catch (Exception $e) {
            self::logN($e->getMessage()); //when warning raised (this can happen if the system call is interrupted by an incoming signal)
        }
    }

    protected function recivSocketSub() {
        $data = unserialize(fgets($this->handler));
        $this->job->onHandledMessage($data, $this);
        $this->job->onHandledMessageAtSubProcess($data);
    }

    /**
     * ペアのプロセスへ$dataを送信します。
     * @param type $data
     */
    public function sendMessage($data) {
        if ($this->initialized) {
            $slData = serialize($data) . PHP_EOL;
            try {
                $r = fwrite($this->handler, $slData);
            } catch (Exception $e) {
                self::logN($e); //when warning raised (this can happen if the system call is interrupted by an incoming signal)
                return false;
            }
            if ($r === false) {
                return false;
            }
            $this->signal(self::sendMessageSignal);
            return $r;
        } else {
            return false;
        }
    }

    /**
     * このプロセスの実行をバックグラウンド処理として定義します。
     * メインプロセスでデストラクトされても、サブプロセスはkillされず、
     * バックグラウンドで処理を続行します。
     * @param bool enable 有効か無効
     */
    public function setBackGround($enable = true) {
        $this->setBackGroud = $enable;
    }

    protected function execute() {
        $this->job->run($this);
    }

    /**
     * サブプロセスの生死を確認します。
     * @return boolean
     */
    public function isAlive() {
        if ($this->pid === self::FORK_FAILED) { //フォーク失敗時は、死んでいることにする。
            return false;
        }
        if ($this->isSubProcess === true) {
            throw new BadMethodCallException("this method is not allowed from sub process");
        }
        $return = pcntl_waitpid($this->pid, $status, WNOHANG || WUNTRACED);
        if ($return === -1) {
            return false;
        } else if ($return === 0) {
            
        }
        if (pcntl_wifstopped($status)) {
            return false;
        }
        return true;
    }

    /**
     * 実行中のプロセスがサブプロセスであるかどうかを判定します。
     * @return type
     */
    public function isSubProcess() {
        return $this->isSubProcess;
    }

}
