<?php
namespace UBike;
require "CurlAgent.php";

use Exception;

class UBikeException extends Exception { }

class UBikeAgentException extends Exception { }


/**
 * Definitions:
 *
 *    $CardNumber:    悠遊卡外碼
 *    $HexCardNumber: 悠遊卡內碼 (hex string)
 *    $DecCardNumber: 悠遊卡內碼 (long integer)
 *    $CardNumberHash: 悠遊卡 Hash
 *
 *
 *
 */




/*
    1:悠遊卡 
    2:台灣智慧卡 
    4:高雄一卡通 
    8:遠通e通卡 
    16: 一般rfid卡 
    32:關貿使用之rfid生活圈卡片 
    64:信用卡 
    128:smartpay  
    ...可向下擴充至 2^31
 */
class Card { 

    public $type; // easy card

    public $decNo = 0;


    /*
        s:卡片狀態: 
        0(已停用)
        2(製卡中)
        4(卡務註銷)
        A(損壞)
        B(遺失)
        C(休學)
        D(退轉學)
        E(畢業)
        F(停職)
        G(離職)
        H(停卡)
        I(回 收)
        J(退休)
        X(黑名單)
        Y(移除綁定)

        PS:
        0.黑名單無法恢復成其他狀態 或移除綁定
        1.只有 1 這種狀態是可以正常使用的卡片
        2.Y 會將資料真實移除,必須這麼做才能重新綁卡給其他人
     */
    public $status;

    public $index;


    /**
     * 外碼
     */
    public $externalId;

    public static function createEasyCardFromDecCardNo($decNo, $index = null) {
        $card = new self;
        $card->type = 1;
        $card->decNo = $decNo;
        $card->index = $index;
        return $card;

    }

    public static function createEasyCardFromHexCardNo($hexNo) {
        $card = new self;
        $card->type = 1;
        $card->decNo = hexdec($hexNo);
        $card->index = $index;
        return $card;
    }



    /**
     * 要注意 jsonObject->n 有時為內碼有時為 hashed card number
     */
    public static function createFromResponse($jsonObject) {
        $card = new self;
        if ( isset($jsonObject->t) ) {
            $card->type = $jsonObject->t;
        }
        if ( isset($jsonObject->i) ) {
            $card->index = $jsonObject->i;
        }
        if ( isset($jsonObject->e) ) { // external id
            $card->externalId = $jsonObject->e;
        }
        if ( isset($jsonObject->n) ) {
            $card->decNo = hexdec($jsonObject->n);
        }
        if ( isset($jsonObject->s) ) {
            $card->status = $jsonObject->s;
        }
        return $card;
    }


    public function asRequestParameter() {
        $ret = array(
            't' => $this->type,
        );
        if ($this->decNo) {
            $ret['n'] = dechex($this->decNo);
        }
        if ($this->status) {
            $ret['s'] = $this->status;
        }
        if ($this->index) {
            $ret['i'] = $this->index;
        }
        return $ret;
    }
}


/*
    000 未支援此函式
    100 無法正確取得sid,無法通過認證
    101 認證失敗,或;失敗次數超過系統原則(1分鐘5次)
    102 尚未取得授權
    103 沒有使用此函式的權限
    104 使用者權限已過期
    105 sid認證過期
    106 找不到查詢的資訊
    107 傳遞錯誤的資料結構
    108 型態規格不正確
    109 資訊已存在
    110 資訊不存在
    111 缺少必要屬性
    113 時間未到或逾期
    301 交易僅支援ssl
    302 服務不存在(ex. 移除服務)
    303 服務已存在(ex. 新增服務)
    304 內部錯誤(ex. 取得json資訊,但不符規格...)
    500 內部錯誤
 */
class UBikeAgent extends CurlAgent {
    private $key;
    private $apiUrl;


    /**
     * @var string The session ID, which is registered by apiLogin method
     */
    private $sessionId;

    /**
     * @var string Due Date of the session ID, which is registered by apiLogin method
     */
    private $dueDate;

    public $code;

    public $url;

    public $version = '02';

    public function __construct($apiHost, $key) {
        $this->key = $key;

        // https://apis_test.lifeplus.tw/api/
        $this->baseURL = 'https://' . $apiHost;
    }

    public function getSessionId() {
        return $this->sessionId;
    }

    public function setSessionId($sid) {
        $this->sessionId = $sid;
    }

