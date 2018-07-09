<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Cron;

use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Extensible;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Versioned\Versioned;
use PhpTek\Verifiable\Verify\VerifiableExtension;

/**
 * Assumes of course that the CronTask cron is running on the server. See README.
 * @todo Switch over to implementing CronTask...
 * @todo Check with Tierion API docs: How many nodes should be submitted to? And why if I submit to only one, does the network not synchronise it?
 * @todo Update logic to write hashes to all 3 nodes, not just the first as-is the case in the client() method.
 */
class ChainpointFullProofTask extends BuildTask
{
    use Extensible;

    /**
     * {@inheritdoc}
     */
    public function getSchedule()
    {
        return '0 1 * * *'; // 01:00 every day
    }

    /**
     * {@inheritdoc}
     * @throws Exception
     */
    public function run($request = null)
    {
        if (!$backend = $this->verifiableService->getBackend()->name() === 'chainpoint') {
            throw new \Exception(sprintf('Cannot use %s backend with %s!', $backend, __CLASS__));
        }

        // Get all records with partial proofs. Attempt to fetch their full proofs
        // from Tierion, then write them back to local DB
        $partials = $this->getPartials();
        $this->updateProofs($partials);
    }

    /**
     * Fetch all partial proofs, ready to make them whole again.
     *
     * @return array
     * @todo Create a new version when updating the proof / update latest version
     */
    protected function getPartials()
    {
        // Get decorated classes
        $dataObjectSublasses = ClassInfo::getValidSubClasses(DataObject::class);
        $candidates = [];

        foreach ($dataObjectSublasses as $class) {
            $obj = Injector::inst()->create($class);

            if ($class === SiteTree::class || !$obj->hasExtension(VerifiableExtension::class)) {
                continue;
            }

            foreach (DataObject::get($class) as $item) {
                // TODO Get all versions that have non-duplicated proof-hashes
                $versions = Versioned::get_all_versions($class, $item->ID)->sort('Version ASC');

                foreach ($versions as $version) {
                    $proof = $version->dbObject('Proof');

                    if ($proof->isInitial()) {
                        $candidates[$class][$version->RecordID][$version->Version] = $proof->getHashIdNode()[0];
                    }
                }
            }
        }

        return $candidates;
    }

    /**
     * Takes a large JSON string, converts it to a ChainpointProof object and
     * updates the necessary records.
     *
     * @param  array $partials
     * @return void
     */
    protected function updateProofs(array $partials)
    {
        foreach ($partials as $class => $data) {
            foreach ($data as $recordId => $versions) {
                // TODO Avoid this third loop, and define a Chainpoint::getProofs() method
                foreach ($versions as $version) {
                    // Don't attempt to write anything that isn't a full proof
                    $response = $this->verifiableService->call('read', $version);
                    $isVerified = ChainpointProof::create()
                            ->setValue($response)
                            ->isVerified();

                    if ($isVerified) {
                        DataObject::get()->filter([
                            'ID' => $recordId,
                            'Version' => $version,
                        ])
                                ->setValue('Proof', $response)
                                ->write();
                    }
                }
            }
        }
    }

}
