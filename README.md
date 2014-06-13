CurlAgent
=====================

Simple Curl interface that allows you to operate HTTP requests / responses easily.

```php
$agent = new CurlAgent\CurlAgent;
try {

    // simply send POST and GET request
    $response = $agent->post('http://does.not.exist');
    $response = $agent->get('http://does.not.exist');

    // send GET and POST with parameters and headers
    $response = $agent->get('http://does.not.exist', [ 'param1' => 'value' ], [ 'accept: text/xml', 'content-type: text/xml;' ]);
    $response = $agent->post('http://does.not.exist', [ 'name' => 'value' ], [ 'accept: text/xml' , ... ]);


    $response = $agent->get('http://does.not.exist', [ 'param1' => 'value' ], [ 'accept: text/xml', 'content-type: text/xml;' ]);
    $response = $agent->post('http://does.not.exist', [ 'name' => 'value' ], [ 'accept: text/xml' , ... ]);


    $response; // CurlResponse object

    $response->body; // raw response body


    $headers = $response->headers;
    foreach ($headers as $field => $value) {
        //....
    }

    // decode body based on the content-type of the response. currently we only support application/json and text/json
    $ret = $response->decodeBody();

} catch ( CurlException $e ) {
    // handle exception here
}
```
