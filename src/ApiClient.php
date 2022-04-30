<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
// use Psr\Log\LoggerAwareInterface;
// use Psr\Log\LoggerAwareTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Ybelenko\DtfDbsClient\ApiClientConfig;
use Ybelenko\DtfDbsClient\Exceptions\ApiErrorException;
use Ybelenko\DtfDbsClient\Exceptions\HttpClientException;
use Ybelenko\DtfDbsClient\Exceptions\OktaAuthErrorException;
use Ybelenko\DtfDbsClient\Exceptions\UnsupportedResponseException;

class ApiClient // implements LoggerAwareInterface
{
    // use LoggerAwareTrait;

    /** @var ApiClientConfig */
    protected $config;

    /**
     * Class constructor.
     *
     * @param ApiClientConfig $config
     */
    public function __construct(
        ApiClientConfig $config
    ) {
        $this->config = $config;
    }

    public function getConfig(): ApiClientConfig
    {
        return $this->config;
    }

    /**
     * Call File Upload Service of DTF DBS API.
     *
     * @api This operation allows DBS to upload file to Deere.
     *
     * @param string|resource|StreamInterface $file      Actual file content
     * @param string|null                     $fileName  FileName is optional, if not pass, it will use actual file name from multipart request.
     * @param bool|null                       $overWrite Overwrite is optional, default value is false, that means it will not allow duplicate file.
     *
     * @throws ApiErrorException when file already exists and overWrite set to false
     *
     * @return bool
     */
    public function callFileUploadService(
        $file,
        ?string $fileName = null,
        ?bool $overWrite = false
    ): bool {
        // obtain new token when necessary
        if (empty($this->config->getAccessToken()) && $newToken = $this->obtainAccessToken()) {
            $this->config->setAccessToken($newToken);
        }

        $request = $this->config->buildFileUploadRequest($file, $fileName, $overWrite);
        $response = $this->processHttpRequest($request);

        if ($response->getStatusCode() === 401 && $newToken = $this->obtainAccessToken()) {
            // access token can be expired, refresh and try again
            $this->config->setAccessToken($newToken);
            $request = $this->config->buildFileListRequest($file, $fileName, $overWrite);
            $response = $this->processHttpRequest($request);
        }

        // expect 204 status code
        $uploadSuccess = $response->getStatusCode() === 204;
        if (!$uploadSuccess) {
            // check for errors in body
            $this->decodeApiResult($request, $response);
        }

        return $uploadSuccess;
    }

    /**
     * Call File List Service of DTF DBS API.
     *
     * @api This operation will list all available files for a Dealer.
     * File list will be HATEOS links so they can be recursively downloaded from the response of this service.
     *
     * @return array[] Approx shape [{"name": "order.dat", "links": [{"rel": "download", "href": "http:"}, {"rel": "details", "href": "http:"}]}, ...]
     */
    public function callFileListService(): array
    {
        // obtain new token when necessary
        if (empty($this->config->getAccessToken()) && $newToken = $this->obtainAccessToken()) {
            $this->config->setAccessToken($newToken);
        }

        $request = $this->config->buildFileListRequest();
        $response = $this->processHttpRequest($request);

        if ($response->getStatusCode() === 401 && $newToken = $this->obtainAccessToken()) {
            // access token can be expired, refresh and try again
            $this->config->setAccessToken($newToken);
            $request = $this->config->buildFileListRequest();
            $response = $this->processHttpRequest($request);
        }

        $result = $this->decodeApiResult($request, $response, ['files']);
        return $result['files'];
    }

