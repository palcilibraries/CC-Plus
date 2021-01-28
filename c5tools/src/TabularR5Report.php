<?php
namespace ubfr\c5tools;

class TabularR5Report extends TabularReport
{

    public function __construct($spreadsheet)
    {
        $this->config = Config::forRelease('5');
        $this->release = '5';

        parent::__construct($spreadsheet);
    }
}