<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Controller;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Versioned\Versioned;
use SilverStripe\CMS\Model\SiteTree;
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
     * @config
     * @var array
     */
    private static $allowed_actions = [
        'page',
        'model',
    ];

    /**
     * Verify a page: /verify/page/<ID> by echoing a JSON response for
     * consumption by client-side logic.
     *
     * @param  HTTPRequest $request
     * @return void
     */
    public function page(HTTPRequest $request)
    {
        $id = $request->param('ID');

        if (empty($id) || !is_numeric($id)) {
            return $this->httpError(400, 'Bad request');
        }

        if (!$record = $this->getVersionedRecord(SiteTree::class, $id)) {
            return false;
        }

        $proof = $record->dbObject('Proof');
        $result = json_decode($this->verifiableService->read($proof->getHashIdNode()), true);

        echo $this->verificationResponse($record, $result);
    }

    /**
     * Verify a data model: /verify/model/<Class>/<ID> by echoing a JSON response for
     * consumption by client-side logic.
     *
     * @param  HTTPRequest $request
     * @return void
     */
    public function model(HTTPRequest $request)
    {
        $class = $request->param('Class');
        $id = $request->param('ID');

        if (empty($id) || !is_numeric($id) || empty($class)) {
            return $this->httpError(400, 'Bad request');
        }

        if (!$record = $this->getVersionedRecord($class, $id)) {
            return false;
        }

        $proof = $record->dbObject('Proof');
        $result = json_decode($this->verifiableService->read($proof->getHashIdNode()), true);

        echo $this->verificationResponse($record, $result);
    }

    /**
     * Fetch a record directly from the relevant table, for the given class
     * and ID.
     *
     * @param  string     $class A fully-qualified PHP class name.
     * @param  int        $id    The RecordID of the desired Versioned record.
     * @return DataObject
     */
    private function getVersionedRecord(string $class, int $id) : DataObject
    {
        return Versioned::get_latest_version($class, $id);
    }

    /**
     * Return an JSON representation of the verification result for internal
     * use.
     *
     * @param  DataObject $record
     * @param  string     $result
     * @return string
     */
    private function verificationResponse($record, $result)
    {
        $isVerified = $record->verify($result, false) ? 'true' : 'false';

        return json_encode([
            'ID' => "$record->ID",
            'Class' => get_class($record),
            'IsVerified' => $isVerified,
        ], JSON_UNESCAPED_UNICODE);
    }

}
