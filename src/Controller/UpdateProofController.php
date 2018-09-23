<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Controller;

use SilverStripe\ORM\DataObject;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DB;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Director;
use SilverStripe\Versioned\Versioned;
use PhpTek\Verifiable\ORM\FieldType\ChainpointProof;
use PhpTek\Verifiable\Model\VerifiableExtension;
use PhpTek\Verifiable\Exception\VerifiableValidationException;

/**
 * Controller available to CLI or XHR requests for updating all or selected versionable
 * object versions with full-proofs.
 *
 * @todo Check with Tierion API docs: How many nodes should be submitted to? And why if I submit to only one, does the network not synchronise it?
 * @todo Only fetch versions that have unique proof values
 * @todo Declare a custom Monolog\Formatter\FormatterInterface and refactor log() method
 */
class UpdateProofController extends Controller
{
    /**
     * Entry point.
     *
     * @param  HTTPRequest $request
     * @throws Exception
     */
    public function index(HTTPRequest $request = null)
    {
        if (!$backend = $this->service->name() === 'chainpoint') {
            throw new \Exception(sprintf('Cannot use %s backend with %s!', $backend, __CLASS__));
        }

        $class = $request->getVar('Class') ?? '';
        $recordId = $request->getVar('ID') ?? 0;
        $version = $request->getVar('Version') ?? 0;

        if ($class && $recordId && $version) {
            $this->log('NOTICE', 'Start: Processing single proof...', 2);
            $this->updateVersion($class, $recordId, $version);
        } else {
            $this->log('NOTICE', 'Start: Processing all proofs...', 2);

            // Get all records with partial proofs. Attempt to fetch their full proofs
            // from Tierion, then write them back to local DB
            $this->updateVersions();
        }

        $this->log('NOTICE', 'End.', 2);
    }

    /**
     * Process a single version for a single record. Fetch partial proof, ready to
     * make them whole again by re-writing to the xxx_Versions table's "Proof" field.
     *
     * @param  string $class
     * @param  int    $id
     * @param  int    $version
     * @return void
     * @throws Exception
     */
    protected function updateVersion($class, $id, $version)
    {
        if (!$record = $class::get()->byID($id)) {
            throw new \Exception(sprintf('Cannot find %s record for #%d', $class, $id));
        }

        if (!$record->hasExtension(VerifiableExtension::class)) {
            throw new \Exception(sprintf('%s does not have verifiable extension applied', $class));
        }

        $record = Versioned::get_version($class, $id, $version);

        $this->process($record);
    }

    /**
     * Process all versions for all applicable records. Fetch partial proofs, ready to
     * make them whole again by re-writing to the xxx_Versions table's "Proof" field.
     *
     * @return void
     */
    protected function updateVersions()
    {
        // Get decorated classes
        $dataObjectSublasses = ClassInfo::getValidSubClasses(DataObject::class);

        foreach ($dataObjectSublasses as $class) {
            $obj = Injector::inst()->create($class);

            if (!$obj->hasExtension(VerifiableExtension::class)) {
                continue;
            }

            $this->log('NOTICE', "Processing class: $class");
            $classFlag = false;

            foreach ($class::get() as $item) {
                $versions = Versioned::get_all_versions($class, $item->ID)->sort('Version ASC');

                foreach ($versions as $record) {
                    if (!$proof = $record->dbObject('Proof')) {
                        continue;
                    }

                    if ($proof->isInitial()) {
                        $classFlag = true;
                        $this->log('NOTICE', "\tInitial proof found for ID #{$record->RecordID} and version {$record->Version}");
                        $this->log('NOTICE', "\tRequesting proof via UUID {$proof->getHashIdNode()[0]}");
                        $this->process($record, $proof);
                    }
                }
            }

            if (!$classFlag) {
                $this->log('NOTICE', "Nothing to do.");
            }
        }
    }

    /**
     * Make the call to the backend, return a full-proof if it's available and
     * update the local version(s) with it.
     *
     * @param  DataObject $record
     * @param  string     $proof
     * @return void
     */
    protected function process($record, $proof)
    {
        // I don't understand the Tierion network... if we submit a hash to one IP
        // the rest of the network is not updated. So we need to pre-seed the service
        // with the saved nodes...unless it's only verified proofs that get propagated..??
        $nodes = $record->dbObject('Extra')->getStoreAsArray();
        $uuid = $proof->getHashIdNode()[0];
        $this->service->setExtra($nodes);

        $this->log('NOTICE', sprintf("\tCalling cached node: %s/proofs/%s", $nodes[0], $uuid));

        // Don't attempt to write anything that isn't a full proof
        try {
            $response = $this->service->call('read', $uuid);
        } catch (VerifiableValidationException $e) {
            $this->log('ERROR', $e->getMessage());

            return;
        }

        $proof = ChainpointProof::create()
                ->setValue($response);

        if ($proof && $proof->isFull()) {
            $this->log('NOTICE', "Full proof fetched. Updating record ID #{$record->RecordID} and version {$record->Version}");
            $this->doUpdate($record, $record->Version, $response);
        } else {
            $this->log('WARN', "\t\tNo full proof found for record ID #{$record->RecordID} and version {$record->Version}");
        }
    }

    /**
     * Use the lowest level of the ORM to update the xxx_Versions table directly
     * with a proof.
     *
     * @param type $id
     * @param type $version
     */
    protected function doUpdate($object, $version, $proof)
    {
        $table = sprintf('%s_Versions', $object->config()->get('table_name'));
        $sql = sprintf(
            'UPDATE "%s" SET "Proof" = \'%s\' WHERE "RecordID" = %d AND "Version" = %d',
            $table,
            $proof,
            $object->ID,
            $version
        );

        DB::query($sql);

        $this->log('NOTICE', "Version #$version of record #{$object->ID} updated.");
    }

    /**
     * Simple colourised logging for CLI operation only.
     *
     * @param  string $type
     * @param  string $msg
     * @param  int    $newLine
     * @return void
     */
    protected function log(string $type, string $msg, int $newLines = 1)
    {
        if (!Director::is_cli()) {
            return;
        }

        $lb = Director::is_cli() ? PHP_EOL : '<br/>';
        $colours = [
            'default' => "\033[0m",
            'red' => "\033[31m",
            'green' => "\033[32m",
            'yellow' => "\033[33m",
            'blue' => "\033[34m",
            'magenta' => "\033[35m",
        ];

        switch ($type) {
            case 'ERROR':
                $colour = $colours['red'];
                break;
            case 'WARN':
                $colour = $colours['yellow'];
                break;
            default:
            case 'WARN':
                $colour = $colours['green'];
                break;
        }

        echo sprintf('%s[%s] %s%s%s',
                $colour,
                $type,
                $msg,
                str_repeat($lb, $newLines),
                $colours['default']
            );
    }

}
