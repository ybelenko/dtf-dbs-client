<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient\Exceptions;

use Ybelenko\DtfDbsClient\Exceptions\BaseRequestException;

class UnsupportedResponseException extends BaseRequestException
{
    /** @var string Runtime exception. */
    protected string $description = 'Received unknown response. Maybe API schema has been changed.';

    /** @var int */
    protected $code = 500;
}
