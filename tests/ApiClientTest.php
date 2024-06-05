<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Ybelenko\DtfDbsClient\Exceptions\ApiErrorException;
use Ybelenko\DtfDbsClient\Exceptions\HttpClientException;
use Ybelenko\DtfDbsClient\Exceptions\OktaAuthErrorException;
use Ybelenko\DtfDbsClient\Exceptions\UnsupportedResponseException;

/**
 * @coversDefaultClass \Ybelenko\DtfDbsClient\ApiClient
 * @uses \Ybelenko\DtfDbsClient\ApiClientConfig
 * @uses \Ybelenko\DtfDbsClient\Exceptions\ApiErrorException
 * @uses \Ybelenko\DtfDbsClient\Exceptions\BaseRequestException
 * @uses \Ybelenko\DtfDbsClient\Exceptions\HttpClientException
 * @uses \Ybelenko\DtfDbsClient\Exceptions\OktaAuthErrorException
 * @uses \Ybelenko\DtfDbsClient\Exceptions\UnsupportedResponseException
 * @covers ::__construct
 * @covers ::getConfig
 * @covers ::callFileListService
 * @covers ::processHttpRequest
 * @covers ::obtainAccessToken
 * @covers ::decodeApiResult
 * @covers ::decodeTokenResult
 *
 * @internal
 *
 * @small
 */
final class ApiClientTest extends TestCase
{
    /**
     * @covers ::callFileUploadService
     * @dataProvider provideFileUploadArguments
     */
    public function testCallFileUploadService(
        ApiClientConfig $config,
        $file,
        ?string $fileName = null,
        ?bool $overWrite = null
    ): void {
        $apiClient = new ApiClient($config);
        $apiClient->getConfig()->setAccessToken('foobar'); // add fake token to skip token request
        static::assertTrue($apiClient->callFileUploadService($file, $fileName, $overWrite));
    }

    public function provideFileUploadArguments(): array
    {
        return [
            'file as path' => [
                $this->getConfigWithGuzzleClient([$this->getFileUploadResponse()]),
                __DIR__ . '/samplecommonfile.txt',
            ],
            'file as resource' => [
                $this->getConfigWithGuzzleClient([$this->getFileUploadResponse()]),
                fopen(__DIR__ . '/samplecommonfile.txt', 'r'),
            ],
            'file as StreamInterface' => [
                $this->getConfigWithGuzzleClient([$this->getFileUploadResponse()]),
                (new HttpFactory())->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
            ],
        ];
    }

    /**
     * @covers ::callFileUploadService
     * @dataProvider provideFileUploadErrorResponses
     */
    public function testCallFileUploadServiceExceptions(
        ApiClientConfig $config,
        $file,
        string $expectedException,
        int $expectedExceptionCode,
        string $expectedExceptionMessage
    ) {
        $this->expectException($expectedException);
        $this->expectExceptionCode($expectedExceptionCode);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $apiClient = new ApiClient($config);
        static::assertFalse($apiClient->callFileUploadService($file));
    }

