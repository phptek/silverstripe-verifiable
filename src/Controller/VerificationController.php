<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Controller;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Versioned\Versioned;
use PhpTek\Verifiable\ORM\FieldType\ChainpointProof;
use SilverStripe\ORM\ValidationException;

/**
 * Accepts incoming requests for data verification e.g. from within the CMS
 * or framework's admin area, and sends them on their way.
 *
 * Will proxy validation requests to the currently configured backend for both
 * {@link SiteTree} and {@link DataObject} subclasses.
 *
 * @todo Take into account LastEdited and Created dates, outside of userland control
 * of verifiable_fields
 * @todo Rename to "VerifiableController"
 */
class VerificationController extends Controller
{
    /**
     * No proof found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_PROOF_NONE = 'No Proof Found';

    /**
     * Invalid proof found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_PROOF_INVALID = 'Invalid Proof Found';

    /**
     * Invalid local hash found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_HASH_LOCAL_INVALID = 'Local Hash Invalid';

    /**
     * Invalid or no remote proof found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_HASH_REMOTE_INVALID_NO_DATA = 'Remote Hash Not Found';

    /**
     * Invalid remote hash found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_HASH_REMOTE_INVALID_NO_HASH = 'Remote Hash Not Found';

    /**
     * Invalid UUID. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_UUID_INVALID = 'Invalid UUID';

    /**
     * All checks passed. Submitted hash is verified.
     *
     * @var string
     */
    const STATUS_VERIFIED = 'Verified';

    /**
     * All checks passed. But submitted hash is not yet verified.
     *
     * @var string
     */
    const STATUS_UNVERIFIED = 'Unverified';

    /**
     * All local checks passed. Submitted hash is currently pending.
     *
     * @var string
     */
    const STATUS_PENDING = 'Pending';

    /**
     * Some kind of upstream error.
     *
     * @var string
     */
    const STATUS_UPSTREAM_ERROR = 'Upstream Error';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'verifyhash',
    ];

    /**
     * Verify the integrity of arbitrary data by means of a single hash.
     *
     * Responds to URIs of the following prototype: /verifiable/verify/<model>/<ID>/<VID>
     * by echoing a JSON response for consumption by client-side logic.
     *
     * @param  HTTPRequest $request
     * @return void
     */
    public function verifyhash(HTTPRequest $request)
    {
        $class = $request->param('ClassName');
        $id = $request->param('ModelID');
        $version = $request->param('VersionID');

        if (empty($id) || !is_numeric($id) ||
                empty($version) || !is_numeric($version) ||
                empty($class)) {
            return $this->httpError(400, 'Bad request');
        }

        if (!$record = Versioned::get_version($class, $id, $version)) {
            return $this->httpError(400, 'Bad request');
        }

        try {
            $status = $this->getVerificationStatus($record, $record->getExtraByIndex());
        } catch (ValidationException $ex) {
            $status = self::STATUS_UPSTREAM_ERROR;
        }

        $response = json_encode([
            'RecordID' => "$record->RecordID",
            'Version' => "$record->Version",
            'Class' => get_class($record),
            'Status' => $status,
            'SubmittedAt' => $record->dbObject('Proof')->getSubmittedAt(),
            'SubmittedTo' => $record->dbObject('Extra')->getStoreAsArray(),
        ], JSON_UNESCAPED_UNICODE);

        $this->renderJSON($response);
    }

    /**
     * Gives us the current verification status of the given record. Takes into
     * account the state of the saved proof as well as by making a backend
     * verification call.
     *
     * For the ChainPoint Backend, the following process occurs:
     *
     * 1. Re-hash verifiable_fields as stored within the "Proof" field
     * 2. Assert that the record's "Proof" field is not empty
     * 3. Assert that the record's "Proof" field contains a valid proof
     * 4. Assert that the new hash exists in the record's "Proof" field
     * 5. Assert that hash_node_id for that proof returns a valid response from ChainPoint
     * 6. Assert that the returned data contains a matching hash for the new hash
     *
     * @param  DataObject $record
     * @param  array      $nodes
     * @return string
     * @todo Add tests
     */
    public function getVerificationStatus($record, $nodes)
    {
        // Set some extra data on the service. In this case, the actual chainpoint
        // node addresses, used to submit hashes for the given $record
        $this->verifiableService->setNodes($nodes);

        // Basic existence of proof (!!) check
        if (!$proof = $record->dbObject('Proof')) {
            return self::STATUS_PROOF_NONE;
        }

        // Basic proof validity check
        // @todo Beef this up to ensure that a basic regex is run over each to ensure it's all
        // not just gobbledygook
        if (!$proof->getHashIdNode() || !$proof->getHash() || !$proof->getSubmittedAt()) {
            return self::STATUS_PROOF_INVALID;
        }

        // Comparison check between locally stored proof, and re-hashed record data
        if ($proof->getHash() !== $foo = $this->verifiableService->hash($record->normaliseData())) {
            return self::STATUS_HASH_LOCAL_INVALID;
        }

        // Remote verification check that local hash_node_id returns a valid response
        // Responds with a binary format proof
        $responseBinary = $this->verifiableService->read($proof->getHashIdNode());

        if ($responseBinary === '[]') {
            return self::STATUS_UUID_INVALID;
        }

        $responseVerify = $this->verifiableService->verify($responseBinary);

        if ($responseVerify === '[]') {
            return self::STATUS_HASH_REMOTE_INVALID_NO_DATA;
        }

        // Compare returned hash matches the re-hash
        $responseProof = ChainpointProof::create()->setValue($responseVerify);

        if (!$responseProof->match($reHash)) {
            return self::STATUS_HASH_REMOTE_INVALID_NO_HASH;
        }

        if ($responseProof->getStatus() === 'verified') {
            return self::STATUS_VERIFIED;
        }

        return self::STATUS_UNVERIFIED;
    }

    /**
     * Properly return JSON, allowing consumers to render returned JSON correctly.
     *
     * @param  string $json
     * @return void
     */
    private function renderJSON(string $json)
    {
        header('Content-Type: application/json');
        echo $json;
        exit(0);
    }

}
