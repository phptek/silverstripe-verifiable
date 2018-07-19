<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Model;

use SilverStripe\ORM\DataExtension;
use PhpTek\Verifiable\ORM\Fieldtype\ChainpointProof;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\HiddenField;
use PhpTek\JSONText\ORM\FieldType\JSONText;
use SilverStripe\View\Requirements;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\DB;

/**
 * By attaching this extension to any {@link DataObject} subclass, it will therefore
 * be "verifiable aware". Declaring a `verify()` method on it, will automatically
 * make whatever the method returns, into that which is hashed and anchored to
 * the backend.
 *
 * If no `verify()` method is detected, the fallback is to assume that selected
 * fields on your data model should be combined and hashed. For this to work,
 * declare a `verifiable_fields` array in YML config. All subsequent publish actions
 * will be passed through here via {@link $this->onBeforeWrite()}.
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
     * When no `verify()` method is found on decorated objects, this is the list
     * of fields who's values will be hashed and committed to the current backend.
     *
     * @var array
     * @config
     */
    private static $verifiable_fields = [];

    /**
     * After each publish action, userland data coming from either a custom `verify()`
     * method or `$verifiable_fields` config, is compiled into a string, hashed and
     * submitted to the current backend.
     *
     * @return void
     */
    public function onAfterPublish()
    {
        $verifiable = $this->source();
        $owner = $this->getOwner();
        $this->service->setExtra();
        $doAnchor = (count($verifiable) && $owner->exists());

        if ($doAnchor && $proofData = $this->service->call('write', $verifiable)) {
            if (is_array($proofData)) {
                $proofData = json_encode($proofData);
            }

            // Update the versions table manually to avoid double publish problem
            // when a DO is marked as "changed"
            $latest = Versioned::get_latest_version(get_class($owner), $owner->ID);
            $table = sprintf('%s_Versions', $latest->config()->get('table_name'));
            DB::query(sprintf(
                'UPDATE "%s" SET "Proof" = \'%s\', Extra = \'%s\' WHERE "RecordID" = %d AND "Version" = %d',
                $table,
                $proofData,
                json_encode($this->service->getExtra()),
                $latest->ID,
                $latest->Version
            ));
        }
    }

    /**
     * Source the data that will end-up hashed and submitted. This method will
     * call a custom verify() method on all decorated objects, if one is defined.
     * This provides a flexible public API for hashing and verifying pretty much
     * anything.
     *
     * If no such method exists, the default is to take the values of the YML
     * config "verifiable_fields" array, then hash and submit the values of those
     * fields. If no verifiable_fields are found or configured, we just return
     * an empty array and just stop.
     *
     * @param  DataObject $record
     * @return array
     */
    public function source($record = null) : array
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
        $list = $disabled = [];
        $versions = $owner->Versions()->sort('Version');

        foreach ($versions as $item) {
            if ($item->Version == 1) {
                $disabled[] = $item->Version;
            }

            $list[$item->Version] = sprintf('Version: %s (Created: %s)', $item->Version, $item->Created);
        }

        $fields->addFieldsToTab('Root.Verify', FieldList::create([
            LiteralField::create('Introduction', '<p class="message">Select a version'
                    . ' whose data you wish to verify, then select the "Verify"'
                    . ' button. After a few seconds, a verification status will be'
                    . ' displayed.</p>'),
            HiddenField::create('Type', null, get_class($owner)),
            DropdownField::create('Version', 'Version', $list)
                ->setEmptyString('-- Select One --')
                ->setDisabledItems($disabled),
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
