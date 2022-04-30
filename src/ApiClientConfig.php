<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient;

use Http\Message\MultipartStream\MultipartStreamBuilder;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

class ApiClientConfig
{
    /** @var ClientInterface */
    protected $httpClient;

    /** @var RequestFactoryInterface */
    protected $requestFactory;

    /** @var UriFactoryInterface */
    protected $uriFactory;

    /** @var StreamFactoryInterface */
    protected $streamFactory;

    /** @var string */
    protected $clientId;

    /** @var string */
    protected $clientSecret;

    /** @var string */
    protected $dealerId;

    /** @var string qual|cert|prod */
    protected $environment;

    /** @var string|null */
    protected $authScope;

    /** @var string|null Bearer authorization token */
    protected $accessToken;

    /**
     * Class constructor.
     */
    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        string $clientId,
        string $clientSecret,
        string $dealerId,
        string $environment,
        ?string $authScope = 'dtf:dbs:file:write dtf:dbs:file:read'
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->streamFactory = $streamFactory;
        $this->setClientId($clientId);
        $this->setClientSecret($clientSecret);
        $this->dealerId = $dealerId;

        $this->setEnvironment($environment);
        $this->setAuthScope($authScope);
    }

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken =  $accessToken;
        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function getHttpClient(): ClientInterface
    {
        return $this->httpClient;
    }

    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;
        return $this;
    }

    public function setClientSecret(string $clientSecret): self
    {
        $this->clientSecret = $clientSecret;
        return $this;
    }

    public function setDealerId(string $dealerId): self
    {
        $this->dealerId = $dealerId;
        return $this;
    }

    /**
     * Sets API environment.
     *
     * @param string $environment prod|cert|qual
     * @throws \InvalidArgumentException when environment is unknown.
     *
     * @return self
     */
    public function setEnvironment(string $environment): self
    {
        if (!in_array($environment, ['prod', 'cert', 'qual'], true)) {
            throw new \InvalidArgumentException('DTF DBS API Client: Invalid environment argument, should be prod|cert|qual');
        }
        $this->environment = $environment;

        return $this;
    }

    public function setAuthScope(?string $authScope = null): ApiClientConfig
    {
        $this->authScope = $authScope;
        return $this;
    }

    /**
     * Create new HTTP request to obtain access token.
     *
     * @uses \http_build_query
     * @see https://www.php.net/manual/en/function.http-build-query
     *
     * @return RequestInterface
     */
    public function buildObtainTokenRequest(): RequestInterface
    {
        $baseTokenUrl = $this->uriFactory->createUri(static::getObtainTokenUrl($this->environment));
        $auth = base64_encode("{$this->clientId}:{$this->clientSecret}");
        $body = $this->streamFactory->createStream(\http_build_query(
            [
                'grant_type' => 'client_credentials',
                'scope' => $this->authScope,
            ],
            '',
            '&',
            \PHP_QUERY_RFC1738
        ));

        return $this->requestFactory
            ->createRequest('POST', $baseTokenUrl->withPath($baseTokenUrl->getPath() . '/v1/token'))
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withHeader('Authorization', "Basic {$auth}")
            ->withBody($body);
    }

    /**
     * Builds HTTP request for File Upload Service of DTF DBS API.
     *
     * @uses \Http\Message\MultipartStream\MultipartStreamBuilder
     * @see https://docs.php-http.org/en/latest/components/multipart-stream-builder.html#multipart-stream-builder
     *
     * @uses \http_build_query
     * @see https://www.php.net/manual/en/function.http-build-query
     *
     * @param string|resource|StreamInterface $file      Actual file content
     * @param string|null                     $fileName  FileName is optional, if not pass, it will use actual file name from multipart request.
     * @param bool|null                       $overWrite Overwrite is optional, default value is false, that means it will not allow duplicate file.
     *
     * @return RequestInterface
     */
    public function buildFileUploadRequest(
        $file,
        ?string $fileName = null,
        ?bool $overWrite = false
    ): RequestInterface {
        // expect path to file, resource or StreamInterface
        if (
            !\is_resource($file)
            && $file instanceof StreamInterface === false
            && \file_exists($file) === false
        ) {
            throw new \InvalidArgumentException('\$file argument must be path to file, resource or StreamInterface');
        }

        $basePath = $this->uriFactory->createUri(static::getApiBasePathUrl($this->environment));
        $url = $basePath->withPath(
            $basePath->getPath() . "/dbs/dealer/{$this->dealerId}/files"
        );
        $query = [
            'fileName' => $fileName,
            'overWrite' => 'True',
        ];

        if (empty($fileName)) {
            unset($query['fileName']);
        }
        if ($overWrite !== true) {
            unset($query['overWrite']);
        }

        if (empty($query) === false) {
            $url = $url->withQuery(\http_build_query(
                $query,
                '',
                '&',
                \PHP_QUERY_RFC1738
            ));
        }

        if (\is_string($file) && \file_exists($file)) {
            $file = fopen($file, 'r');
        }

        $builder = new MultipartStreamBuilder($this->streamFactory);
        $builder->addResource('file', $file);

        $multipartStream = $builder->build();
        $boundary = $builder->getBoundary();

        return $this->requestFactory->createRequest('PUT', $url)
            ->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->withHeader('Content-Type', "multipart/form-data; boundary={$boundary}")
            ->withBody($multipartStream);
    }

    /**
     * Builds HTTP request for File List Service of DTF DBS API.
     *
     * @return RequestInterface
     */
    public function buildFileListRequest(): RequestInterface
    {
        $basePath = $this->uriFactory->createUri(static::getApiBasePathUrl($this->environment));
        $url = $basePath->withPath($basePath->getPath() . "/dbs/dealer/{$this->dealerId}/files");

        return $this->requestFactory
            ->createRequest('GET', $url)
            ->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->withHeader('Accept', 'application/json');
    }

    /**
     * Builds HTTP request for File Download Service of DTF DBS API.
     *
     * @return RequestInterface
     */
    public function buildFileDownloadRequest(string $filename): RequestInterface
    {
        $basePath = $this->uriFactory->createUri(static::getApiBasePathUrl($this->environment));
        $url = $basePath->withPath($basePath->getPath() . "/dbs/dealer/{$this->dealerId}/files/" . rawurlencode($filename));

        return $this->requestFactory
            ->createRequest('GET', $url)
            ->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->withHeader('Accept', 'application/json');
    }

    /**
     * Build HTTP request to get file details from DTF DBS API.
     *
     * @return RequestInterface
     */
    public function buildGetFileDetailsRequest(string $filename): RequestInterface
    {
        $basePath = $this->uriFactory->createUri(static::getApiBasePathUrl($this->environment));
        $url = $basePath->withPath(
            $basePath->getPath()
            . "/dbs/dealer/{$this->dealerId}/files/"
            . rawurlencode($filename)
            . '/details'
        );

        return $this->requestFactory
            ->createRequest('GET', $url)
            ->withHeader('Authorization', "Bearer {$this->accessToken}")
            ->withHeader('Accept', 'application/json');
    }

    /**
     * Returns OAuth2 server base URL.
     *
     * @param string $environment prod|cert|qual
     *
     * @throws \InvalidArgumentException when invalid environment.
     *
     * @return string
     */
    public static function getObtainTokenUrl(string $environment): string
    {
        switch ($environment) {
            case 'qual':
                return 'https://sso-qual.johndeere.com/oauth2/ausi42oq38DYB06q50h7';
            case 'cert':
                return 'https://sso-cert.johndeere.com/oauth2/aus97etlxsNTFzHT11t7';
            case 'prod':
                return 'https://sso.johndeere.com/oauth2/aus9k0fb8kUjG8S5Z1t7';
            default:
                throw new \InvalidArgumentException('DTF DBS API Client: Unknown environment to get OAuth token URL');
        }
    }

    /**
     * Returns API base URL.
     *
     * @param string $environment prod|cert|qual
     *
     * @throws \InvalidArgumentException when invalid environment.
     *
     * @return string
     */
    public static function getApiBasePathUrl(string $environment): string
    {
        switch ($environment) {
            case 'qual':
                return 'https://servicesextqual.tal.deere.com/dtfapi';
            case 'cert':
                return 'https://servicesextcert.deere.com/dtfapi';
            case 'prod':
                return 'https://servicesext.deere.com/dtfapi';
            default:
                throw new \InvalidArgumentException('DTF DBS API Client: Unknown environment to get API base path');
        }
    }
}
