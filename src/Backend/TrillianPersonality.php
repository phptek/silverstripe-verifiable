<?php

/**
 * @author  Russell MIchell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

class TrillianPersonality implements BackendProvider
{
    /**
     *
     * {@inheritdoc}
     */
    public function connect() : bool
    {
    }

    /**
     *
     * {@inheritdoc}
     */
    public function read(string $hash) : array
    {
    }

    /**
     *
     * {@inheritdoc}
     */
    public function write() : string
    {
    }


}

