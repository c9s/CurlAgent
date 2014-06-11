<?php
namespace CurlAgent;

define('CRLF', "\r\n");
use ArrayAccess;


/**
 * new CurlException(msg, code);
 */
class CurlException extends Exception {

    // http://curl.haxx.se/libcurl/c/libcurl-errors.html
    public function __construct($ch) {
        parent::__construct(curl_error($ch), curl_errno($ch));
        curl_close($ch); // close and free the resource
    }
}

class CurlAgent implements ArrayAccess {

    public $throwException = true;

    public $cookieFile;

    public $sslVerifyhost = 0;

    public $sslVerifypeer = 0;

    public $followLocation = 1;

    public $receiveHeader = true;

    public $userAgent;

    public $proxy;

    public $proxyAuth;

    public $connectionTimeout = 30;

    public $failOnError = true;

    protected $_curlOptions = array();

    public function __construct() {
        $this->cookieFile = tempnam("/tmp", str_replace('\\','_',get_class($this)) . mt_rand());
    }

    /**
     * Set Proxy
     *
     * @param string $proxy this parameter is a string in 127.0.0.1:8888 format.
     */
    public function setProxy($proxy, $auth = null) {
        $this->proxy = $proxy;
        if ($auth) {
            $this->proxyAuth = $auth;
        }
    }

    public function setConnectionTimeout($secs) {
        $this->connectionTimeout = $secs;
    }

    protected function _createCurlInstance() {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_COOKIEJAR,  $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->sslVerifyhost);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->sslVerifypeer);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $this->followLocation);


        curl_setopt($ch, CURLOPT_FAILONERROR, $this->failOnError);

        // curl_setopt($this->curl, CURLOPT_WRITEFUNCTION, array($this, "curl_handler_recv")); 

        if ($this->connectionTimeout)  {
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectionTimeout );
        }

        if ( $this->proxy ) {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 0);
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
            if ( $this->proxyAuth ) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxyAuth);
            }
        }

        if ( $this->userAgent ) {
            curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent );
        }
        if ( $this->receiveHeader ) {
            curl_setopt($ch, CURLOPT_HEADER, true);
        }

        foreach( $this->_curlOptions as $k => $v) {
            curl_setopt($ch, $k, $v);
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


    public function get($url, $fields = array(), $headers = array() ) {
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
        $result = curl_exec($ch);
        if ( $result ) {
            if ( $this->receiveHeader ) {
                $ret = $this->_separateResponse($ch, $result);
                curl_close($ch);
                return $ret;
            }
            curl_close($ch);
            return $result;
        }
        // curl error code
        // int curl_errno($ch);
        if ( $this->throwException ) {
            throw new CurlException($ch);
        }

        curl_close($ch);
        return FALSE;
    }

    public function post($url, $fields = array() , $headers = array() ) {
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


    
    public function offsetSet($name,$value)
    {
        $this->_curlOptions[ $name ] = $value;
    }
    
    public function offsetExists($name)
    {
        return isset($this->_curlOptions[ $name ]);
    }
    
    public function offsetGet($name)
    {
        return $this->_curlOptions[ $name ];
    }
    
    public function offsetUnset($name)
    {
        unset($this->_curlOptions[$name]);
    }

}

