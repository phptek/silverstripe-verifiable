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
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\DataObject;

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
    // This data-model is using the default "verifiable_fields" mode
    const SOURCE_MODE_FIELD = 1;
    // This data-model is using the custom "verify" function mode
    const SOURCE_MODE_FUNC = 2;

    /**
     * Declares a JSON-aware {@link DBField} where all chainpoint proofs are stored.
     *
     * @var array
     * @config
     */
    private static $db = [
        'Proof' => ChainpointProof::class,
        'Extra' => JSONText::class,
        'VerifiableFields' => JSONText::class,
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
     * Which source mode are we using?
     *
     * @return int
     */
    public function sourceMode()
    {
        if (method_exists($this->getOwner(), 'verify')) {
            return self::SOURCE_MODE_FUNC;
        }

        return self::SOURCE_MODE_FIELD;
    }

    /**
     * After each publish action, userland data coming from either a custom `verify()`
     * method or `$verifiable_fields` config, is compiled into a string, hashed and
     * submitted to the current backend.
     *
     * Note: We update the versions table manually to avoid double publish problem
     * where a DO is marked internally as "changed".
     *
     * @return void
     */
    public function onAfterPublish()
    {
        $owner = $this->getOwner();
        $latest = Versioned::get_latest_version(get_class($owner), $owner->ID);
        $table = sprintf('%s_Versions', $latest->baseTable());
        // Could be a bug with SilverStripe\Config: Adding VerifiableExtension to both
        // Assets\File and Assets\Image, results in x2 "Title" fields, even though
        // Assets\Image's table has no such field.
        $verifiableFields = array_unique($owner->config()->get('verifiable_fields'));

        // Save the verifiable_fields to the xxx_Versioned table _before_ calling
        // source() which itself, makes use of this data
        DB::query(sprintf(''
            . ' UPDATE "%s"'
            . ' SET "VerifiableFields" = \'%s\''
            . ' WHERE "RecordID" = %d AND "Version" = %d',
            $table,
            json_encode($verifiableFields),
            $latest->ID,
            $latest->Version
        ));

        $this->service->setExtra();
        $verifiable = $this->source();
        $doAnchor = (count($verifiable) && $owner->exists());

        if ($doAnchor && $proofData = $this->service->call('write', $verifiable)) {
            if (is_array($proofData)) {
                $proofData = json_encode($proofData);
            }

            DB::query(sprintf(''
                . ' UPDATE "%s"'
                . ' SET "Proof" = \'%s\','
                . '     "Extra" = \'%s\''
                . ' WHERE "RecordID" = %d AND "Version" = %d',
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
     * call a custom verify() method on all decorated objects if one is defined,
     * providing a flexible API for hashing and verifying pretty much
     * anything. If no such method exists, the default is to take the value
     * of the YML config "verifiable_fields" array, then hash and submit the values
     * of those DB fields. If no verifiable_fields are configured, we return an
     * empty array.
     *
     * @param  DataObject $record
     * @return array
     */
    public function source($record = null) : array
    {
        $record = $record ?: $this->getOwner();
        $verifiable = [];

        if ($this->sourceMode() === self::SOURCE_MODE_FUNC) {
            $verifiable = (array) $record->verify();
        } else {
            // If the "VerifiableFields" DB field is not empty, it contains a cached
            // list of the field-names who's content should be sent for hashing.
            // This means the list of fields to be verified is now _relative_ to
            // the current version, thus any change made to YML config, will only
            // affect versions created _after_ that change.
            $verifiableFields = $record->config()->get('verifiable_fields');

            if ($cachedFields = $record->dbObject('VerifiableFields')->getStoreAsArray()) {
                $verifiableFields = $cachedFields;
            }

            foreach ($verifiableFields as $field) {
                if ($field === 'Proof') {
                    continue;
                }

                $verifiable[] = strip_tags((string) $record->getField($field));
            }
        }

        return $verifiable;
    }

    /**
     * Adds a "Verification" tab to {@link SiteTree} objects in the framework UI.
     *
     * @param  FieldList $fields
     * @return void
     */
    public function updateCMSFields(FieldList $fields)
    {
        parent::updateCMSFields($fields);

        $this->updateAdminForm($fields);
    }

    /**
     * Adds a "Verification" tab to {@link File} objects in the framework UI.
     *
     * @param  FieldList $fields
     * @return void
     */
    public function updateFormFields(FieldList $fields, $controller, $formName, $record)
    {
        $this->updateAdminForm($fields, $record);
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

    /**
     * @param  FieldList  $fields
     * @param  array      $record If passed, the data-model is likely a {@link File}
     *                            subclass, meaning that $this->getOwner() is not
     *                            going to be a {@link DataObject} subclass.
     * @return void
     */
    private function updateAdminForm(FieldList $fields, array $record = null)
    {
        $owner = $record ? $record['Record'] : $this->getOwner();
        $tabRootName = $record ? 'Editor' : 'Root';
        $list = $disabled = [];
        $versions = $owner->Versions()->sort('Version');

        // Build the menu of versioned objects
        foreach ($versions as $item) {
            if ($item->Version == 1) {
                $disabled[] = $item->Version;
            }

            $list[$item->Version] = sprintf('Version: %s (Created: %s)', $item->Version, $item->Created);
        }

        $fields->addFieldsToTab($tabRootName . '.Verify', FieldList::create([
            LiteralField::create('Introduction', '<p class="message intro">Select a version'
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

}
