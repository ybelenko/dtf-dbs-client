<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient\Exceptions;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class BaseRequestException extends \Exception implements RequestExceptionInterface
{
    /** @var RequestInterface */
    protected $request;

    /** @var ResponseInterface|null */
    protected $response;

    /**
     * Class constructor.
     */
    public function __construct(
        RequestInterface $request,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?ResponseInterface $response = null
    ) {
        parent::__construct($message, $code ?? $this->code, $previous);
        $this->request = $request;
        $this->response = $response;
    }

    /**
     * @inheritDoc
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }

    /**
     * Create new instance with provided response.
     *
     * @param ResponseInterface $response
     *
     * @return self
     */
    public function withResponse(ResponseInterface $response): self
    {
        return new static($this->request, $this->message, $this->code, $this->getPrevious(), $response);
    }

    /**
     * Get HTTP response.
     * Might be NULL when request has been interrupted.
     *
     * @return ResponseInterface|null
     */
    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
