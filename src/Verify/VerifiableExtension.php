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
use SilverStripe\Forms\HiddenField;
use PhpTek\JSONText\ORM\FieldType\JSONText;
use SilverStripe\View\Requirements;

/**
 * By attaching this extension to any {@link DataObject} subclass and declaring a
 * $verifiable_fields array in YML config, all subsequent database writes will
 * be passed through here via {@link $this->onBeforeWrite()};
 *
 * This {@link DataExtension} also provides a single field to which all verified
 * and verifiable chainpoint proofs are stored in a queryable JSON-aware field.
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
     * @todo Implement in onAfterPublish() instead. Minimises HTTP requests to the backend.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        $verifiable = $this->verify();
        $owner = $this->getOwner();
        $this->verifiableService->setExtra();

        if (count($verifiable) && $proofData = $this->verifiableService->call('write', $verifiable)) {
            if (is_array($proofData)) {
                $proofData = json_encode($proofData);
            }

            $owner->setField('Proof', $proofData);
            $owner->setField('Extra', json_encode($this->verifiableService->getExtra()));
        }
    }

    /**
     * Call a custom verify() method on all decorated objects, if one exists.
     * This provides a flexible public API for hashing and verifying pretty much
     * anything.
     *
     * If no verify method exists, the default is to take the values of the YML
     * config "verifiable_fields" array, then hash and submit the values of those
     * fields. If no verifiable_fields are found or configured, we just return
     * an empty array and stop.
     *
     * @param  DataObject $record
     * @return array
     */
    public function verify($record = null) : array
    {
        $record = $record ?: $this->getOwner();
        $verifiable = [];

        if (method_exists($record, 'verify')) {
            $verifiable = (array) $record->verify();
        } else {
            $fields = $record->config()->get('verifiable_fields');

            foreach ($fields as $field) {
                if ($field === 'Proof') {
                    continue;
                }

                $verifiable[] = (string) $record->getField($field);
            }
        }

        return $verifiable;
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

        Requirements::javascript('phptek/verifiable: client/verifiable.js');

        $owner = $this->getOwner();
        $list = [];
        $versions = $owner->Versions()->sort('Version');

        foreach ($versions as $item) {
            $list[$item->Version] = sprintf('Version: %s (Created: %s)', $item->Version, $item->Created);
        }

        $fields->addFieldsToTab('Root.Verify', FieldList::create([
            LiteralField::create('Introduction', '<p class="message">Select a version'
                    . ' whose data you wish to verify, then select the "Verify"'
                    . ' button. After a few seconds, a verification status will be'
                    . ' displayed.</p>'),
            HiddenField::create('Type', null, get_class($owner)),
            DropdownField::create('Version', 'Version', $list)
                ->setEmptyString('-- Select One --'),
            FormAction::create('doVerify', 'Verify')
                ->setUseButtonTag(true)
                ->addExtraClass('btn action btn-outline-primary ')
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
