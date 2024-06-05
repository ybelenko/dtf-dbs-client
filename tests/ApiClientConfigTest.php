<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\MultipartStream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Ybelenko\DtfDbsClient\ApiClientConfig;

/**
 * @coversDefaultClass \Ybelenko\DtfDbsClient\ApiClientConfig
 * @uses \Ybelenko\DtfDbsClient\ApiClient
 * @covers ::__construct
 * @covers ::getHttpClient
 * @covers ::setDealerId
 * @covers ::setAccessToken
 * @covers ::getAccessToken
 * @covers ::setAuthScope
 * @covers ::setClientId
 * @covers ::setClientSecret
 * @covers ::setEnvironment
 * @covers ::setDealerId
 * @covers ::buildFileListRequest
 * @covers ::getObtainTokenUrl
 * @covers ::getApiBasePathUrl
 *
 * @internal
 *
 * @small
 */
final class ApiClientConfigTest extends TestCase
{
    /**
     * @covers ::__construct
     * @dataProvider provideConstructorArguments
     */
    public function testConstructorAndSetters(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        string $clientId,
        string $clientSecret,
        string $dealerId,
        string $env,
        string $scopes
    ): void {
        $config = new ApiClientConfig($httpClient, $requestFactory, $uriFactory, $streamFactory, $clientId, $clientSecret, $dealerId, $env, $scopes);
        static::assertInstanceOf(ApiClientConfig::class, $config);
        static::assertInstanceOf(ClientInterface::class, $config->getHttpClient());
        $config->setDealerId('88888888');
        static::assertStringContainsString('88888888', (string)$config->buildFileListRequest()->getUri());
        $oldAccessToken = $config->getAccessToken();
        $config->setAccessToken('Test access token');
        $newAccessToken = $config->getAccessToken();
        static::assertNotSame($oldAccessToken, $newAccessToken);
        static::assertSame('Test access token', $newAccessToken);
    }

    public function provideConstructorArguments(): array
    {
        return [
            [
                new Client(),
                new HttpFactory(),
                new HttpFactory(),
                new HttpFactory(),
                'alladin',
                'openseasam',
                'test01',
                'prod',
                'dtf:dbs:file:write dtf:dbs:file:read'
            ]
        ];
    }

    /**
     * @covers ::setEnvironment
     */
    public function testSetEnvironmentException(): void
    {
        $config = $this->getConfigWithGuzzleClient();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DTF DBS API Client: Invalid environment argument, should be prod|cert|qual');
        // apply unknown environment
        $config->setEnvironment('foobar');
    }

    /**
     * @covers ::buildFileUploadRequest
     */
    public function testBuildFileUploadRequestException(): void
    {
        $config = $this->getConfigWithGuzzleClient();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('\$file argument must be path to file, resource or StreamInterface');
        $config->buildFileUploadRequest('foobar');
    }

