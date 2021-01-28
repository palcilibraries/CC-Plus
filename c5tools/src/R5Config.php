<?php
namespace ubfr\c5tools;

class R5Config extends Config
{

    public function __construct()
    {
        $this->readConfig(implode(DIRECTORY_SEPARATOR, [
            dirname(__FILE__),
            'config',
            'r5'
        ]));
    }
}
