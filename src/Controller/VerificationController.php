<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Controller;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Versioned\Versioned;

/**
 * Accepts incoming requests for data verification e.g. from within the CMS
 * or framework's admin area, and sends them on their way.
 *
 * Will proxy validation requests to the currently configured backend for both
 * {@link SiteTree} and {@link DataObject} subclasses.
 */
class VerificationController extends Controller
{
    /**
     * Represents a failed verification.
     *
     * @var string
     */
    const STATUS_FAILURE = 'FAIL';

    /**
     * Represents a passed verification.
     *
     * @var string
     */
    const STATUS_PASSED = 'PASS';

    /**
     * Represents a pending verification.
     *
     * @var string
     */
    const STATUS_PENDING = 'PENDING';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'verifyhash',
    ];

    /**
     * Responds to URIs of the following prototype: /verifiable/verify/<model>/<ID>/<VID>
     * by echoing a JSON response for consumption by client-side logic.
     *
     * Also provides x2 extension points, both of which are passed a copy of
     * the contents of the managed record's "Proof" {@link ChainpointProof} "Proof"
     * field, an indirect {@link DBField} subclass.
     *
     * - onBeforeVerify()
     * - onAfterVerify()
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

        if (!$record->getField('Proof')) {
            return $this->httpError(400, 'Bad request');
        }

        $response = json_encode([
            'RecordID' => "$record->RecordID",
            'Version' => "$record->Version",
            'Class' => get_class($record),
            'Submitted' => $record->dbObject('Proof')->getSubmittedAt(),
            'Status' => $this->getVerificationStatus($record),
        ], JSON_UNESCAPED_UNICODE);

        $this->renderJSON($response);
    }

    /**
     * Gives us the current verification status of the given record. Takes into
     * account the state of the saved proof as well as by making a backend
     * verification call.
     *
     * @param  StdClass $record
     * @return string
     * @todo What _is_ verification? That verifiable_fields data has NOT changed:
     *  1. If no full local proof: false
     *  2. If sending local proof to /verify fails: false, otherwise;
     *  3. Re-calculate hash
     *  4. Compare against local "Proof::hash()"
     *  5. Send hash-value to /proofs to see if we get a valid proof back
     *  6. Is Valid proof? Yes: Verified. No? Tampered-with
     */
    public function getVerificationStatus($record)
    {
        $hashToVerify = $this->verifiableService->hash($record->normaliseData()); // <-- Could have been tampered-with
        $hashIsVerified = $this->verifiableService->verify($hashToVerify);
        $proofData = $record->getField('Proof');
        $proofIsPartial = $this->verifiableService->proofIsPartial($proofData);
        $proofIsComplete = $this->verifiableService->proofIsComplete($proofData);

        switch (true) {
            case $proofIsComplete && !$hashIsVerified:
            default:
                return self::STATUS_FAILURE;
            case $proofIsPartial && !$hashIsVerified:
                return self::STATUS_PENDING;
            case $proofIsComplete && $proofIsVerified:
                return self::STATUS_PASS;
        }
    }

    /**
     * Fetch a record directly from the relevant table, for the given class
     * and ID.
     *
     * @param  string     $class   A fully-qualified PHP class name.
     * @param  int        $rid     The "RecordID" of the desired Versioned record.
     * @param  int        $version The "Version" of the desired Versioned record.
     * @return mixed null | DataObject
     * @todo Add an instanceof DataObject check to prevent "SiteTree" being passed for example
     */
    private function getVersionedRecord(string $class, int $id, int $vid)
    {
        return Versioned::get_version($class, $id, $vid);
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
