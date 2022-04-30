<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient\Exceptions;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ybelenko\DtfDbsClient\Exceptions\BaseRequestException;

class ApiErrorException extends BaseRequestException
{
    /** @var string */
    protected $description = 'DTF DBS internal API Exception';

    /** @var string|null */
    protected $errorCode;

    /** @var int|null */
    protected $httpStatusCode;

    /** @var string|null */
    protected $timestamp;

    /** @var string|null */
    protected $path;

    /**
     * Get errorCode value.
     *
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Set errorCode value.
     *
     * @param string|null $errorCode The value
     *
     * @return self
     */
    public function setErrorCode(?string $errorCode): self
    {
        $this->errorCode = $errorCode;
        return $this;
    }

    /**
     * Get httpStatusCode value.
     *
     * @return int|null
     */
    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    /**
     * Set httpStatusCode value.
     *
     * @param int|null $httpStatusCode The value
     *
     * @return self
     */
    public function setHttpStatusCode(?int $httpStatusCode): self
    {
        $this->httpStatusCode = $httpStatusCode;
        return $this;
    }

    /**
     * Get timestamp value.
     *
     * @return string|null
     */
    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }

    /**
     * Set timestamp value.
     *
     * @param string|null $timestamp The value
     *
     * @return self
     */
    public function setTimestamp(?string $timestamp): self
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * Get path value.
     *
     * @return string|null
     */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /**
     * Set path value.
     *
     * @param string|null $path The value
     *
     * @return self
     */
    public function setPath(?string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Class static factory.
     *
     * @param RequestInterface       $request
     * @param string                 $errorCode
     * @param string                 $message
     * @param int                    $statusCode
     * @param string|null            $timestamp
     * @param string|null            $path
     * @param ResponseInterface|null $response
     *
     * @return self
     */
    public static function createFromValues(
        RequestInterface $request,
        string $errorCode,
        string $message,
        int $statusCode,
        ?string $timestamp = null,
        ?string $path = null,
        ?ResponseInterface $response = null
    ): self {
        return (new static($request, $message, $statusCode, null, $response))
            ->setErrorCode($errorCode)
            ->setHttpStatusCode($statusCode)
            ->setTimestamp($timestamp)
            ->setPath($path);
    }
}
