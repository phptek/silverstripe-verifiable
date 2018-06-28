<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Cron;

use SilverStripe\CronTask\Interfaces\CronTask;
use PhpTek\Verifiable\ORM\FieldType\ChainpointProof;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Extensible;
use PhpTek\Verifiable\Verify\VerifiableExtension;
use SilverStripe\ORM\DataList;

/**
 * Assumes of course that the CronTask cron is running on the server. See README.
 */
class ChainpointFullProofTask implements CronTask
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
    public function process()
    {
        if (!$backend = $this->verifiableService->getBackend() === 'chainpoint') {
            throw new Exception(sprintf('Cannot use %s backend with %s!', $backend, __CLASS__));
        }

        $fullProofs = $this->verifiableService->read($this->getPartials());
        $this->writeFull($fullProofs);
    }

    /**
     * Fetch all partial proofs, ready to make them whole again. The returned
     *
     * @return array
     */
    protected function getPartials()
    {
        // Get decorated classes
        $verifiableClasses = array_filter(self::get_extensions(DataObject::class), function($v) {
            return $v === VerifiableExtension::class;
        });

        // Now fetch the relevant records, filtering on incomplete proofs
        DataList::create($args);
    }

    /**
     * Takes a large JSON string, converts it to a ChainpointProof object and
     * updates the necessary records.
     *
     * @param  string $fullProofs
     * @return void
     */
    protected function writeFull($proofs)
    {
        $jsonObject = ChainpointProof::create()->setValue($proofs);
    }

}
