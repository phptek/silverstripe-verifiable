<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Verify;

use SilverStripe\ORM\DataExtension;
use PhpTek\Verifiable\ORM\Fieldtype\ChainpointProof;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;

/**
 * By attaching this extension to any {@link DataObject} subclass and declaring a
 * $verifiable_fields array in YML config, all subsequent database writes will
 * be passed through here via {@link $this->onAfterWrite()};
 *
 * This {@link DataExtension} also provides a single field to which all verified
 * and verifiable chainpoint proofs are stored in a queryable JSON-aware field.
 *
 * @todo Store the hash function used and if subsequent verifications
 *       fail because differing hash functions are used, throw an exception.
 * @todo Hard-code "Created" and "LastEdited" fields into "verifiable_fields"
 * @todo Prevent "Proof" field from ever being configured in verifiable_fields
 * @todo Use AsyncPHP to make the initial write call to the backend, wait ~15s and then request a proof in return
 */
class VerifiableExtension extends DataExtension
{
    /**
     * Declares a JSON-aware {@link DBField} where all chainpoint proofs are stored.
     *
     * @var array
     * @config
     */
    private static $db = [
        'Proof' => ChainpointProof::class,
    ];

    /**
     * The field-values that will be hashed and committed to the current backend.
     *
     * @var array
     * @config
     */
    private static $verifiable_fields = [];

    /**
     * Write -->onAfterWrite() recursion prevention.
     *
     * @var string
     */
    protected static $has_write_occured = false;

    /**
     * After each write, data from the $verifiable_fields config is compiled
     * into a string, hashed and submitted to the current backend.
     *
     * Once written, we periodically poll the backend to receive the full
     * chainpoint proof (it takes time for Bitcoin's PoW confirmations, not so
     * much for Ethereum).
     *
     * We need this "complete" proof for subsequent verification checks
     * also made against the same backend in the future.
     *
     * If only the "Proof" field has been written-to, or no-data is found in the
     * verifiable_fields, this should not constitute a write that we need to do
     * anything with, and it's therefore skipped.
     *
     * @return void
     * @todo Add a check into $skipWriteProof for checking _only_ "Proof" having changed.
     * This should _not_ constitute a change requiting a call to the backend for.
     */
    public function onAfterWrite()
    {
        parent::onAfterWrite();

        // Overflow protection should bad recursion occur
        static $write_count = 0;
        $write_count++;

        $verifiable = $this->normaliseData();
        $skipWriteProof = self::$has_write_occured === true || !count($verifiable) || $write_count > 1;

        if ($skipWriteProof) {
            // Now we fire-off a job to check if verification is complete
            // TODO: Use AsyncPHP and use a callback OR check if API has a callback
            // endpoint it can call on our end (Tierion's API does)
            // $this->verifiableService->queueVerification($this->getOwner());

            return;
        }

        if ($proofData = $this->verifiableService->write($verifiable)) {
            self::$has_write_occured = true;

            $this->writeProof($proofData);
        }
    }

    /**
     * Normalise this model's data so it's suited to being hashed.
     *
     * @return array
     * @todo use array_reduce()?
     */
    public function normaliseData() : array
    {
        $fields = $this->getOwner()->config()->get('verifiable_fields');
        $verifiable = [];

        foreach ($fields as $field) {
            $verifiable[] = (string) $this->getOwner()->getField($field);
        }

        return $verifiable;
    }

    /**
     * Writes string data that is assumed to be JSON (as returned from a
     * web-service for example) and saved to this decorated object's "Proof"
     * field.
     *
     * @param  mixed $proof
     * @return void
     */
    public function writeProof($proof)
    {
        if (is_array($proof)) {
            $proof = json_encode($proof);
        }

        $owner = $this->getOwner();
        $owner->setField('Proof', $proof);
        $owner->write();
    }

    /**
     * Adds a "Verification" tab to the CMS.
     *
     * @param  FieldList $fields
     * @return void
     */
    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);

        $owner = $this->getOwner();
        $list = $owner->Versions();

        $fields->addFieldsToTab('Root.Verify', FieldList::create([
            LiteralField::create('Introduction', '<p class="vry-intro"></p>'),
            DropdownField::create('Version', 'Version', $list->toArray())
                ->setEmptyString('-- Select One --'),
                FormAction::create('doVerify', 'Verify')
        ]));
    }

}
