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
use PhpTek\Verifiable\Verify\VerifiableExtension;

/**
 * Controller available to CLI or AJAX for updating all or selected versions.
 *
 * @todo Check with Tierion API docs: How many nodes should be submitted to? And why if I submit to only one, does the network not synchronise it?
 * @todo Update logic to write hashes to all 3 nodes, not just the first as-is the case in the client() method.
 */
class UpdateProofController extends Controller
{
    /**
     * @param  HTTPRequest $request
     * @throws Exception
     */
    public function index(HTTPRequest $request = null)
    {
        if (!$backend = $this->verifiableService->getBackend()->name() === 'chainpoint') {
            throw new \Exception(sprintf('Cannot use %s backend with %s!', $backend, __CLASS__));
        }

        $this->log('NOTICE', 'Start: Processing proofs...', 2);

        // Get all records with partial proofs. Attempt to fetch their full proofs
        // from Tierion, then write them back to local DB
        $this->updateVersions();

        $this->log('NOTICE', 'Finish: Processing proofs.');
    }

    /**
     * Fetch all partial proofs, ready to make them whole again by re-writing to
     * each xxx_Versions table's "Proof" field.
     *
     * @return array
     */
    protected function updateVersions()
    {
        // Get decorated classes
        $dataObjectSublasses = ClassInfo::getValidSubClasses(DataObject::class);
        $candidates = [];

        foreach ($dataObjectSublasses as $class) {
            $obj = Injector::inst()->create($class);

            if (!$obj->hasExtension(VerifiableExtension::class)) {
                continue;
            }

            $this->log('NOTICE', "Processing class: $class");

            foreach ($class::get() as $item) {
                // TODO Get all versions that have non-duplicated proof-hashes
                $versions = Versioned::get_all_versions($class, $item->ID)->sort('Version ASC');

                foreach ($versions as $record) {
                    $proof = $record->dbObject('Proof');

                    if ($proof->isInitial()) {
                        $this->log('NOTICE', "\tInitial proof found for ID #{$record->RecordID} and version {$record->Version}");
                        $this->log('NOTICE', "\tRequesting proof via UUID {$proof->getHashIdNode()[0]}");

                        // I don't understand the Tierion network... if we submit a hash to one IP
                        // the rest of the network is not updated. So we need to pre-seed the service
                        // with the saved nodes...unless it's only verified proofs that get propagated..??
                        $nodes = $record->dbObject('Extra')->getStoreAsArray();
                        $uuid = $proof->getHashIdNode()[0];
                        $this->verifiableService->setExtra($nodes);

                        $this->log('NOTICE', sprintf('Calling %s/proofs/%s', $nodes[0], $uuid));

                        // Don't attempt to write anything that isn't a full proof
                        $response = $this->verifiableService->call('read', $uuid);
                        $isFull = ChainpointProof::create()
                                ->setValue($response)
                                ->isFull();

                        if ($isFull) {
                            $this->log('NOTICE', "Full proof fetched. Updating record with ID #{$record->RecordID} and version $version");
                            $this->updateQuery($record, $version, $response);
                        } else {
                            $this->log('WARN', "No full proof found for record with ID #{$record->RecordID} and version $version");
                        }
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Use the lowest level of the ORM to update the xxx_Versions table directly
     * with a proof.
     *
     * @param type $id
     * @param type $version
     */
    protected function updateQuery($object, $version, $proof)
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
     * @param  string $type
     * @param  string $msg
     * @param  int    $newLine
     * @return void
     * @todo   Declare a custom Monolog\Formatter\FormatterInterface
     */
    private function log(string $type, string $msg, int $newLines = 1)
    {
        $lb = Director::is_cli() ? PHP_EOL : '<br/>';

        echo sprintf('[%s] %s%s', $type, $msg, str_repeat($lb, $newLines));
    }

}