    /**
     * Call File Download Service of DTF DBS API.
     *
     * @api This operation downloads single file for a Dealer based in the input.
     * To save file in local storage use any PSR-17 uploaded file factory implementation.
     * @see https://github.com/php-fig/http-factory/blob/36fa03d50ff82abcae81860bdaf4ed9a1510c7cd/src/UploadedFileFactoryInterface.php
     * With that factory you can create UploadedFileInterface instance providing stream as the first argument.
     * @see https://github.com/php-fig/http-message/blob/efd67d1dc14a7ef4fc4e518e7dee91c271d524e4/src/UploadedFileInterface.php
     * Finally UploadedFileInterface::moveTo() method is able to write file to disk.
     *
     * @param string $filename
     *
     * @throws ApiErrorException
     *
     * @return StreamInterface
     */
    public function callFileDownloadService(string $filename): StreamInterface
    {
        // obtain new token when necessary
        if (empty($this->config->getAccessToken()) && $newToken = $this->obtainAccessToken()) {
            $this->config->setAccessToken($newToken);
        }

        $request = $this->config->buildFileDownloadRequest($filename);
        $response = $this->processHttpRequest($request);

        if ($response->getStatusCode() === 401 && $newToken = $this->obtainAccessToken()) {
            // access token can be expired, refresh and try again
            $this->config->setAccessToken($newToken);
            $request = $this->config->buildFileDownloadRequest($filename);
            $response = $this->processHttpRequest($request);
        }

        if (
            $response->getStatusCode() !== 200
            || !$response->hasHeader('Content-Type')
            || \stripos($response->getHeader('Content-Type')[0], 'application/octet-stream') === false
        ) {
            // check error fields
            $this->decodeApiResult($request, $response);
        }

        return $response->getBody();
    }

    /**
     * Call Get File Details Service of DTF DBS API
     *
     * @api Undocumented DTF DBS API method.
     *
     * @param string $filename
     *
     * @throws ApiErrorException
     *
     * @return array Approx shape {"name": "foobar.dat", "size": "0.02KB", "status": "Available", "lastAccessed": "2022-05-03 04:04", "links": [{ "rel": "download", "href": "http:"}, ...]}
     */
    public function callGetFileDetailsService(string $filename): array
    {
        // obtain new token when necessary
        if (empty($this->config->getAccessToken()) && $newToken = $this->obtainAccessToken()) {
            $this->config->setAccessToken($newToken);
        }

        $request = $this->config->buildGetFileDetailsRequest($filename);
        $response = $this->processHttpRequest($request);

        if ($response->getStatusCode() === 401 && $newToken = $this->obtainAccessToken()) {
            // access token can be expired, refresh and try again
            $this->config->setAccessToken($newToken);
            $request = $this->config->buildGetFileDetailsRequest($filename);
            $response = $this->processHttpRequest($request);
        }

        $result = $this->decodeApiResult($request, $response);
        return $result;
    }

    /**
     * Request new access token from OKTA CAP auth servers.
     *
     * @api Common OAuth2 process to sign new access token via client_credentials grant.
     *
     * @throws OktaCapAuthException
     * @throws \RuntimeException
     *
     * @return string new JWT access token, expires in 3600 seconds
     */
    public function obtainAccessToken(): string
    {
        $request = $this->config->buildObtainTokenRequest();
        $response = $this->processHttpRequest($request);
        $result = $this->decodeTokenResult($request, $response);

        return $result['access_token'];
    }

    /**
     * @internal
     *
     * @param RequestInterface $request
     *
     * @throws HttpClientException
     *
     * @return ResponseInterface
     */
    protected function processHttpRequest(RequestInterface $request): ResponseInterface
    {
        /** @var ResponseInterface|null */
        $response = null;
        /** @var \Exception|null $e */
        $e = null;
        /** @var string|null $message */
        $message = null;

        try {
            $httpClient = $this->config->getHttpClient();
            $response = $httpClient->sendRequest($request);
        } catch (RequestExceptionInterface $e) {
            // http request failed due invalid status code, body isn't seekable, invalid http method
            $message = 'HTTP request failed due non 2xx status code or other standard violations.';
        } catch (NetworkExceptionInterface $e) {
            // connection timeout or lack of internet
            $message = 'HTTP request failed due network issues, check internet connection.';
        } catch (ClientExceptionInterface $e) {
            // unspecified http client error
            $message = 'HTTP request failed for some unspecified reason.';
        } catch (\Throwable $e) {
            // wrap any nested exception with our exception
            $message = 'General error occurred during HTTP request.';
        } finally {
            if ($e) {
                throw new HttpClientException($request, $message, 500, $e, $response);
            }
        }

        return $response;
    }

