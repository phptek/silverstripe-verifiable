<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Job;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * Simple job to periodically verify a hash against a backend and
 * on success receive and save a returned chainpoint proof. Once a proof is saved,
 * this job is considered to be complete.
 *
 * @todo Using the Tierion REST API, we can submit "Blockscriptions" where a callback
 * URL is called by the network itself when a ChainPoint proof is ready. Is there
 * anything similar in the Chainpoint API?
 */
class BackendVerificationJob extends AbstractQueuedJob
{
    /**
     * @return string
     */
    public function getSignature() : string
    {
        return $this->getHash();
    }

    /**
     * @return string
     */
    public function getTitle() : string
    {
        return 'Chainpoint Proof Fetch Job';
    }

    /**
     * @return string
     */
    public function getJobType()
    {
        return QueuedJob::IMMEDIATE;
    }

    /**
     * @return string
     */
    public function getHash() : string
    {
        return $this->verifiableService->hash($model->normaliseData());
    }

    /**
     * Do the work to ping the remote backend.
     *
     * @return void
     */
    public function process()
    {
        $body = $this->verifiableService->read($this->getHash());

        if ($this->verifiableService->isVerifiedFull($body)) {
            $this->getObject()->setField('Proof', $body)->write();
            $this->isComplete = true;
        }
    }

}
