<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient\Exceptions;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @coversDefaultClass \Ybelenko\DtfDbsClient\Exceptions\BaseRequestException
 *
 * @internal
 *
 * @small
 */
final class HttpClientExceptionTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::getRequest
     * @covers ::getResponse
     * @covers ::withResponse
     */
    public function testConstructorAndSetters()
    {
        $factory = new HttpFactory();
        $exception = new HttpClientException($factory->createRequest('GET', 'foobar'), 'Connection Timeout', 500, null, null);

        static::assertInstanceOf(RequestExceptionInterface::class, $exception);
        static::assertInstanceOf(RequestInterface::class, $exception->getRequest());
        static::assertSame('Connection Timeout', $exception->getMessage());
        static::assertSame(500, $exception->getCode());
        static::assertNull($exception->getPrevious());
        static::assertNull($exception->getResponse());

        $exception = $exception->withResponse($factory->createResponse());
        static::assertInstanceOf(ResponseInterface::class, $exception->getResponse());
        static::assertSame(200, $exception->getResponse()->getStatusCode());
    }
}
