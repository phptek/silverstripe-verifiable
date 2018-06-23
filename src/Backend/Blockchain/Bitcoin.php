<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend\Blockchain;

/**
 * Class that models connection and verification requests made on and to the
 * Bitcoin network via the full-node software returned by the implementation()
 * method.
 */
class Bitcoin
{
    /**
     * Which full-node client software are we using to make requests back to the
     * Bitcoin network.
     *
     * @return string
     */
    public function implementation() : string
    {
        return 'bitcoind';
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
    public function verifyProof(string $proof) : bool
    {
        // TODO
    }

}
