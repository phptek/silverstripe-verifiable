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
 * be passed through here via {@link $this->onBeforeWrite()};
 *
 * This {@link DataExtension} also provides a single field to which all verified
 * and verifiable chainpoint proofs are stored in a queryable JSON-aware field.
 *
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
     * Before each write, data from the $verifiable_fields config is compiled
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
     */
    public function onBeforeWrite()
    {
        $verifiable = $this->normaliseData();

        if (count($verifiable) && $proofData = $this->verifiableService->write($verifiable)) {
            if (is_array($proofData)) {
                $proofData = json_encode($proofData);
            }

            $this->getOwner()->setField('Proof', $proofData);
        }

        parent::onBeforeWrite();
    }

    /**
     * Normalise this model's data so it's suited to being hashed.
     *
     * @param  DataObject $record
     * @return array
     */
    public function normaliseData($record = null) : array
    {
        $record = $record ?: $this->getOwner();
        $fields = $record->config()->get('verifiable_fields');
        $verifiable = [];

        foreach ($fields as $field) {
            $verifiable[] = (string) $record->getField($field);
        }

        return $verifiable;
    }

    /**
     * Adds a "Verification" tab to the CMS.
     *
     * @param  FieldList $fields
     * @return void
     * @todo Complete a basic CMS UI
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
