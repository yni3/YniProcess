<?php

namespace YniProcess;

use YniProcess\YniProcess;

/**
 *
 * @author yni3
 */
interface YniRunnable {

    /**
     * サブプロセスでの実行内容
     * @param \YniLibrary\YniCore\Process\YniProcess $parent
     */
    public function run(YniProcess $parent);

    /**
     * メッセージ受信時の挙動
     * サブでも、メインでもどちらでも受ける。
     * @param type $message_data
     * @param \YniLibrary\YniCore\Process\YniProcess $recivedObject
     */
    public function onHandledMessage($message_data, YniProcess $recivedObject);

    /**
     * サブプロセスからの通知を親が受け取ったときのコールバック\n
     * これは、親のプロセス
     * @param type $message_data
     */
    public function onHandledMessageAtSubProcess($message_data);

    /**
     * 親プロセスからのメッセージを受け取ったときのコールバック\n
     * これは、サブプロセス
     * @param type $message_data
     */
    public function onHandledMessageAtParentProcess($message_data);
}

