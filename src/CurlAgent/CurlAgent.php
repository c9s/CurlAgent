<?php
namespace CurlAgent;
use ArrayAccess;
use Exception;

define('CRLF', "\r\n");

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



    protected function _handleCurlError($ch) {
        if ( $this->throwException ) {
            // the CurlException close the curl response automatically
            throw new CurlException($ch);
        }
        return FALSE;
    }

    protected function _handleCurlResponse($ch, $rawResponse) {
        $ret = null;
        if ($rawResponse) {
            if ( $this->receiveHeader ) {
                $ret = CurlResponse::createFromRawResponse($ch, $rawResponse);
            } else {
                $ret = new CurlResponse($rawResponse);
            }

            if (getenv('DEBUG_RESPONSE')) {
                echo "RESPONSE:\n";
                print_r($ret->decodeBody());
            }

        } else {
            $ret = $this->_handleCurlError($ch);
        }



        curl_close($ch);
        return $ret;
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

        curl_setopt($ch, CURLINFO_HEADER_OUT, true );

        if ( $this->proxy ) {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
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

    protected function _separateResponse($ch, $rawResponse) {
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $rawHeader  = substr($rawResponse, 0, $headerSize);
        $body       = substr($rawResponse, $headerSize);
        return new CurlResponse($body, $rawHeader);
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
        if ( getenv('DEBUG_REQUEST') ) {
            echo "REQUEST:\n";
            print_r($fields);
        }
        return $this->executeRequest($ch);
    }

    public function post($url, $fields = array() , $headers = array() ) {
        $fieldsString = $this->_encodeFields($fields);
        $ch = $this->_createCurlInstance();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, count($fields));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
        curl_setopt($ch, CURLOPT_FAILONERROR, true); 

        if ( getenv('DEBUG_REQUEST') ) {
            echo "REQUEST:\n";
            print_r($fields);
        }
        if ( ! empty($headers) ) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        return $this->executeRequest($ch);
    }


    public function executeRequest($ch) {
        $rawResponse = curl_exec($ch);
        return $this->_handleCurlResponse($ch, $rawResponse);
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

