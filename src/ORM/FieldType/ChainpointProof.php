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
     * @var int
     */
    const MODEL_TYPE_PHR = 1; // PostHashResponse

    /**
     * @var int
     */
    const MODEL_TYPE_GPR = 2; // GetProofsResponse

    /**
     * @var int
     */
    const MODEL_TYPE_PVR = 3; // PostVerifyResponse

    /**
     * Returns the generated value of the proof's "hash_id_node" key. This is used
     * as a UUID for proofs.
     *
     * @return array
     */
    public function getHashIdNode() : array
    {
        $this->setReturnType('array');

        if (!empty($value = $this->query('$..hash_id_node'))) {
            return $value;
        }

        return [];
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

        // PostHashResponse
        $field = 'submitted_at';

        if (!empty($value = $this->query('->>', $field))) {
            return $value[$field];
        }

        // PostVerifyResponse
        $field = 'hash_submitted_node_at';

        if (!empty($value = $this->query('->>', $field))) {
            return $value[$field];
        }

        return '';
    }

    /**
     * Returns all the "anchor" objects for the currently stored proof.
     *
     * Example return value:
     *
     * <code>
     * ["anchors"]=>
     * array(1) {
     * [0]=>
     *  array(3) {
     *    ["branch"]=>
     *    string(17) "cal_anchor_branch"
     *    ["type"]=>
     *    string(3) "cal"
     *    ["valid"]=>
     *    bool(true)
     *  }
     * }
     * </code>
     *
     * @return array
     */
    public function getAnchors() : array
    {
        $this->setReturnType('array');

        if (!empty($value = $this->query('$..anchors'))) {
            return $value;
        }

        return [];
    }

    /**
     * Returns the type of Proof model we currently have. For a full list of the types
     * and the REST requests made, for which are returned; refer to the SwaggerHub docs:
     * https://app.swaggerhub.com/apis/chainpoint/node/1.0.0#/hashes/post_hashes.
     *
     * @return int
     */
    public function getModelType() : int
    {
        $data = $data ?? $this->getStoreAsArray();

        switch (true) {
            case !empty($data['meta']):
                return self::MODEL_TYPE_PHR;
            case !empty($data[0]['proof']):
                return self::MODEL_TYPE_GPR;
            case !empty($data[0]['proof_index']):
                return self::MODEL_TYPE_PVR;
            default:
                return 0;
        }
    }

    /**
     * Does the proof's data represent an INITIAL proof? The type we get straight
     * from a request to the /hashes endpoint?
     *
     * @return bool
     */
    public function isInitial() : bool
    {
        return $this->getModelType() === self::MODEL_TYPE_PHR;
    }

    /**
     * Does the proof's data represent a FULL verification as far as the local database
     * is concerned?
     *
     * @return bool
     */
    public function isFull() : bool
    {
        return $this->getModelType() === self::MODEL_TYPE_GPR && count($this->getAnchors()) >= 2;
    }

    /**
     * Does the proof's data represent a PARTIAL verification as far as the local database
     * is concerned?
     *
     * @return bool
     */
    public function isPending() : bool
    {
        return $this->getModelType() === self::MODEL_TYPE_GPR && count($this->getAnchors()) === 1;
    }

    /**
     * Is this full proof verified? A verified proof is one that is confirmed on
     * Tierion's ("calendar") blockchain, and at least one other e.g. Bitcoin.
     *
     * @return bool
     * @todo If a full-proof contains >1 "anchors" block, what is the value of 'status'?
     */
    public function isVerified() : bool
    {
        if (!$this->isFull() || count($this->getAnchors()) <= 1) {
            return false;
        }

        return $this->getStatus() === 'verified';
    }

}
