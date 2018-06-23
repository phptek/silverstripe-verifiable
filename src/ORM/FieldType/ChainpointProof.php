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
    protected function getHashIdNode() : string
    {
        $this->setReturnType('json');

        return $this->query('->>', 'hash_id_node');
    }

    /**
     * Does the passed $hash match in the stored proof?
     *
     * @return boolean
     */
    public function match(string $hash) : bool
    {
        $hashFromProof = json_decode($this->getHashIdNode(), true) ?: '';

        return $hash === array_values($hashFromProof)[0];
    }

}
