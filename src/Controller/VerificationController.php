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

/**
 * Accepts incoming requests for data verification e.g. from within the CMS
 * or framework's admin area, and sends them on their way.
 *
 * Will proxy validation requests to the currently configured backend for both
 * {@link SiteTree} and {@link DataObject} subclasses.
 *
 * @todo Take into account LastEdited and Created dates, outside of userland control
 * of verifiable_fields
 */
class VerificationController extends Controller
{
    /**
     * No proof found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_PROOF_NONE = 'No Proof';

    /**
     * Invalid proof found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_PROOF_INVALID = 'Invalid Proof';

    /**
     * Invalid local hash found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_HASH_LOCAL_INVALID = 'Local Hash Invalid';

    /**
     * Invalid remote hash found. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_HASH_REMOTE_INVALID_NO_DATA = 'Remote Hash No Data';

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
     * All local checks passed. Submitted hash is currently pending.
     *
     * @var string
     */
    const STATUS_PENDING = 'Pending';

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
        $vid = $request->param('VersionID');

        if (empty($id) || !is_numeric($id) ||
                empty($vid) || !is_numeric($vid) ||
                empty($class)) {
            return $this->httpError(400, 'Bad request');
        }

        if (!$record = $this->getVersionedRecord($class, $id, $vid)) {
            return $this->httpError(400, 'Bad request');
        }

        $response = json_encode([
            'RecordID' => "$record->RecordID",
            'Version' => "$record->Version",
            'Class' => get_class($record),
            'Status' => $this->getVerificationStatus($record),
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
     * @return string
     * @todo Add tests
     */
    public function getVerificationStatus($record)
    {
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
        if (!$proof->match($reHash = $this->verifiableService->hash($record->normaliseData()))) {
            return self::STATUS_HASH_LOCAL_INVALID;
        }

        // Remote verification check that local hash_node_id returns a valid response
        // Responds with a binary format proof
        $responseBinary = $this->verifiableService->read($proof->getHashIdNode());

        if ($responseBinary === '[]') {
            return self::STATUS_UUID_INVALID;
        }

        // Send that binary hash back to the remote and retrieve the stored hash
        // Could be a full or a partial response
        $responseProof = ChainpointProof::create()->setValue($responseBinary);

        if ($responseProof->isPartial()) {
            return self::STATUS_PENDING;
        }

        if (!$responseJson = $this->verifiableService->verify($responseBinary)) {
            return self::STATUS_HASH_REMOTE_INVALID_NO_DATA;
        }

        // Compare returned hash matches the re-hash
        $responseProof = ChainpointProof::create()->setValue($responseJson);

        if (!$responseProof->match($reHash)) {
            return self::STATUS_HASH_REMOTE_INVALID_NO_HASH;
        }

        return self::STATUS_VERIFIED;
    }

    /**
     * Fetch a record directly from the relevant table, for the given class
     * and ID.
     *
     * @param  string     $class   A fully-qualified PHP class name.
     * @param  int        $id      The "RecordID" of the desired Versioned record.
     * @param  int        $version The "Version" of the desired Versioned record.
     * @return mixed null | DataObject
     */
    private function getVersionedRecord(string $class, int $id, int $version)
    {
        return Versioned::get_version($class, $id, $version);
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
