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
    public function match(string $hash) : bool
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

        return isset($data['anchors']) && count($data['anchors']) <= 1;
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
        $valid = 0;

        // There can be two types of proof:
        // The "binary" kind, as returned from chainpoint.org's /verify endpoint
        // The "full" proof, as returned from chainpoint.org's /proofs endpoint
        $isFull = isset($data[0]['proof']);

        if ($isFull) {
            if (!empty($data[0]['proof']['branches'])) {
                // Calendar
                if (!empty($end = end($data[0]['proof']['branches'][0]['ops'])['anchors'][0]['anchor_id'])) {
                    $valid++;
                }
                // Bitcoin
                if (!empty($end = end($data[0]['proof']['branches'][0]['branches'][0]['ops'])['anchors'][0]['anchor_id'])) {
                    $valid++;
                }
            }
        } else {
            if (!empty($data[0]['anchors']) && count($data[0]['anchors']) >= 2) {
                foreach ($data[0]['anchors'] as $anchor) {
                    if (isset($anchor['valid']) && $anchor['valid'] === true) {
                        $valid++;
                    }
                }
            }
        }

        // "Full" means anchors to at least Tierion's internal "Calendar" and Bitcoin blockchains
        return $valid >= 2;
    }

}
