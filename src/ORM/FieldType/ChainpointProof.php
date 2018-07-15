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
     * @return array
     */
    public function getAnchors() : array
    {
        $this->setReturnType('array');
        $value = $this->query('$..anchors');

        if (!empty($value)) {
            return $value[0];
        }

        return [];
    }

    /**
     * Returns all the "anchors_complete" objects for the currently stored proof.
     *
     * @return array
     */
    public function getAnchorsComplete() : array
    {
        $this->setReturnType('array');
        $value = $this->query('$..anchors_complete');

        if (!empty($value)) {
            return $value[0];
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
     * "Initial" is not an official name for a particular response format.
     * We have invented it. This method will tell us if the format of the stored
     * proof is of the type that a node will immediately return upon receiving a
     * POST request to the /hashes endpoint.
     *
     * An example of such a response can be found in:  test/fixture/response-initial.json.
     *
     * @return bool
     */
    public function isInitial() : bool
    {
        return $this->getModelType() === self::MODEL_TYPE_PHR;
    }

    /**
     * "Pending" is an official name for a particular response format. This method
     * will tell us if the format of the stored proof is of the type that a node
     * will immediately return upon receiving a GET request to the /proofs
     * endpoint, 15s or more AFTER receiving a POST request to the /hashes endpoint.
     *
     * An example of such a response can be found in:  test/fixture/response-pending.json.
     *
     * @return bool
     */
    public function isPending() : bool
    {
        return $this->getModelType() === self::MODEL_TYPE_GPR && count($this->getAnchorsComplete()) === 1;
    }

    /**
     * "Full" is an official name for a particular response format. This method
     * will tell us if the format of the stored proof is of the type that a node
     * will immediately return upon receiving a GET request to the /proofs
     * endpoint, 2h or more AFTER receiving a POST request to the /hashes endpoint.
     *
     * An example of such a response can be found in:  test/fixture/response-full.json.
     *
     * @return bool
     */
    public function isFull() : bool
    {
        return $this->getModelType() === self::MODEL_TYPE_GPR && count($this->getAnchorsComplete()) >= 2;
    }

    /**
     * "Verified" is an official name, but not for a particular response format.
     * Rather for determining if a full-proof is verified.
     *
     * @return bool
     * @todo If a full-proof contains >1 "anchors" block, what is the value of 'status'?
     */
    public function isVerified() : bool
    {
        return $this->getModelType() === self::MODEL_TYPE_PVR && $this->getStatus() === 'verified';
    }

}
