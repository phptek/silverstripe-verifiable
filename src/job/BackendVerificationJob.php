<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Job;

use Symbiote\QueuedJobs\Services\AbstractQueuedJob;

/**
 * Simple job to verify a hash against a backend and on success, receive and
 * save a chainpoint proof.
 */
class BackendVerificationJob extends AbstractQueuedJob
{
    /**
     * @return string
     */
    public function getSignature() : string
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
        $body = $this->verifiableService->read($this->getSignature());

        // TODO
        // What does a valid/invalid response look like?
        if ($isValid) {
            $this->getObject()->setField('Proof', $body)->write();
        }
    }

}
