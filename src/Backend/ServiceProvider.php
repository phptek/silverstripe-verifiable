<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

/**
 * Defines exactly what services should look like.
 */
interface ServiceProvider
{
    /**
     * @return string
     */
    public function name() : string;
}
