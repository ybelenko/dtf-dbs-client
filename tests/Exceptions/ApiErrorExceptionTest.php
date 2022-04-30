<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient\Exceptions;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @coversDefaultClass \Ybelenko\DtfDbsClient\Exceptions\ApiErrorException
 * @uses \Ybelenko\DtfDbsClient\Exceptions\BaseRequestException
 * @covers ::getErrorCode
 * @covers ::getHttpStatusCode
 * @covers ::getPath
 * @covers ::getTimestamp
 * @covers ::setErrorCode
 * @covers ::setHttpStatusCode
 * @covers ::setPath
 * @covers ::setTimestamp
 *
 * @internal
 *
 * @small
 */
final class ApiErrorExceptionTest extends TestCase
{
    /**
     * @covers ::__construct
     * @covers ::createFromValues
     */
    public function testConstructorAndSetters()
    {
        $factory = new HttpFactory();
        $exception = ApiErrorException::createFromValues($factory->createRequest('GET', 'foobar'), 'FileNotFound', 'Specified file does not exist.', 404, '2022-05-03 05:53', '/dbs/dealer/test01/files/foobar.DAT/details', $factory->createResponse(404));

        static::assertInstanceOf(RequestExceptionInterface::class, $exception);
        static::assertSame('Specified file does not exist.', $exception->getMessage());
        static::assertSame('FileNotFound', $exception->getErrorCode());
        static::assertSame(404, $exception->getHttpStatusCode());
        static::assertSame('2022-05-03 05:53', $exception->getTimestamp());
        static::assertSame('/dbs/dealer/test01/files/foobar.DAT/details', $exception->getPath());
        static::assertInstanceOf(ResponseInterface::class, $exception->getResponse());
        static::assertSame(404, $exception->getResponse()->getStatusCode());
    }
}
