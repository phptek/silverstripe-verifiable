<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\ORM\FieldType;

use PhpTek\JSONText\ORM\FieldType\JSONText;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use PhpTek\Verifiable\Util\Util;

/**
 * Encapsulates a single chainpoint proof as returned by the currently active Merkle
 * store e.g. a Blockchain.
 *
 * Makes use of the {@link JSONText} package and wraps simple queries around
 * its raw JSONQuery calls.
 *
 * @todo Switch all refs to VerifiableBackendException for a new VerifiableChainpointException
 */
class ChainpointProof extends JSONText
{

    /**
     * Chainpoint specific model format
     *
     * @var int
     */
    const MODEL_TYPE_PHR = 1; // PostHashResponse

    /**
     * Chainpoint specific model format
     *
     * @var int
     */
    const MODEL_TYPE_GPR = 2; // GetProofsResponse

    /**
     * Chainpoint specific model format
     *
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
            return !empty($value[0]) ? $value : [];
        }

        return [];
    }

    /**
     * Returns the generated value of the proof's "hash_id_node" key. This is used
     * as a UUID for proofs.
     *
     * @return string
     */
    public function getProof() : string
    {
        $this->setReturnType('array');

        if (!empty($value = $this->query('$..proof'))) {
            return $value[0];
        }

        return '';
    }

    /**
     * Return a valid v3 JSON-LD Chainpoint Proof; The value of the proof field
     * in JSON-LD format.
     *
     * @return string
     * @throws VerifiableBackendException
     */
    public function getProofJson() : string
    {
        if (!function_exists('zlib_decode')) {
            throw new VerifiableBackendException('zlib is not enabled!');
        }

        if (!function_exists('msgpack_unpack')) {
            throw new VerifiableBackendException('msgpack extension is not enabled!');
        }

        if ($proof = $this->getProof()) {
            $data = msgpack_unpack(zlib_decode(base64_decode($proof)));

            return str_replace('\\/', '/', json_encode($data, JSON_PRETTY_PRINT));
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
     * Returns all the "anchors" objects for the currently stored proof.
     *
     * @param  string $type Leave empty to get all "anchors" objects.
     * @return array
     */
    public function getAnchors(string $type = '') : array
    {
        $this->setReturnType('array');

        if ($type === 'cal') {
            $query = '$..branches..ops..anchors';
        } else if ($type === 'btc') {
            $query = '$..branches..branches..ops..anchors';
        } else {
            $query = '$..anchors';
        }

        $value = $this->query($query);

        if (!empty($value)) {
            return $value[0];
        }

        return [];
    }

    /**
     * Return the Merkle Root from a remote URI.
     *
     * @param  string $type One of 'cal' or 'btc'
     * @return string
     * @throws VerifiableBackendException
     */
    public function getMerkleRoot(string $type = 'cal') : string
    {
        if (!$anchor = $this->getAnchors($type)) {
            throw new VerifiableBackendException('No Merkle Root found!');
        }

        if (!empty($anchor[0]['uris'][0])) {
            // TODO Smell
            if (Util::is_running_test()) {
                return '';
            } else if (ini_get('allow_url_fopen') !== "1" || !$root = file_get_contents($anchor[0]['uris'][0])) {
                throw new VerifiableBackendException('Unable to read remote file.');
            }

            return $root;
        }
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
            case isset($data['meta']):
                return self::MODEL_TYPE_PHR;
            case isset($data[0]['proof']):
                return self::MODEL_TYPE_GPR;
            case isset($data[0]['proof_index']):
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
     * Returns the Bitcoin specific branch
     *
     * @return array
     */
    public function getBitcoinOps() : array
    {
        $this->setReturnType('array');

        // PostHashResponse
        $field = 'branches..branches';

        if (!empty($value = $this->query('->>', $field))) {
            if (preg_match('#^btc#', $value[$field][0]['label'])) {
                return $value[$field][0]['ops'];
            }
        }

        return [];
    }

    /**
     * Returns the Ethereum specific branch
     *
     * @return string
     */
    public function getEthereumOps() : string
    {
        throw new \Exception('NOOP');
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

    /**
     * Return the Bitcoin block height, if one exists.
     *
     * @return string
     * @throws VerifiableBackendException
     */
    public function getBitcoinBlockHeight() : string
    {
        if (!$anchors = $this->getAnchors('btc')) {
            throw new VerifiableBackendException('No BTC block data found!');
        }

        return $anchors[0]['anchor_id'];
    }

}
