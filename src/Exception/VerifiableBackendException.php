<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Exception;

/**
 * Thrown when errors in the HTTP transport between SilverStripe and the backend
 * occur, or when the backend itself responds with problems of its own. But then
 * we all have those...
 */
class VerifiableBackendException extends \Exception
{
}
