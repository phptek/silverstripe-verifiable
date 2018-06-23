<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\ORM\FieldType;

use PhpTek\JSONText\ORM\FieldType\JSONText;

/**
 * Encapsulates a single chainpoint proof. The proof is usually derived from the
 * "Proof" field on a {@link DataExtension}.
 *
 * Makes use of the {@link JSONText} package and wraps simple queries around
 * its raw JSONQuery calls.
 */
class ChainpointProof extends JSONText
{

    /**
     * Returns the generated value of the proof's "hash_id_node" key.
     *
     * @return string
     */
    public function getHashIdNode() : string
    {
        $this->setReturnType('array');

        return $this->query('->>', 'hash_id_node')['hash_id_node'];
    }

    /**
     * Returns the generated value of the proof's "hash" key.
     *
     * @return string
     */
    public function getHash() : string
    {
        $this->setReturnType('array');

        return $this->query('->>', 'hash')['hash'];
    }

}