    public function apiLogin($userId, $password) {
        $resp = $this->requestPost($this->baseURL . '/api/adminV2/apiLogin', 
            array(
                'userid' => $userId,
                'passwd' => $password,
                'ver' => $this->version 
            )
        );

        $ret = $this->_checkResponse($resp);
        if($ret->retCode == 0) {
            // TODO: return error
            return false;    
        } else {
            // session ID
            $this->sessionId = $ret->retVal;
            $ret = explode(':', $ret->retVal);
            $this->dueDate = $ret[1];
            return $this->sessionId;
        }
    }

    public function apiVersion() {
        $resp = $this->requestGet($this->baseURL . '/api/adminV2/apiVersion');
        $ret = $this->_checkResponse($resp);
        return $ret->retVal->v; // the version string "2.0.0"
    }

    public function checkSidDueDate() {
        return $this->dueDate > time();
    }


    /**
     * Return request code and login URL
     */
    public function requestCode($session) {
        $data = array(
            'key' => $this->key,
            'session' => $session
        );

        $resp = $this->requestPost($this->baseURL . '/api/ybConnection/requestCode', 
            array(
                'sid'  => $this->sessionId, 
                'data' => '[' . json_encode($data) . ']'
            )  
        );
        $ret = $resp['body'];

        if (!$ret || !$ret[0]) {
            return false;
        }

        $ret = $ret[0];
        if($ret->retCode == 0) {
            // TODO: return error
            return $ret;    
        } else {
            return $ret->retVal;
        }
    }

    /**
     * @param array $ret the returned code from "requestCode" API.
     */
    public function buildLoginUrl($ret) {
        // https://apis_test.lifeplus.tw/api/ybConnection/connectHost?data=[{"code":"6c94dc1b89b05fadf3d66e763b3aebe9"}]
        return $ret->url . '?' . http_build_query([
            'data' => [['code' => $ret->code]],
        ]);
    }



    protected function _checkSID() {
        if ( !$this->sessionId ) {
            throw new UBikeAgentException("sessionId is not set, please login first.");
        }
        if ( !$this->dueDate ) {
            throw new UBikeAgentException("dueDate is not set, please login first.");
        }
        return true;
    }



    /**
     * Check the JSON response from UBike, validate the retVal and retCode
     */
    protected function _checkResponse($response) {
        // UBike does not return response
        if ( !isset($response['body']) ) {
            throw new UBikeException;
        }
        $ret = $response['body'];
        if ( !$ret || !isset($ret[0]) ) {
            throw new UBikeException;
        }
        $ret = $ret[0];
        $ret->retCode;
        return $ret;
    }

    public function checkAuth($t, $uid, $code, $key, $token){
        $str = $t . "\t" . '===youbikeConnection' . $uid . ':' . $code . ':' . $key . 'youbikeConnection==';
        return sha1($str) == $token;
    }


    /************************************************
     * Cloud v2 related API (LIFEPLUS)
     *************************************************/
    public function addLifePlusCardInfo($uid, $cardTypeNo) {
        $this->_checkSID();

        // POST with sid=1a3e92d0ef:1321242359&data=[{"uid":"luke@asp.com.tw"}]
        $resp = $this->requestPost($this->baseURL . '/api/cloudV2/cardinfoFind_by_uid', 
            array(
                'sid'  => $this->sessionId, 
                'data' => '[' . json_encode([ 'uid' => $uid ]) . ']',
            )  
        );

        $ret = $this->_checkResponse($resp);

    }




    /**
     * 外碼查內碼
     *
     * 10/16位 查 hex 內碼
     */
    public function queryHexCardNumber($cardType, $cardNumber) {
        $this->_checkSID();

        // [{"function":"2","cardtypeno":[{"t":1,"e":"0123456789","n":"" {"t":1,"e":"2345678901","n":""}] }]
        $resp = $this->requestPost($this->baseURL . '/api/ubikeV2/registration', [
                'sid'  => $this->sessionId, 
                'data' => json_encode([
                    [
                        'function' => 2,
                        'cardtypeno' => [ [ 't' => $cardType, 'e' => $cardNumber, 'n' => '' ] ],
                    ]
                ]),
            ]);
        $ret = $this->_checkResponse($resp);

        /*
        class stdClass#186 (2) {
            public $retCode => int(1)
            public $retVal => array(1) {
                [0] => class stdClass#185 (3) {
                    public $e => string(9) "012345678"
                    public $n => string(1) "0"
                    public $t => int(1)
                }
            }
        }
        */
        return $ret;
    }

