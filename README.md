CurlAgent
=====================

Simple Curl interface that allows you to operate HTTP requests / responses easily.

```php
$agent = new CurlAgent\CurlAgent;
try {
    $response = $agent->get('http://does.not.exist');

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