    /**
     * @covers ::getObtainTokenUrl
     */
    public function testGetObtainTokenUrlException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DTF DBS API Client: Unknown environment to get OAuth token URL');
        ApiClientConfig::getObtainTokenUrl('foobar');
    }

    /**
     * @covers ::getApiBasePathUrl
     */
    public function testGetApiBasePathUrlException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('DTF DBS API Client: Unknown environment to get API base path');
        ApiClientConfig::getApiBasePathUrl('foobar');
    }

    /**
     * @covers ::getObtainTokenUrl
     * @dataProvider provideTokenUrls
     */
    public function testGetObtainTokenUrl(string $environment, string $expectedUrl): void
    {
        $url = ApiClientConfig::getObtainTokenUrl($environment);
        static::assertIsString($url);
        static::assertSame($expectedUrl, $url);
    }

    public function provideTokenUrls(): array
    {
        return [
            'qual' => [
                'qual',
                'https://sso-qual.johndeere.com/oauth2/ausi42oq38DYB06q50h7',
            ],
            'cert' => [
                'cert',
                'https://sso-cert.johndeere.com/oauth2/aus97etlxsNTFzHT11t7',
            ],
            'prod' => [
                'prod',
                'https://sso.johndeere.com/oauth2/aus9k0fb8kUjG8S5Z1t7',
            ],
        ];
    }

    /**
     * @covers ::getApiBasePathUrl
     * @dataProvider provideBasePathUrls
     */
    public function testGetApiBasePathUrl(string $environment, string $expectedUrl): void
    {
        $url = ApiClientConfig::getApiBasePathUrl($environment);
        static::assertIsString($url);
        static::assertSame($expectedUrl, $url);
    }

    public function provideBasePathUrls(): array
    {
        return [
            'qual' => [
                'qual',
                'https://dtfapiqual.deere.com',
            ],
            'cert' => [
                'cert',
                'https://dtfapicert.deere.com',
            ],
            'prod' => [
                'prod',
                'https://dtfapi.deere.com',
            ],
        ];
    }

    /**
     * @covers ::buildObtainTokenRequest
     * @dataProvider getAuthRequests
     */
    public function testBuildObtainTokenRequest(
        ApiClientConfig $config,
        string $expectedUrl,
        string $expectedAuthHeader,
        array $expectedBody
    ): void {
        $request = $config->buildObtainTokenRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('POST', $request->getMethod());
        static::assertSame($expectedUrl, (string)$request->getUri());
        static::assertTrue($request->hasHeader('Authorization'));
        static::assertCount(1, $request->getHeader('Authorization'));
        static::assertSame($expectedAuthHeader, $request->getHeader('Authorization')[0]);
        static::assertTrue($request->hasHeader('Content-Type'));
        static::assertCount(1, $request->getHeader('Content-Type'));
        static::assertSame('application/x-www-form-urlencoded', $request->getHeader('Content-Type')[0]);
        parse_str((string)$request->getBody(), $body);
        static::assertSame($expectedBody, $body);
    }

    public function getAuthRequests(): array
    {
        $guzzleFactory = new HttpFactory();
        $qualTokenUri = $guzzleFactory->createUri(ApiClientConfig::getObtainTokenUrl('qual'));
        $certTokenUri = $guzzleFactory->createUri(ApiClientConfig::getObtainTokenUrl('cert'));
        $prodTokenUri = $guzzleFactory->createUri(ApiClientConfig::getObtainTokenUrl('prod'));

        return [
            'qual' => [
                $this->getConfigWithGuzzleClient()
                    ->setClientId('username')
                    ->setClientSecret('password')
                    ->setEnvironment('qual')
                    ->setAuthScope('dtf:dbs:file:write'),
                (string)$qualTokenUri->withPath($qualTokenUri->getPath() . '/v1/token'),
                'Basic dXNlcm5hbWU6cGFzc3dvcmQ=',
                [
                    'grant_type' => 'client_credentials',
                    'scope' => 'dtf:dbs:file:write',
                ],
            ],
            'cert' => [
                $this->getConfigWithGuzzleClient()
                    ->setClientId('Aladdin')
                    ->setClientSecret('open sesame')
                    ->setEnvironment('cert'),
                (string)$certTokenUri->withPath($certTokenUri->getPath() . '/v1/token'),
                'Basic QWxhZGRpbjpvcGVuIHNlc2FtZQ==',
                [
                    'grant_type' => 'client_credentials',
                    'scope' => 'dtf:dbs:file:write dtf:dbs:file:read',
                ],
            ],
            'prod' => [
                $this->getConfigWithGuzzleClient()
                    ->setClientId('johndoe')
                    ->setClientSecret('Hello World!')
                    ->setEnvironment('prod')
                    ->setAuthScope('dtf:dbs:file:read'),
                (string)$prodTokenUri->withPath($prodTokenUri->getPath() . '/v1/token'),
                'Basic am9obmRvZTpIZWxsbyBXb3JsZCE=',
                [
                    'grant_type' => 'client_credentials',
                    'scope' => 'dtf:dbs:file:read',
                ],
            ],
        ];
    }

    /**
     * @covers ::buildFileUploadRequest
     * @dataProvider getFileUploadRequests
     */
    public function testBuildFileUploadRequest(
        ApiClientConfig $config,
        $file,
        ?string $fileName,
        ?bool $overWrite,
        string $expectedUrl,
        string $expectedAuthHeader,
        $expectedFilePart
    ): void {
        $request = $config->buildFileUploadRequest($file, $fileName, $overWrite);
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('PUT', $request->getMethod());
        // check headers
        static::assertSame($expectedUrl, (string)$request->getUri());
        static::assertTrue($request->hasHeader('Authorization'));
        static::assertSame($expectedAuthHeader, $request->getHeaderLine('Authorization'));
        static::assertTrue($request->hasHeader('Content-Type'));
        static::assertMatchesRegularExpression('/^multipart\/form-data; boundary=(.+)$/', $request->getHeaderLine('Content-Type'));
        // parse random boundary for next assert
        $boundary = preg_replace('/^multipart\/form-data; boundary=(.+)$/', '$1', $request->getHeader('Content-Type')[0]);

        // build multi-part body with Guzzle factory
        $guzzleStream = new MultipartStream(
            [[
                'name' => 'file',
                'contents' => $expectedFilePart,
            ]],
            $boundary
        );
        static::assertSame((string)$guzzleStream, (string)$request->getBody());
    }

    public function getFileUploadRequests(): array
    {
        $accessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $guzzleFactory = new HttpFactory();

        return [
            'Hello World! path as file' => [
                $this->getConfigWithGuzzleClient()->setAccessToken($accessToken),
                __DIR__ . '/samplecommonfile.txt',
                null,
                null,
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/test01/files'),
                "Bearer {$accessToken}",
                $guzzleFactory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
            ],
            'Hello World! resource as file' => [
                $this->getConfigWithGuzzleClient()->setAccessToken($accessToken),
                \fopen(__DIR__ . '/samplecommonfile.txt', 'r'),
                null,
                null,
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/test01/files'),
                "Bearer {$accessToken}",
                $guzzleFactory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
            ],
            'Hello World! Stream as file' => [
                $this->getConfigWithGuzzleClient()->setAccessToken($accessToken),
                $guzzleFactory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
                null,
                null,
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/test01/files'),
                "Bearer {$accessToken}",
                $guzzleFactory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
            ],
            'with fileName query option' => [
                $this->getConfigWithGuzzleClient()->setAccessToken($accessToken),
                __DIR__ . '/samplecommonfile.txt',
                'Screen Recording at 2022-04-01 12:34:56.mp4',
                null,
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/test01/files')
                    ->withQuery(\http_build_query(['fileName' => 'Screen Recording at 2022-04-01 12:34:56.mp4'])),
                "Bearer {$accessToken}",
                $guzzleFactory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
            ],
            'with overwrite query option' => [
                $this->getConfigWithGuzzleClient()->setAccessToken($accessToken),
                __DIR__ . '/samplecommonfile.txt',
                null,
                true,
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/test01/files')
                    ->withQuery(\http_build_query(['overWrite' => 'True'])),
                "Bearer {$accessToken}",
                $guzzleFactory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
            ],
            'with both overwrite and fileName query options' => [
                $this->getConfigWithGuzzleClient()->setAccessToken($accessToken),
                __DIR__ . '/samplecommonfile.txt',
                'Screen Recording at 2022-04-01 12:34:56.mp4',
                true,
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/test01/files')
                    ->withQuery(\http_build_query([
                        'fileName' => 'Screen Recording at 2022-04-01 12:34:56.mp4',
                        'overWrite' => 'True'
                    ])),
                "Bearer {$accessToken}",
                $guzzleFactory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
            ],
        ];
    }

    /**
     * @covers ::buildFileListRequest
     * @dataProvider getFileListRequests
     */
    public function testBuildFileListRequest(
        ApiClientConfig $config,
        string $expectedUrl,
        string $expectedAuthHeader,
        string $expectedAcceptHeader
    ): void {
        $request = $config->buildFileListRequest();
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
        // check headers
        static::assertSame($expectedUrl, (string)$request->getUri());
        static::assertTrue($request->hasHeader('Authorization'));
        static::assertSame($expectedAuthHeader, $request->getHeaderLine('Authorization'));
        static::assertTrue($request->hasHeader('Accept'));
        static::assertSame($expectedAcceptHeader, $request->getHeaderLine('Accept'));
    }

    public function getFileListRequests(): array
    {
        $accessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $guzzleFactory = new HttpFactory();

        return [
            'Dealer test01, env is cert' => [
                $this->getConfigWithGuzzleClient()
                    ->setDealerId('test01')
                    ->setEnvironment('cert')
                    ->setAccessToken($accessToken),
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('cert'))
                    ->withPath('/dbs/dealer/test01/files'),
                "Bearer {$accessToken}",
                'application/json',
            ],
            'Dealer 06DV01, env is prod' => [
                $this->getConfigWithGuzzleClient()
                    ->setDealerId('06DV01')
                    ->setEnvironment('prod')
                    ->setAccessToken($accessToken),
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/06DV01/files'),
                "Bearer {$accessToken}",
                'application/json',
            ],
        ];
    }

    /**
     * @covers ::buildFileDownloadRequest
     * @dataProvider getFileDownloadRequests
     */
    public function testBuildFileDownloadRequest(
        ApiClientConfig $config,
        string $filename,
        string $expectedUrl,
        string $expectedAuthHeader,
        string $expectedAcceptHeader
    ): void {
        $request = $config->buildFileDownloadRequest($filename);
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
        // check headers
        static::assertSame($expectedUrl, (string)$request->getUri());
        static::assertTrue($request->hasHeader('Authorization'));
        static::assertSame($expectedAuthHeader, $request->getHeaderLine('Authorization'));
        static::assertTrue($request->hasHeader('Accept'));
        static::assertSame($expectedAcceptHeader, $request->getHeaderLine('Accept'));
    }

    public function getFileDownloadRequests(): array
    {
        $accessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $guzzleFactory = new HttpFactory();

        return [
            'Dealer test01, env is cert' => [
                $this->getConfigWithGuzzleClient()
                    ->setDealerId('test01')
                    ->setEnvironment('cert')
                    ->setAccessToken($accessToken),
                'samplecommonfile.txt',
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('cert'))
                    ->withPath('/dbs/dealer/test01/files/samplecommonfile.txt'),
                "Bearer {$accessToken}",
                'application/json',
            ],
            'Dealer 06DV01, env is prod' => [
                $this->getConfigWithGuzzleClient()
                    ->setDealerId('06DV01')
                    ->setEnvironment('prod')
                    ->setAccessToken($accessToken),
                'Screen Recording at 2022-04-01 12:34:56.mp4',
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/06DV01/files/' . \rawurlencode('Screen Recording at 2022-04-01 12:34:56.mp4')),
                "Bearer {$accessToken}",
                'application/json',
            ],
        ];
    }

    /**
     * @covers ::buildGetFileDetailsRequest
     * @dataProvider getFileDetailsRequests
     */
    public function testBuildGetFileDetailsRequest(
        ApiClientConfig $config,
        string $filename,
        string $expectedUrl,
        string $expectedAuthHeader,
        string $expectedAcceptHeader
    ): void {
        $request = $config->buildGetFileDetailsRequest($filename);
        static::assertInstanceOf(RequestInterface::class, $request);
        static::assertSame('GET', $request->getMethod());
        // check headers
        static::assertSame($expectedUrl, (string)$request->getUri());
        static::assertTrue($request->hasHeader('Authorization'));
        static::assertSame($expectedAuthHeader, $request->getHeaderLine('Authorization'));
        static::assertTrue($request->hasHeader('Accept'));
        static::assertSame($expectedAcceptHeader, $request->getHeaderLine('Accept'));
    }

    public function getFileDetailsRequests(): array
    {
        $accessToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c';
        $guzzleFactory = new HttpFactory();

        return [
            'Dealer test01, env is cert' => [
                $this->getConfigWithGuzzleClient()
                    ->setDealerId('test01')
                    ->setEnvironment('cert')
                    ->setAccessToken($accessToken),
                'samplecommonfile.txt',
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('cert'))
                    ->withPath('/dbs/dealer/test01/files/samplecommonfile.txt/details'),
                "Bearer {$accessToken}",
                'application/json',
            ],
            'Dealer 06DV01, env is prod' => [
                $this->getConfigWithGuzzleClient()
                    ->setDealerId('06DV01')
                    ->setEnvironment('prod')
                    ->setAccessToken($accessToken),
                'Screen Recording at 2022-04-01 12:34:56.mp4',
                (string)$guzzleFactory->createUri(ApiClientConfig::getApiBasePathUrl('prod'))
                    ->withPath('/dbs/dealer/06DV01/files/' . \rawurlencode('Screen Recording at 2022-04-01 12:34:56.mp4')) . '/details',
                "Bearer {$accessToken}",
                'application/json',
            ],
        ];
    }

    protected function getConfigWithGuzzleClient(): ApiClientConfig
    {
        return new ApiClientConfig(
            new Client(),
            new HttpFactory(),
            new HttpFactory(),
            new HttpFactory(),
            'alladin',
            'openseasam',
            'test01',
            'prod',
            'dtf:dbs:file:write dtf:dbs:file:read'
        );
    }
}