    public function provideFileUploadErrorResponses(): array
    {
        $factory = new HttpFactory();

        return [
            '409 Conflict.' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getUnauthorizedResponse(),
                        $this->getTokenResponse(),
                        $this->getErrorResponse('http://', 409),
                    ]
                ),
                $factory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'),
                ApiErrorException::class,
                409,
                'The specified foobar.eame already exists',
            ],
        ];
    }

    /**
     * @covers ::callFileListService
     * @dataProvider provideFileListErrorResponses
     */
    public function testCallFileListServiceExceptions(
        ApiClientConfig $config,
        string $expectedException,
        int $expectedExceptionCode,
        string $expectedExceptionMessage
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionCode($expectedExceptionCode);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $apiClient = new ApiClient($config);
        $apiClient->callFileListService();
    }

    public function provideFileListErrorResponses(): array
    {
        $factory = new HttpFactory();

        return [
            'Guzzle non 2xx response with http_errors => true' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        new BadResponseException('Error Communicating with Server', $factory->createRequest('GET', 'test'), $factory->createResponse()),
                    ]
                ),
                HttpClientException::class,
                500,
                'HTTP request failed due non 2xx status code or other standard violations.',
            ],
            'Guzzle network error' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        new ConnectException('Error Communicating with Server', $factory->createRequest('GET', 'test')),
                    ]
                ),
                HttpClientException::class,
                500,
                'HTTP request failed due network issues, check internet connection.',
            ],
            'Guzzle other exceptions' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        new TransferException('Error Communicating with Server'),
                    ]
                ),
                HttpClientException::class,
                500,
                'HTTP request failed for some unspecified reason.',
            ],
            'Runtime exception during HTTP call' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        new \RuntimeException('Something wrong happened'),
                    ]
                ),
                HttpClientException::class,
                500,
                'General error occurred during HTTP request.',
            ],
            'OAuth2 invalid grant response' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getOktaAuthUnsupportedGrantResponse(),
                    ]
                ),
                OktaAuthErrorException::class,
                400,
                'unsupported_grant_type: The authorization grant type is not supported by the authorization server. Configured grant types: [client_credentials].',
            ],
            'OAuth2 wrong credentials' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getOktaAuth401Response(),
                    ]
                ),
                OktaAuthErrorException::class,
                401,
                'invalid_client: Invalid value for \'client_id\' parameter.',
            ],
            'Wrong content-type during token signing' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $factory->createResponse(200)->withHeader('Content-Type', 'text/xml'),
                    ]
                ),
                UnsupportedResponseException::class,
                500,
                'Provided response isn\'t JSON content type. text/xml',
            ],
            'Response isn\'t assoc array' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse()->withBody(
                            $factory->createStream(\json_encode(true))
                        ),
                    ]
                ),
                UnsupportedResponseException::class,
                500,
                'Invalid response, assoc array expected',
            ],
            'Non-standard OAuth token body' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse()->withBody(
                            $factory->createStream(\json_encode(['foobar' => 'foobaz']))
                        ),
                    ]
                ),
                UnsupportedResponseException::class,
                500,
                'Body doesn\'t contain "access_token" field',
            ],
            'Malformed JSON' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse()->withBody(
                            $factory->createStream("{'Organization': 'PHP Documentation Team'}")
                        ),
                    ]
                ),
                UnsupportedResponseException::class,
                500,
                'Cannot parse response body. Malformed JSON',
            ],
            'Wrong content-type of API response' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $factory->createResponse(200)->withHeader('Content-Type', 'text/plain'),
                    ]
                ),
                UnsupportedResponseException::class,
                500,
                'Provided response isn\'t JSON content type. text/plain',
            ],
            'Response from API isn\'t assoc array' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getTokenResponse()->withBody(
                            $factory->createStream(\json_encode(true))
                        ),
                    ]
                ),
                UnsupportedResponseException::class,
                500,
                'Invalid response, assoc array expected',
            ],
            'Missing fields of API response' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getTokenResponse()->withBody(
                            $factory->createStream(\json_encode(['foobar' => 'foobaz']))
                        ),
                    ]
                ),
                UnsupportedResponseException::class,
                500,
                'No required field "files" in response body',
            ],
            'Malformed JSON of API response.' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getTokenResponse()->withBody(
                            $factory->createStream('foobar: foobaz')
                        ),
                    ]
                ),
                UnsupportedResponseException::class,
                500,
                'Cannot parse response body. Malformed JSON',
            ],
            '401 unauthorized during request.' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getUnauthorizedResponse(), // then client tries to obtain token one more time
                        $this->getTokenResponse(),
                        $this->getUnauthorizedResponse(),
                    ]
                ),
                ApiErrorException::class,
                401,
                'Server: 1012100 - Credentials invalid or missing.',
            ],
            'Bad request API response.' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getErrorResponse('http://', 409),
                    ]
                ),
                ApiErrorException::class,
                409,
                'The specified foobar.eame already exists',
            ],
        ];
    }

    /**
     * @covers ::callFileDownloadService
     * @dataProvider provideFileDownloadResponses
     */
    public function testCallFileDownloadService(
        ApiClientConfig $config,
        string $filename
    ) {
        $apiClient = new ApiClient($config);
        $apiClient->getConfig()->setAccessToken('foobar'); // add fake token to skip token request
        $stream = $apiClient->callFileDownloadService($filename);
        static::assertInstanceOf(StreamInterface::class, $stream);
    }

    public function provideFileDownloadResponses(): array
    {
        return [
            'Content-Type: application/octet-stream' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getFileDownloadResponse(),
                    ]
                ),
                'foobar.dat',
            ],
        ];
    }

    /**
     * @covers ::callFileDownloadService
     * @dataProvider provideDownloadFileErrorResponses
     */
    public function testCallFileDownloadServiceExceptions(
        ApiClientConfig $config,
        string $filename,
        string $expectedException,
        int $expectedExceptionCode,
        string $expectedExceptionMessage
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionCode($expectedExceptionCode);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $apiClient = new ApiClient($config);
        $apiClient->callFileDownloadService($filename);
    }

    public function provideDownloadFileErrorResponses(): array
    {
        $factory = new HttpFactory();

        return [
            '404 not found' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getUnauthorizedResponse(),
                        $this->getTokenResponse(),
                        $this->getErrorResponse('location', 404)
                            ->withBody($factory->createStream(\json_encode([
                                'timestamp' => '2022-04-27 15:39',
                                'error' => 'FileNotFound',
                                'message' => 'Specified file does not exist.',
                                'path' => '/dbs/dealer/test01/files',
                                'status' => 404,
                            ]))),
                    ]
                ),
                'foobar.dat',
                ApiErrorException::class,
                404,
                'Specified file does not exist.',
            ],
        ];
    }

    /**
     * @covers ::callGetFileDetailsService
     * @dataProvider provideFileDetailsResponses
     */
    public function testCallGetFileDetailsService(
        ApiClientConfig $config,
        string $filename,
        array $expectedResult
    ) {
        $apiClient = new ApiClient($config);
        $apiClient->getConfig()->setAccessToken('foobar'); // add fake token to skip token request
        $result = $apiClient->callGetFileDetailsService($filename);
        static::assertIsArray($result);
        static::assertSame($expectedResult, $result);
    }

    public function provideFileDetailsResponses(): array
    {
        return [
            'File details' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getFileDetailsResponse(),
                    ]
                ),
                'samplecommonfile.txt',
                \json_decode((string)$this->getFileDetailsResponse()->getBody(), true),
            ],
        ];
    }

    /**
     * @covers ::callGetFileDetailsService
     * @dataProvider provideFileDetailsErrorResponses
     */
    public function testCallGetFileDetailsServiceExceptions(
        ApiClientConfig $config,
        string $filename,
        string $expectedException,
        int $expectedExceptionCode,
        string $expectedExceptionMessage
    ): void {
        $this->expectException($expectedException);
        $this->expectExceptionCode($expectedExceptionCode);
        $this->expectExceptionMessage($expectedExceptionMessage);

        $apiClient = new ApiClient($config);
        $apiClient->callGetFileDetailsService($filename);
    }

    public function provideFileDetailsErrorResponses(): array
    {
        $factory = new HttpFactory();

        return [
            '404 not found' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getUnauthorizedResponse(),
                        $this->getTokenResponse(),
                        $this->getErrorResponse('location', 404)
                            ->withBody($factory->createStream(\json_encode([
                                'timestamp' => '2022-04-27 15:39',
                                'error' => 'FileNotFound',
                                'message' => 'Specified file does not exist.',
                                'path' => '/dbs/dealer/test01/files',
                                'status' => 404,
                            ]))),
                    ]
                ),
                'foobar.dat',
                ApiErrorException::class,
                404,
                'Specified file does not exist.',
            ],
        ];
    }

    /**
     * @covers ::obtainAccessToken
     * @dataProvider provideConfigToTestAuth
     */
    public function testObtainAccessToken(
        ApiClientConfig $config,
        string $accessToken,
        ResponseInterface $expectedResponse
    ): void {
        // try any request, obtain token is the first step anyway
        $apiClient = new ApiClient($config);
        $result = $apiClient->callFileListService();
        static::assertSame($accessToken, $apiClient->getConfig()->getAccessToken());
        static::assertSame(\json_decode((string)$expectedResponse->getBody(), true)['files'], $result);
    }

    public function provideConfigToTestAuth(): array
    {
        return [
            'Check access token refresh' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getTokenResponse(),
                        $this->getFileListEmptyResponse(),
                    ]
                ),
                'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',
                $this->getFileListEmptyResponse(),
            ],
            'Don\'t refresh token when already defined' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getFileListFromDocResponse(),
                        $this->getFileListFromDocResponse(),
                    ]
                )->setAccessToken('foobar'),
                'foobar',
                $this->getFileListFromDocResponse(),
            ],
            'Refresh after unauthorized response' => [
                $this->getConfigWithGuzzleClient(
                    [
                        $this->getUnauthorizedResponse(),
                        $this->getTokenResponse(),
                        $this->getFileListEmptyResponse(),
                    ]
                )->setAccessToken('foobar'),
                'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',
                $this->getFileListEmptyResponse(),
            ],
        ];
    }

    protected function getTokenResponse(): ResponseInterface
    {
        $factory = new HttpFactory();
        return $factory->createResponse(200)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $factory->createStream(\json_encode([
                    'token_type' => 'Bearer',
                    'expires_in' => 3600,
                    'access_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c',
                    'scope' => 'cap',
                ]))
            );
    }

    protected function getFileUploadResponse(): ResponseInterface
    {
        $headers = $this->getDefaultResponseHeaders();
        unset($headers['Content-Type']); // file upload 204 doesn't have content type
        return new Response(204, $headers);
    }

    protected function getFileListEmptyResponse(): ResponseInterface
    {
        $factory = new HttpFactory();
        $response = new Response(200, $this->getDefaultResponseHeaders());

        return $response->withBody(
            $factory->createStream(\json_encode([
                'files' => [],
                'links' => [
                    [
                        'rel' => 'self',
                        'href' => 'https://dtfapi.deere.com/dbs/dbs/dealer/test01/files'
                    ]
                ],
            ]))
        );
    }

    protected function getFileListFromDocResponse(): ResponseInterface
    {
        $factory = new HttpFactory();
        $response = new Response(200, $this->getDefaultResponseHeaders());

        return $response->withBody(
            $factory->createStream(\json_encode([
                'files' => [
                    [
                        'name' => 'samplecommonfile.txt',
                        'modifiedTime' => '2021-02-04 02:55',
                        'size' => '25 Byte(s)',
                        'links' => [
                            [
                                'rel' => 'self',
                                'href' => 'https://dtfapicert.deere.com/dbs/dealer/06DV01/files/samplecommonfile.txt',
                            ],
                        ],
                    ],
                ],
                'links' => [
                    [
                        'rel' => 'self',
                        'href' => 'https://dtfapicert.deere.com/dbs/dealer/06DV01/files'
                    ]
                ],
            ]))
        );
    }

    protected function getFileDownloadResponse(): ResponseInterface
    {
        $factory = new HttpFactory();
        $response = new Response(200, $this->getDefaultResponseHeaders());

        return $response
            ->withBody($factory->createStreamFromFile(__DIR__ . '/samplecommonfile.txt'))
            ->withHeader('Content-Type', 'application/octet-stream')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Content-Disposition', 'filename=samplecommonfile.txt')
            ->withHeader('Transfer-Encoding', 'chunked');
    }

    protected function getFileDetailsResponse(): ResponseInterface
    {
        $factory = new HttpFactory();
        $response = new Response(200, $this->getDefaultResponseHeaders());

        return $response
            ->withBody($factory->createStream(\json_encode([
                'name' => 'samplecommonfile.txt',
                'size' => '0.02KB',
                'status' => 'Available',
                'lastAccessed' => '2022-05-03 04:04',
                'links' => [
                    [
                        'rel' => 'download',
                        'href' => 'https://dtfapicert.deere.com/dbs/dealer/06DV01/files/samplecommonfile.txt'
                    ],
                    [
                        'rel' => 'self',
                        'href' => 'https://dtfapicert.deere.com/dbs/dealer/06DV01/files/samplecommonfile.txt/details'
                    ],
                ],
            ])))
            ->withHeader('Content-Type', 'application/json;charset=utf-8')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type')
            ->withHeader('Transfer-Encoding', 'chunked');
    }

    protected function getUnauthorizedResponse(): ResponseInterface
    {
        $factory = new HttpFactory();
        return $factory->createResponse(401)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $factory->createStream(\json_encode([
                    'faultcode' => 'Server',
                    'faultstring' => '1012100 - Credentials invalid or missing.',
                ]))
            );
    }

    protected function getOktaAuthUnsupportedGrantResponse(): ResponseInterface
    {
        $factory = new HttpFactory();
        return $factory->createResponse(400)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $factory->createStream(\json_encode([
                    'error' => 'unsupported_grant_type',
                    'error_description' => 'The authorization grant type is not supported by the authorization server. Configured grant types: [client_credentials].',
                ]))
            );
    }

    protected function getOktaAuth401Response(): ResponseInterface
    {
        $factory = new HttpFactory();
        return $factory->createResponse(401)
            ->withHeader('Content-Type', 'application/json')
            ->withBody(
                $factory->createStream(\json_encode([
                    'errorCode' => 'invalid_client',
                    'errorSummary' => "Invalid value for 'client_id' parameter.",
                    'errorLink' => 'invalid_client',
                    'errorId' => 'oaey_iirViATF6tniVlN1NAjA',
                    'errorCauses' => [],
                ]))
            );
    }

    protected function getErrorResponse(
        string $location = 'https://dtfapicert.deere.com/dbs/dealer/test01/files/409?overwrite=True',
        int $errorCode = 400,
        int $contentLength = 197
    ): ResponseInterface {
        $factory = new HttpFactory();
        $response = new Response($errorCode, $this->getDefaultResponseHeaders());

        return $response
            ->withHeader('Location', $location)
            ->withHeader('x-deere-dtf-api-err-code', (string)$errorCode)
            ->withHeader('Content-Length', (string)$contentLength)
            ->withBody(
                $factory->createStream(\json_encode([
                    'timestamp' => '2022-04-27 15:39',
                    'error' => 'FileAlreadyExists',
                    'message' => 'The specified foobar.eame already exists',
                    'path' => '/dbs/dealer/test01/files',
                    'status' => 409,
                ]))
            );
    }

    protected function getDefaultResponseHeaders(): array
    {
        return [
            'Date' => (new \DateTime())->format(\DateTimeInterface::RFC7231), // format Wed, 27 Apr 2022 20:37:26 GMT,
            'Content-Type' => 'application/json;charset=utf-8',
            'X-Content-Type-Options' => 'nosniff',
            'X-XSS-Protection' => '1; mode=block',
            'Cache-Control' => 'no-cache, no-store, max-age=0, must-revalidate',
            'Expires' => '0',
            'Strict-Transport-Security' => 'max-age=31536000 ; includeSubDomains',
            'X-Frame-Options' => 'DENY',
            'X-Application-Context' => 'application:Cert',
        ];
    }

    protected function getConfigWithGuzzleClient(array $mockResponses): ApiClientConfig
    {
        $mock = new MockHandler($mockResponses);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        return new ApiClientConfig(
            $client,
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
