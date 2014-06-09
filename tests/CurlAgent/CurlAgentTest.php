<?php

class CurlAgentTest extends PHPUnit_Framework_TestCase
{
    public function test()
    {
        $agent = new CurlAgent\CurlAgent;
        ok($agent);
    }
}

