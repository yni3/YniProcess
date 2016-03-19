<?php

namespace YniProcess;

use YniProcess\YniBaseInterface;

/**
 * 全基底クラス<br>
 *
 * @author yni3
 */
abstract class YniBase implements YniBaseInterface {

    private $object_id;
    private $object_id_got = false;

    public function getObjectId() {
        if ($this->object_id_got === false) {
            $this->object_id = spl_object_hash($this);
            $this->object_id_got = true;
        }
        return $this->object_id;
    }

}
