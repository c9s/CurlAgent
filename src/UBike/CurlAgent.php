<?php
namespace UBike;

define('CRLF', "\r\n");

class CurlAgent {

    public $cookieFile;

    public $sslVerifyhost = 0;

    public $sslVerifypeer = 0;

    public $followLocation = 1;

    public $receiveHeader = true;

    public $userAgent;

    public function __construct() {
        $this->cookieFile = tempnam("/tmp", str_replace('\\','_',get_class($this)) . mt_rand());
    }

    protected function _createCurlInstance() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_COOKIEJAR,  $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerifyhost);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerifypeer);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->followLocation);

        if ( $this->userAgent ) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent );
        }
        if ( $this->receiveHeader ) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }
        return $ch;
    }

    protected function _encodeFields(& $fields) {
        $fieldsString = '';
        foreach( $fields as $key => $value ) { 
            $fieldsString .= $key.'='. urlencode($value) .'&'; 
        }
        return rtrim($fieldsString, '&');
    }

    protected function _readResponseBody($response) {
        return explode( CRLF . CRLF, $response);
    }

    protected function _parseHttpHeader($headerBody) {
        $headers = array();
        $lines   = explode("\r\n", $headerBody);
        $status  = array_shift($lines);
        foreach( $lines as $line ) {
            if ( trim($line) ) {
                list($key, $value) = explode(': ', $line);
                $headers[strtolower($key)] = $value;
            }
        }
        return $headers;
    }

    protected function _separateResponse($ch, $response) {
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header     = substr($response, 0, $headerSize);
        $body       = substr($response, $headerSize);

        $headers = $this->_parseHttpHeader($header);

        if ( isset($headers['content-type']) ) {
            // Content-Type: application/json; charset=utf-8
            if ( strpos($headers['content-type'], 'application/json') !== false ) {
                // over-write the text body with our decoded json object
                $body = json_decode($body);
            }
        }


        return array(
            'body' => $body,
            'header' => $header,
        );
    }


    public function requestGet($url, $fields = array(), $headers = array() ) {
        $fieldsString = $this->_encodeFields($fields);

        if ( !empty($fields) ) {
            $url = $url . '?' . http_build_query($fields);
        }

        $ch = $this->_createCurlInstance();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
        curl_setopt($ch, CURLOPT_FAILONERROR, true); 

        $ret = null;
        if ( $response = curl_exec($ch) ) {
            if ( $this->receiveHeader ) {
                return $this->_separateResponse($ch, $response);
            }
            return $ret;
        }
        curl_close($ch);
        return $ret;
    }


    public function requestPost($url, $fields = array() , $headers = array() ) {
        $fieldsString = $this->_encodeFields($fields);
        $ch = $this->_createCurlInstance();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
        curl_setopt($ch, CURLOPT_FAILONERROR, true); 

        if ( ! empty($headers) ) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }


        $ret = null;
        if ( $response = curl_exec($ch) ) {
            if ( $this->receiveHeader ) {
                return $this->_separateResponse($ch, $response);
            }
            return $ret;
        }
        curl_close($ch);
        return $ret;
    }

    public function __destruct() {
        if( file_exists($this->cookieFile) ) {
            unlink($this->cookieFile);
        }
    }
}
