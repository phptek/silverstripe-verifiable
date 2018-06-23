<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

/**
 * Defines exactly what backends must look like.
 */
interface BackendProvider
{
    /**
     * The name of this backend.
     *
     * @return string
     */
    public function name() : string;

    /**
     * Establish a connection to this backend.
     *
     * @return bool True if connection was successful, false otherwise.
     */
    public function connect() : bool;

    /**
     * Write to this backend.
     *
     * @param  string $hash The hashed data to be written
     * @return string       A valid JSON-LD ChainPoint Proof
     * @see    https://chainpoint.org/
     */
    public function write(string $hash) : string;

    /**
     * Read record(s) from this backend.
     *
     * @return array An array comprising one or more arrays who's values are:
     *               1). The Merkle Root hash
     *               2). The requested leaf-node hash
     */
    public function read(string $hash) : array;

}

}
