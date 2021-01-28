<?php
namespace ubfr\c5tools;

class JsonR5Report extends JsonReport
{

    public function __construct($json)
    {
        $this->config = Config::forRelease('5');
        $this->release = '5';

        parent::__construct($json);
    }
}
