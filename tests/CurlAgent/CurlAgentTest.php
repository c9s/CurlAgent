<?php

class CurlAgentTest extends PHPUnit_Framework_TestCase
{

    /**
     * @expectedException CurlAgent\CurlException
     */
    public function testError() {
        $agent = new CurlAgent\CurlAgent;
        ok($agent);
        $response = $agent->get('http://does.not.exist');
    }

    public function testGet()
    {
        $agent = new CurlAgent\CurlAgent;
        ok($agent);

        $response = $agent->get('https://stackoverflow.com/questions/11297320/using-a-try-catch-with-curl-in-php');
        ok($response);
        ok($response->body);
        ok($response->headers);
        ok(is_array($response->headers));
    }

    public function testProxy() {
        $agent = new CurlAgent\CurlAgent;
        $agent->setProxy('106.187.96.49:3128');
        $response = $agent->get('https://stackoverflow.com/questions/11297320/using-a-try-catch-with-curl-in-php');
        ok($response);
        ok($response->body);
        ok($response->headers);
    }
}

