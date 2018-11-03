<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Viz;

use SilverStripe\Core\Injector\Factory;
use SilverStripe\Core\Injector\Injector;
use Dcentrica\Viz\ChainpointViz;

/**
 * Generic factory for instantiating hash-visualisation classes.
 */
class HashVizFactory implements Factory
{
    /**
     * @param  string $service
     * @param  array $params
     * @return ChainpointViz
     */
    public function create($service, array $params = []) : ChainpointViz
    {
        return Injector::inst()->create(ChainpointViz::class);
    }

}