    /**
     * Parses JSON from OKTA CAP auth server response.
     * @internal
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @throws OktaAuthErrorException
     * @throws UnsupportedResponseException
     *
     * @return mixed
     */
    protected function decodeTokenResult(
        RequestInterface $request,
        ResponseInterface $response
    ) {
        /** @var mixed */
        $result = null;
        if (
            $response->hasHeader('Content-Type') === false
            || \stripos($response->getHeader('Content-Type')[0], 'application/json') === false
        ) {
            // different content-type, not JSON
            $msg = sprintf('Provided response isn\'t JSON content type. %s', $response->getHeaderLine('Content-Type') ?? '');
            throw new UnsupportedResponseException($request, $msg, 500, null, $response);
        }

        try {
            $result = \json_decode((string)$response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($result) === false) {
                // we usually expect assoc array
                throw new UnsupportedResponseException($request, 'Invalid response, assoc array expected', 500, null, $response);
            }


            // standard oauth error body
            if (\array_key_exists('error', $result) && \array_key_exists('error_description', $result)) {
                $msg = \sprintf('%s: %s', $result['error'], $result['error_description']);
                throw new OktaAuthErrorException($request, $msg, $response->getStatusCode(), null, $response);
            }

            // custom OKTA CAP Auth error body
            if (\array_key_exists('errorCode', $result) && \array_key_exists('errorSummary', $result)) {
                $msg = \sprintf('%s: %s', $result['errorCode'], $result['errorSummary']);
                throw new OktaAuthErrorException($request, $msg, $response->getStatusCode(), null, $response);
            }

            // access token not found
            if (\array_key_exists('access_token', $result) === false) {
                throw new UnsupportedResponseException($request, 'Body doesn\'t contain "access_token" field', 500, null, $response);
            }
        } catch (\JsonException $e) {
            throw new UnsupportedResponseException($request, 'Cannot parse response body. Malformed JSON', 500, $e, $response);
        }

        return $result;
    }

    /**
     * Parses JSON from common API response.
     * @internal
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param string[]|null     $requiredFields
     *
     * @throws UnsupportedResponseException
     * @throws ApiErrorException
     *
     * @return mixed
     */
    protected function decodeApiResult(
        RequestInterface $request,
        ResponseInterface $response,
        ?array $requiredFields = []
    ) {
        /** @var mixed */
        $result = null;
        if (
            $response->hasHeader('Content-Type') === false
            || \stripos($response->getHeader('Content-Type')[0], 'application/json') === false
        ) {
            // different content-type, not JSON
            $msg = sprintf('Provided response isn\'t JSON content type. %s', $response->getHeaderLine('Content-Type') ?? '');
            throw new UnsupportedResponseException($request, $msg, 500, null, $response);
        }

        try {
            $result = \json_decode((string)$response->getBody(), true, 512, \JSON_THROW_ON_ERROR);

            if (\is_array($result) === false) {
                // we usually expect assoc array
                throw new UnsupportedResponseException($request, 'Invalid response, assoc array expected', 500, null, $response);
            }

            // unauthorized error
            if (\array_key_exists('faultcode', $result) && \array_key_exists('faultstring', $result)) {
                $msg = \sprintf('%s: %s', $result['faultcode'], $result['faultstring']);
                throw ApiErrorException::createFromValues(
                    $request,
                    $result['faultcode'],
                    $msg,
                    $response->getStatusCode()
                )->withResponse($response);
            }

            // api error
            if (\array_key_exists('error', $result) && \array_key_exists('message', $result)) {
                throw ApiErrorException::createFromValues(
                    $request,
                    $result['error'],
                    $result['message'],
                    $response->getStatusCode(),
                    $result['timestamp'] ?? null,
                    $result['path'] ?? null,
                    $response
                );
            }

            if (is_array($requiredFields) && is_array($result)) {
                foreach ($requiredFields as $reqKey) {
                    if (\array_key_exists($reqKey, $result) === false) {
                        $msg = \sprintf('No required field "%s" in response body', $reqKey);
                        throw new UnsupportedResponseException($request, $msg, 500, null, $response);
                    }
                }
            }
        } catch (\JsonException $e) {
            throw new UnsupportedResponseException($request, 'Cannot parse response body. Malformed JSON.', 500, $e, $response);
        }

        return $result;
    }
}
