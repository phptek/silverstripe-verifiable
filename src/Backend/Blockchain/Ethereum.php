<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend\Blockchain;

/**
 * Class that models connection and verification requests made on and to the
 * Ethereum network via the full-node software returned by the implementation()
 * method.
 */
class Ethereum
{
    /**
     * Which full-node client software are we using to make requests back to the
     * Ethereum network.
     *
     * @return string
     */
    public function implementation() : string
    {
        return 'geth';
    }

    /**
     * @return bool
     */
    public function connect() : bool
    {
        // TODO
    }

    /**
     * @return bool
     */
    public function verifyProof() : bool
    {
        // TODO
    }

}
