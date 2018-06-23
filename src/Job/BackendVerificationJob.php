<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Job;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * Simple job to periodically fetch a full chainpoint proof from the backend.
 * On success receive and save the returned, full chainpoint proof. Once a proof
 * is saved, this job is considered to be complete.
 *
 * @todo Using the Tierion REST API, we can submit "Blockscriptions" where a callback
 * URL is called by the network itself when a ChainPoint proof is ready. Is there
 * anything similar in the Chainpoint API?
 * @todo Ensure this job does not create a copy of itself, if the proof-save
 * is successful.
 */
class BackendVerificationJob extends AbstractQueuedJob
{
    /**
     * @return string
     */
    public function getTitle() : string
    {
        return 'Chainpoint Full Proof Fetch Job';
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::IMMEDIATE;
    }

    /**
     * Do the work to ping the remote backend.
     *
     * @return void
     * @todo Prevent a new job being created
     */
    public function process()
    {
        $proof = $this->getObject()->dbObject('Proof');
        $savedHash = $proof->getHashNodeId();
        $body = $this->verifiableService->read($savedHash);

        if ($proof->isComplete($body)) {
            $this->getObject()->setField('Proof', $body)->write();
            $this->isComplete = true;
        }
    }

}
