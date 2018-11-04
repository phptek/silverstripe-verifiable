<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Exception;

use PhpTek\Verifiable\Exception\VerifiableSecurityException;

/**
 * Thrown when no version is found for checksum checks.
 */
class VerifiableNoVersionException extends VerifiableSecurityException
{
}
