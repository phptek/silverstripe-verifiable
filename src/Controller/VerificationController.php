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
     * Verify a page.
     *
     * @param  HTTPRequest $request
     * @return bool True if verified, false otherwise.
     */
    public function page(HTTPRequest $request) : bool
    {
        $id = $request->param('ID');

        if (empty($id) || !is_numeric($id)) {
            return $this->httpError(400, 'Bad request');
        }

        if (!$record = $this->getVersionedRecord(SiteTree::class, $id)) {
            return false;
        }

        $proof = $record->dbObject('Proof');

        return $this->verifiableService->read($proof->getHashIdNode());
    }

    /**
     * Verify a data model.
     *
     * @param  HTTPRequest $request
     * @return bool True if verified, false otherwise.
     */
    public function model(HTTPRequest $request) : bool
    {
        $class = $request->param('Class');
        $id = $request->param('ID');

        if (empty($id) || !is_numeric($id) || empty($class)) {
            return $this->httpError(400, 'Bad request');
        }

        if (!$record = $this->getVersionedRecord($class, $id)) {
            return false;
        }

        $proof = $record->dbObject('Proof')->getHashIdNode();

        return $this->verifiableService->read($proof);
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

}