    public function findCardByHexCardNumber($hexCardNumber) {
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/cloudV2/cardinfoFind_by_cardno', [
                'sid'  => $this->sessionId, 
                'data' => json_encode([[ 'cardno' => $hexCardNumber ]]),
            ]);
        $ret = $this->_checkResponse($resp);
        /*
            retVal: { uid: ... , cardtypeno: [ {s: .., t:... , i:...}, .... ] }
         */
        // [{"retCode":1,"retVal":{"uid":"luke@asp.com.tw","ou":["/所屬群組1","/所屬群組2"],"cardno":["1","499602d1","2","d2029649"]}}]
        $cards = array();
        foreach ($ret->retVal->cardtypeno as $cardObj) {
            $cards[] = Card::createFromResponse($cardObj);
        }
        return $cards;

        // 

    }

    public function findCardByUId($uid) {
        $this->_checkSID();

        // POST with sid=1a3e92d0ef:1321242359&data=[{"uid":"luke@asp.com.tw"}]
        $resp = $this->requestPost($this->baseURL . '/api/cloudV2/cardinfoFind_by_uid', 
            array(
                'sid'  => $this->sessionId, 
                'data' => '[' . json_encode([ 'uid' => $uid ]) . ']',
            ));

        $ret = $this->_checkResponse($resp);

        /*
            retVal: { uid: ... , cardtypeno: [ {s: .., t:... , i:...}, .... ] }
         */
        // [{"retCode":1,"retVal":{"uid":"luke@asp.com.tw","ou":["/所屬群組1","/所屬群組2"],"cardno":["1","499602d1","2","d2029649"]}}]
        $cards = array();
        foreach ($ret->retVal->cardtypeno as $cardObj) {
            $cards[] = Card::createFromResponse($cardObj);
        }
        return $cards;
    }



    public function findMemberByCardNo($hexCardNumber) {
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/cloudV2/memberFind_by_cardno', array(
                'sid'  => $this->sessionId, 
                'data' => '[' . json_encode([ 'cardno' => $hexCardNumber ]) . ']',
            ));
        $ret = $this->_checkResponse($resp);
        return $ret;
    }

    public function findMemberByPhone($phoneNumber) {
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/cloudV2/memberFind_by_phone', array(
                'sid'  => $this->sessionId, 
                'data' => '[' . json_encode([ 'phone' => $phoneNumber ]) . ']',
            ));
        $ret = $this->_checkResponse($resp);
        return $ret;
    }




    /**
     * cardNumberHash: 此卡號並非 卡片內外碼任何一種,而是外碼查 內碼時系統所賦予經過hash的卡號40bytes或是內碼 使用cardnohash的結果,用於查詢 時,此欄位空白
     *
     * XXX: NOT IMPLEMENTED
     */
    public function ubikeBinding($phoneNumber, $cardNumberHash) {
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/ubikeV2/binding', array(
                'sid'  => $this->sessionId, 
                'data' => '[' . json_encode([ 'phone' => $phoneNumber, 'cardno' => $cardNumberHash ]) . ']',
            ));
        return $this->_checkResponse($resp);
    }



    /**
     *
     * @return Card Hash Number 
     */
    public function getCardNumberHash($hexCardNumber) {
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/ubikeV2/cardnohash', array(
                'sid'  => $this->sessionId, 
                'data' => '[' . json_encode([ 'cardno' => $hexCardNumber ]) . ']',
        ));
        $ret = $this->_checkResponse($resp);
        if ($ret->retCode == 0) {
            return null;
        }
        return $ret->retVal;
    }


    /**
     * @param $cardNumber 卡片外碼
     */
    public function checkUBikeByCardNumber($cardNumber) {
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/ubikeV2/cardno_check', array(
                'sid'  => $this->sessionId, 
                'data' => json_encode([
                    ['e' => $cardNumber, 'h' => '']
                ]),
        ));
        $ret = $this->_checkResponse($resp);
        if ($ret->retCode == 0) {
            return null;
        }
        return $ret->retVal->checked; // 'Y' or 'N' or 0 or -1
    }


    /**
     * @param $cardNumber 卡片外碼
     */
    public function checkUBikeByCardNumberHash($cardNumberHash) {
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/ubikeV2/cardno_check', array(
                'sid'  => $this->sessionId, 
                'data' => json_encode([
                    ['e' => '', 'h' => $cardNumberHash]
                ]),
        ));
        $ret = $this->_checkResponse($resp);
        if ($ret->retCode == 0) {
            return null;
        }
        return $ret->retVal->checked; // 'Y' or 'N' or 0 or -1
    }






    public function getUBikeTicket($args) {
        $this->_checkSID();
        /*
            [{
                "t":"bike0906",
                "starttime":"1377575220",
                "endtime":"1377575250",
                "custname":"雲端生活家股份有限公司",
                "rno":"T02",
                "count" :"10",
                "projectno":"proj001"
            }]
        */
        $resp = $this->requestPost($this->baseURL . '/api/ubikeTicket/getTickets', array(
                'sid'  => $this->sessionId, 
                'data' => json_encode([ $args ]),
        ));
        $ret = $this->_checkResponse($resp);
        if ($ret->retCode == 0) {
            throw new Exception($ret->retVal);
        }

        /*
        class stdClass#187 (2) {
            public $retCode => int(1)
            public $retVal =>
            array(3) {
                    [0] => string(10) "BFQUQ5WGPJ"
                    [1] => string(10) "IO10PTG9U7"
                    [2] => string(10) "E5XZ6GAMS0"
            }
        }
        */
        return $ret->retVal; // string array
    }


    /**
     *
     * 將多票券綁在手機上
     * [{"t":"bike0906","ticketno":["WJ11FNNQPQ","XTFRFNFL1Q"],"phone":"0930000306"}]
     *
     * 將多票券綁在卡號內碼上(16進制 big-endian 小寫)
     * [{"t":"bike0906","ticketno":["WJ11FNNQPQ","XTFRFNFL1Q"],"cardno":"aaff941a"}]
     *
     * 將多票券綁在卡號上（外碼）
     * [{"t":"bike0906","ticketno":["WJ11FNNQPQ","XTFRFNFL1Q"],"externalid":"1234567890"}]
     *
     * @param $args arguments
     */
    public function bindingUBikeTicket($args) {
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/ubikeTicket/bindingTicket', array(
                'sid'  => $this->sessionId, 
                'data' => json_encode([ $args ]),
        ));
        $ret = $this->_checkResponse($resp);
        if ($ret->retCode == 0) {
            throw new Exception($ret->retVal);
        }
        return $ret->retVal;
    }


    public function bindingUBikeTicketWithPhone($ticketType, $tickets, $phoneNumber) {
        return $this->bindingUBikeTicket([ "t" => $ticketType,"ticketno" => $tickets,"phone" => $phoneNumber ]);
    }

    public function bindingUBikeTicketWithHexCardNumber($ticketType, $tickets, $hexCardNumber) {
        return $this->bindingUBikeTicket([ "t" => $ticketType,"ticketno" => $tickets,"cardno" => $hexCardNumber ]);
    }

    public function bindingUBikeTicketWithCardNumber($ticketType, $tickets, $cardNumber) {
        return $this->bindingUBikeTicket([ "t" => $ticketType,"ticketno" => $tickets,"externalid" => $cardNumber ]);
    }


    /**
     *  內碼查詢外碼
     *
     *  成功                     [{"retCode":1,"retVal":{"extno":"1234567890"}}]
     *  失敗 (規格不正確)        [{"retCode":1,"retVal":{"extno":"-2"}}]
     *  失敗 (無法查詢到結果)    [{"retCode":1,"retVal":{"extno":"-1"}}]
     *  失敗 (查詢逾時)          [{"retCode":1,"retVal":{"extno":"0"}}]
     */
    public function getCardNumberByDecCardNumber($decCardNumber) {
        // https://apis_test.lifeplus.tw/api/ubikeV2/
        $this->_checkSID();
        $resp = $this->requestPost($this->baseURL . '/api/ubikeV2/GetExternalId', array(
                'sid'  => $this->sessionId, 
                'data' => json_encode([
                    [ 'cardno' => $decCardNumber ]
                ]),
        ));
        $ret = $this->_checkResponse($resp);
        if ($ret->retCode == 0) {
            throw new Exception($ret->retVal);
        }
        return $ret->retVal->extno;
    }


    // TODO: 11.  https://apis_test.lifeplus.tw/api/ubikeV2/cardno_check
    // TODO: 24.  https://apis_test.lifeplus.tw/api/ubikeV2/cardInfo
    // TODO:      https://apis_test.lifeplus.tw/api/ubikeV2/binding   // 綁卡程序(已是會員需要加入新的卡片)
    // TODO: 7.cardnohash

}




