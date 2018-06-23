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
use SilverStripe\Forms\ToggleCompositeField;
use PhpTek\JSONText\ORM\FieldType\JSONText;

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
 * @todo Use crontask module to periodically query backends for a full proof
 * @todo WARNING: Tight coupling between: Extension <=> Service <=> Backend (Node "Discovery" is chainpoint-specific)
 * @todo Rename x2 fields and prefix with 'v' ??
 * @todo Save only the IP octets, no need to save "http(s)?" in the "Extra" field
 * @todo Avoid node-discovery in anywhere _other_ than the CMS / admin UI
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
        'Extra' => JSONText::class,
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
     * @return void
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $verifiable = $this->normaliseData();
        $owner = $this->getOwner();
        $this->verifiableService->setExtra();

        if (count($verifiable) && $proofData = $this->verifiableService->write($verifiable)) {
            if (is_array($proofData)) {
                $proofData = json_encode($proofData);
            }

            $owner->setField('Proof', $proofData);
            $owner->setField('Extra', json_encode($this->verifiableService->getExtra()));
        }
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
        $list = [];
        $versions = $owner->Versions()->sort('Version');
        $keyTable = file_get_contents(realpath(__DIR__) . '/../../doc/statuses.html');

        foreach ($versions as $item) {
            $list[$item->Version] = sprintf('Version: %s (Created: %s)', $item->Version, $item->Created);
        }

        $fields->addFieldsToTab('Root.Verify', FieldList::create([
            LiteralField::create('Introduction', '<p class="message">Select a version'
                    . ' whose data you wish to verify, then select the "Verify"'
                    . ' button. After a few seconds, a verification status will be'
                    . ' displayed. Please refer to the "Status Key" table below to'
                    . ' interpret the result.</p>'),
            DropdownField::create('Version', 'Version', $list)
                ->setEmptyString('-- Select One --'),
                FormAction::create('doVerify', 'Verify'),
            ToggleCompositeField::create('KeyTable', 'Status Key', LiteralField::create('Foo', $keyTable)),
        ]));
    }

    /**
     * Get the contents of this model's "Extra" field by numeric index.
     *
     * @param  int $num
     * @return mixed array | int
     */
    public function getExtraByIndex(int $num = null)
    {
        $extra = $this->getOwner()->dbObject('Extra');
        $extra->setReturnType('array');

        if (!$num) {
            return $extra->getStoreAsArray();
        }

        if (!empty($value = $extra->nth($num))) {
            return is_array($value) ? $value[0] : $value; // <-- stuuupId. Needs fixing in JSONText
        }

        return [];
    }

}
