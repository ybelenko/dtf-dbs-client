<?php declare(strict_types=1);

/**
 * @author Yuriy Belenko <yura-bely@mail.ru>
 * @license MIT
 */

namespace Ybelenko\DtfDbsClient\Exceptions;

use Ybelenko\DtfDbsClient\Exceptions\BaseRequestException;

class OktaAuthErrorException extends BaseRequestException
{
    /** @var string Runtime exception. */
    protected string $description = 'Some error occurred during Okta OAUTH authentication';

    /** @var int */
    protected $code = 400;
}
