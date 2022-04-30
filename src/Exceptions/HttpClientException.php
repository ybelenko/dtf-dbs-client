<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient\Exceptions;

use Ybelenko\DtfDbsClient\Exceptions\BaseRequestException;

class HttpClientException extends BaseRequestException
{
    /** @var string Runtime exception. */
    protected string $description = 'Http client thrown some exception which cannot handle ourselves.';

    /** @var int */
    protected $code = 500;
}
