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
     * Write a hash to this backend.
     *
     * @param  string $hash The hashed data to be written
     * @return string       A valid JSON-LD ChainPoint Proof
     */
    public function writeHash(string $hash) : string;

    /**
     * Read a proof from this backend.
     *
     * @return string A JSON-LD chainpoint proof.
     */
    public function getProof(string $hash) : string;

    /**
     * Verify a proof against the backend.
     *
     * @param  string $proof  A valid JSIN-LD chainpoint proof
     * @return bool
     */
    public function verifyProof(string $proof) : bool;

}
