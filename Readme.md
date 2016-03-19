# YniProcess

PHPマルチプロセス用ライブラリ

PHP > 5.3
pcntl_ 系関数が使えること

composerでインストール

	composer.json

	{
        "repositories": [
            {
                "type": "vcs",
                "url": "https://github.com/yni3/YniProcess.git"
            },
        ],
        "require": {
            "yni3/YniProcess" : "dev-master",
        },
    }

使用法

インターフェースYniRunnableを実装し、YniProcess生成時に渡すと、実装したコールバックがサブプロセスで実行されます。

	class Test extends \YniProcess\YniBase implements \YniProcess\YniRunnable {

        public function __construct($i) {
        }

        public function onHandledMessage($message_data, \YniProcess\YniProcess $recivedObject) 
        {
			//プロセスメッセージを受け取った
        }

        public function onHandledMessageAtParentProcess($message_data) {
            //親プロセスで子からメッセージを受け取った
        }

        public function onHandledMessageAtSubProcess($message_data) {
            //子プロセスで親からメッセージを受け取った
        }

        public function run(\YniProcess\YniProcess $parent) {
            //サブプロセスで実行する内容
        }

	}
    
    //コンストラクトした瞬間にforkが切られ、実装したrun()が走ります。
    $proc = new YniProcess\YniProcess(new Test());
    
    //終了するまで待つ
    while ($proc->isAlive()) {
	}



