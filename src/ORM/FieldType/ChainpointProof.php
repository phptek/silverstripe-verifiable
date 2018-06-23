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
 *
 * @todo Add support for exporting in MsgPack format (http://msgpack.org/index.html)
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

    /**
     * Returns the generated value of the proof's "submitted_at" key.
     *
     * @return string
     */
    public function getSubmittedAt() : string
    {
        $this->setReturnType('array');

        return $this->query('->>', 'submitted_at')['submitted_at'];
    }

    /**
     * Does the passed data response represent a PARTIAL verification as far as
     * the local database is concerned?
     *
     * @param  mixed $input
     * @return bool
     */
    public function isPartiallyComplete($input = null) : bool
    {
        $data = $data ?? $this->getStoreAsArray();

        if (isset($data['anchors_complete']) && count($data['anchors_complete']) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Does the passed data represent a FULL verification as far as the local database
     * is concerned?
     *
     * @param  mixed $input
     * @return bool
     */
    public function isComplete($input = null) : bool
    {
        $data = $data ?? $this->getStoreAsArray();

        if (empty($data['anchors_complete']) || empty($data['anchors'])) {
            return false;
        }

        // "Full" means anchors to both Etheruem and Bitcoin blockchains
        return count($data['anchors']) === 3; // "cal" + "btc" + "eth"
    }

}
