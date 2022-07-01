# DTF DBS API Client

[![Tests](https://github.com/ybelenko/dtf-dbs-client/actions/workflows/main.yml/badge.svg)](https://github.com/ybelenko/dtf-dbs-client/actions/workflows/main.yml)
[![Coverage Status](https://coveralls.io/repos/github/ybelenko/dtf-dbs-client/badge.svg?branch=main)](https://coveralls.io/github/ybelenko/dtf-dbs-client?branch=main)

## Requirements
* PHP 7.4 or 8.x
* HTTP client(this readme describes Guzzle example, but you can use any other [PSR18](https://www.php-fig.org/psr/psr-18/) complaint package). Check these packages [https://packagist.org/providers/psr/http-client-implementation](https://packagist.org/providers/psr/http-client-implementation) if you need Guzzle alternative.

## Installation via [Composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-macos)
Run in command line:
```console
composer require ybelenko/dtf-dbs-client
```

## Setup
### Via [PHP-DI](https://php-di.org/doc/getting-started.html) container
```php
<?php
// config.dev.php
// contains sensitive data
// should be excluded from source base in .gitignore file
return [
    'DtfDbsApi.dealerId' => 'test01',
    'DtfDbsApi.clientId' => 'xxxxxxxxxxxxxxxxx',
    'DtfDbsApi.clientSecret' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    'DtfDbsApi.environment' => 'cert',// cert|qual|prod
];
```

```php
<?php
// config.php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\RequestOptions;
use Psr\Http\Client\ClientInterface;
use Ybelenko\DtfDbsClient\ApiClient;
use Ybelenko\DtfDbsClient\ApiClientConfig;

return [
    // Guzzle Client with DTF DBS API
    ClientInterface::class => \DI\autowire(Client::class)
        ->constructor([
            RequestOptions::HTTP_ERRORS => false,// important to handle non 2xx statuses properly
        ]),

    ApiClient::class => \DI\autowire(),
    ApiClientConfig::class => \DI\autowire()
        ->constructorParameter('requestFactory', \DI\create(HttpFactory::class))
        ->constructorParameter('uriFactory', \DI\create(HttpFactory::class))
        ->constructorParameter('streamFactory', \DI\create(HttpFactory::class))
        ->constructorParameter('dealerId', \DI\get('DtfDbsApi.dealerId'))
        ->constructorParameter('clientId', \DI\get('DtfDbsApi.clientId'))
        ->constructorParameter('clientSecret', \DI\get('DtfDbsApi.clientSecret'))
        ->constructorParameter('environment', \DI\get('DtfDbsApi.environment'))
        ->constructorParameter('authScope', 'dtf:dbs:file:write dtf:dbs:file:read'),
];
```

```php
<?php
// index.php
require_once(__DIR__ . '/vendor/autoload.php');

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

$builder = new ContainerBuilder();

// Main configuration
$builder->addDefinitions("config.php");

// Config file for the environment
$builder->addDefinitions("config.$environment.php");

/** @var ContainerInterface */
$container = $builder->build();
```

### Manual
```php
<?php
// index.php
require_once(__DIR__ . '/vendor/autoload.php');

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\RequestOptions;
use Ybelenko\DtfDbsClient\ApiClient;
use Ybelenko\DtfDbsClient\ApiClientConfig;

$client = new ApiClient(
    new ApiClientConfig(
        new Client([RequestOptions::HTTP_ERRORS => false]),// httpClient
        new HttpFactory(),// requestFactory 
        new HttpFactory(),// uriFactory
        new HttpFactory(),// streamFactory
        'xxxxxxxxxxxxxxxxx',// clientId 
        'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',// clientSecret 
        'test01',// dealerId 
        'cert',// environment cert|qual|prod 
        'dtf:dbs:file:write dtf:dbs:file:read'// scopes
    )
);

// ready to call API services
```
## API services
### File List Service
```php
// $client initialization omitted
try {
    /** @var array[] */
    $filesList = $client->callFileListService();
    // Approx shape [{"name": "order.dat", "links": [{"rel": "download", "href": "http:"}, {"rel": "details", "href": "http:"}]}, ...]
    foreach ($filesList as $file) {
        // do something
    }
} catch (\Throwable $e) {
    // echo or log exception for following investigation
}
```

### File Upload Service
```php
// $client initialization omitted
try {
    $factory = new \GuzzleHttp\Psr7\HttpFactory();
    $testFile = $factory->createStreamFromFile(__DIR__ . '/tests/samplecommonfile.txt', 'r');
    /** @var bool */
    $success = $client->callFileUploadService(
        $testFile, 
        null,// filename, optional
        true// overwrite param
    );
    if (!$success) {
        throw new \Exception('Unable to upload file');
    }
} catch (\Throwable $e) {
    // echo or log exception for following investigation
}
```

### File Download Service
```php
// $client initialization omitted
try {
    // can be retrieved from File List Service above
    $filename = 'samplecommonfile.txt';
    /** @var \Psr\Http\Message\StreamInterface */
    $fileStream = $client->callFileDownloadService($filename);
    $uploaded = new \GuzzleHttp\Psr7\UploadedFile($fileStream, $fileStream->getSize(), \UPLOAD_ERR_OK, $filename);
    $uploaded->moveTo(__DIR__ . '\/output\/' . $filename);
    // saved to output folder
} catch (\Throwable $e) {
    // echo or log exception for following investigation
}
```

## Author
[Yuriy Belenko](https://github.com/ybelenko)
