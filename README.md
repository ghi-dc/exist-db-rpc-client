# exist-db-rpc-client

A client that abstracts out the XML RPC calls for [eXist-db](http://exist-db.org/exist/apps/homepage/index.html).
Based on [php-eXist-db-Client](https://github.com/CuAnnan/php-eXist-db-Client), ported to [fxmlrpc](https://github.com/lstrojny/fxmlrpc).

For an overview over the available methods, check the [eXist-db XML-RPC API Developer's Guide](https://exist-db.org/exist/apps/doc/devguide_xmlrpc) as well as the [RpcAPI interface](https://github.com/eXist-db/exist/blob/develop/exist-core/src/main/java/org/exist/xmlrpc/RpcAPI.java).


## Usage

```php

$options = [
    'protocol' => 'http',
    'user' => 'guest',
    'password' => 'guest',
    'host' => 'localhost',
    'port' => '8080',
    'path' => '/exist/xmlrpc/',
];

$connection = new \ExistDbRpc\Client($options);

// simple RPC call
var_dump($connection->getVersion());

// actual query
$stmt = $connection->prepareQuery('for $someNode in collection("/SomeCollection")/someNodeName[./somePredicateAttribute=$someValueToBeBound] return $someNode');
$stmt->bindVariable('someValueToBeBound', '5');

$resultPool = $stmt->execute();
$results = $resultPool->getAllResults();

foreach($results as $result) {
    var_dump($result);
}

$resultPool->release();
```

## Return types

- Query::setStringReturnType()
    result is string

- Query::setSimpleXMLReturnType()
    result is instance of [SimpleXMLElement](https://www.php.net/manual/en/class.simplexmlelement.php)

- Query::setDOMReturnType()
    result is instance of [DOMElement](https://www.php.net/manual/en/class.domelement.php)

- Query::setJSONReturnType()
    result is instance of [array](https://www.php.net/manual/en/language.types.array.php)
