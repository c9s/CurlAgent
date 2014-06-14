<?php
namespace CurlAgent;

class CurlRequest
{

    /**
     * @var string the default request method
     */
    public $method = 'GET';

    public $parameters = array();


    /**
     * @var array header strings "field name" => "field value"
     *
     *     'Content-Type' => '...'
     *     'Accept' => 'text/xml'
     */
    public $headers = array();

    public $response;

    public function __construct($url, $method = 'GET', $parameters = array(), $headers = array() ) {
        $this->method = $method;
        $this->parameters = $parameters;
        $this->headers = $headers;
    }

    public function setHeaders(array $headers) {
        $this->headers = $headers;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function setResponse(CurlResponse $resp) {
        $this->response = $resp;
    }

    public function getResponse() {
        return $this->response;
    }

    public function getEncodedParameters() {
        return http_build_query($this->parameters);
    }

}

