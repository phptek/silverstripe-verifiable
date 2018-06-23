<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\ORM\FieldType;

use PhpTek\JSONText\ORM\FieldType\JSONText;

/**
 * Encapsulates a single chainpoint proof as returned by the currently active Merkle
 * store e.g. a Blockchain.
 *
 * Makes use of the {@link JSONText} package and wraps simple queries around
 * its raw JSONQuery calls.
 *
 * @todo Add support for exporting in MsgPack format (http://msgpack.org/index.html)
 * @todo Create a new JSONText subclass called "VerifiableFields" and use it to store
 * the verifiable_fields originally used to hash this record's data.
 */
class ChainpointProof extends JSONText
{

    /**
     * Returns the generated value of the proof's "hash_id_node" key. This is used
     * as a UUID for proofs.
     *
     * @return string
     */
    public function getHashIdNode() : string
    {
        $this->setReturnType('array');

        $field = 'hash_id_node';

        if (!empty($value = $this->query('->>', $field))) {
            return $value[$field];
        }

        return '';
    }

    /**
     * Returns the generated value of the proof's "hash" key.
     *
     * @return string
     */
    public function getHash() : string
    {
        $this->setReturnType('array');

        $field = 'hash';

        if (!empty($value = $this->query('->>', $field))) {
            return $value[$field];
        }

        return '';
    }

    /**
     * Returns the generated value of the proof's "status" key.
     *
     * @return string
     */
    public function getStatus() : string
    {
        $this->setReturnType('array');

        $field = 'status';

        if (!empty($value = $this->query('->>', $field))) {
            return $value[$field];
        }

        return '';
    }

    /**
     * Returns the generated value of the proof's "submitted_at" key.
     *
     * @return string
     */
    public function getSubmittedAt() : string
    {
        $this->setReturnType('array');

        $field = 'submitted_at';

        if (!empty($value = $this->query('->>', $field))) {
            return $value[$field];
        }

        return '';
    }

    /**
     * Returns the generated value of the proof's "proof" key.
     *
     * @return string
     */
    public function getProof() : string
    {
        $this->setReturnType('array');

        $field = 'proof';

        if (!empty($value = $this->query('->>', $field))) {
            return $value[$field];
        }

        return '';
    }

    /**
     * Attempts to match a hash against a locally stored proof.
     *
     * @param string $hash
     * @return bool
     */
    public function match(string $hash)
    {
        return $this->getHash() === $hash;
    }

    /**
     * Does the passed data response represent a PARTIAL verification as far as
     * the local database is concerned?
     *
     * @param  mixed $input
     * @return bool
     */
    public function isPartial($input = null) : bool
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
