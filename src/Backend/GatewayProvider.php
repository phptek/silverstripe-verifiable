<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

/**
 * Defines exactly what backends-gateways should look like.
 */
interface GatewayProvider
{

    /**
     * The name of this backend.
     *
     * @return string
     */
    public function name() : string;

    /**
     * The name of the hash-function supported by implementors e.g. 'sha256'.
     *
     * @return string
     */
    public function hashFunc() : string;
}
