<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Controller;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\DataObject;

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
     * @var array
     */
    private static $allowed_actions = [
        'verify',
    ];

    /**
     * Responds to URIs of the following prototype: /verifiable/verify/<model>/<ID>
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
     * @todo Update "Status" to be one of: "Pending" | "Verified" | "Failed"
     */
    public function verify(HTTPRequest $request)
    {
        $class = $request->param('ClassName');
        $id = $request->param('ModelID');

        if (empty($id) || !is_numeric($id) || empty($class)) {
            return $this->httpError(400, 'Bad request');
        }

        if (!$record = $this->getVersionedRecord($class, $id)) {
            return $this->httpError(400, 'Bad request');
        }

        echo json_encode([
            'ID' => "$record->ID",
            'Class' => get_class($record),
            'Status' => $this->verifiableService->getVerificationStatus($record),
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Fetch a record directly from the relevant table, for the given class
     * and ID.
     *
     * @param  string     $class A fully-qualified PHP class name.
     * @param  int        $id    The RecordID of the desired Versioned record.
     * @return DataObject
     * @todo Add an instanceof DataObject check to prevent "SiteTree" being passed for example
     */
    private function getVersionedRecord(string $class, int $id) : DataObject
    {
        return Versioned::get_latest_version($class, $id);
    }

}
