<?php

/**
 * @author  Russell MIchell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

use PhpTek\Verifiable\Backend\BackendProvider;
use GuzzleHttp\Client;
use Guzzle\Http\Exception\RequestException;
use Guzzle\Http\Message\Request;
use SilverStripe\Core\Config\Configurable;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use SilverStripe\Core\Injector\Injector;

/**
 * See: https://chainpoint.org
 */
class Chainpoint implements BackendProvider
{
    use Configurable;

    /**
     * Configuration of this backend's supported blockchain networks and
     * connection details for each one's locally-installed full-node.
     *
     * Tieron supports Bitcoin and Ethereum, but there's nothing to stop custom
     * routines and config appropriating an additional blockxhain network to which
     * proofs can be saved e.g. a local Hyperledger Fabric network.
     *
     * @var array
     * @config
     */
    private static $blockchain_config = [
        [
            'name' => 'Bitcoin',
            'implementation' => 'bitcoind',
            'host' => '',
            'port' => 0,
        ],
        [
            'name' => 'Ethereum',
            'implementation' => 'geth',
            'host' => '',
            'port' => 0,
        ],
    ];

    /**
     * @return string
     */
    public function name() : string
    {
        return 'chainpoint';
    }

    /**
     * @param  string $hash
     * @return string
     * @throws VerifiableBackendException
     */
    public function getProof(string $hash) : string
    {
        $response = $this->client('/proofs', 'GET', $hash);

        if ($response->getStatusCode() !== 200) {
            throw new VerifiableBackendException('Unable to fetch proof from backend.');
        }

        return $response->getBody();
    }

    /**
     * @param  string $hash
     * @return string
     */
    public function writeHash(string $hash) : string
    {
        $response = $this->client('/hashes', 'POST', ['hashes' => $hash]);

        return $response->getBody();
    }

    /**
     * Submit a chainpoint proof to the backend for verification.
     *
     * @param  string $proof A valid JSON-LD Chainpoint Proof.
     * @return bool
     */
    public function verifyProof(string $proof) : bool
    {
        // Consult blockchains directly, if so configured and suitable
        // blockchain full-nodes are available to our RPC connections
        if ((bool) $this->config()->get('direct_verification') === true) {
            return $this->backend->verifyDirect($proof);
        }

        $response = $this->client('/verify', 'POST', json_encode(['proofs' => [$hash]]));

        return $response->getStatusCode() === 200 &&
                json_decode($response->getBody(), true)['status'] === 'verified';
    }

    /**
     * Return a client to use for all RPC traffic to this backend.
     *
     * @param  string                $url
     * @param  string                $verb
     * @param  array                 $payload
     * @return mixed null | Response Guzzle Response object
     * @todo Client()->setSslVerification() if required
     */
    protected function client(string $url, string $verb, array $payload = [])
    {
        $verb = strtoupper($verb);
        $client = new Client([
            'base_uri' => $this->fetchNodeUrl(),
            'timeout'  => $this->config()->get('chainpoint', 'params')['timeout'],
        ]);
        $request = new Request($verb, $url, $payload);

        try {
            return $client->send($request);
        } catch (RequestException $e) {
            return null;
        }
    }

    /**
     * The Tieron network comprises many nodes, some of which may or may not be
     * online. Pings a randomly selected resource URL, containing IPs of each
     * advertised node and calls each until one returns an HTTP 200.
     *
     * @return string
     */
    protected function fetchNodeUrl()
    {
        $chainpointUrls = [
            'https://a.chainpoint.org/nodes/random',
            'https://b.chainpoint.org/nodes/random',
            'https://c.chainpoint.org/nodes/random',
        ];

        $url = $chainpointUrls[rand(count($randUrls))];
        $response = $this->client($url, 'GET');

        foreach (json_decode($response->getBody(), true) as $candidate) {
            $response = $this->client($candidate['public_uri']);

            if ($response->getStatusCode() === 200) {
                return $candidate['public_uri'];
            }
        }
    }

    /**
     * For each of this backend's supported blockchain networks, skips any intermediate
     * verification steps through the Tieron network, preferring instead to calculate
     * proofs ourselves in consultation directly with the relevant networks.
     *
     * @param  string $proof    The stored JSON-LD chainpoint proof
     * @param  array  $networks An array of available blockchains to consult
     * @return bool             Returns true if each blockchain found in $network
     *                          can verify our proof.
     * @todo   Implement via dedicated classes for each configured blockchain network.
     */
    protected function verifyProofDirect(string $proof, array $networks = [])
    {
        $result = [];

        foreach ($this->config()->get('blockchain_config') as $config) {
            if (in_array($config['name'], $networks)) {
                $implementation = ucfirst(strtolower($config['name']));
                $node = Injector::inst()->createWithArgs($implementation, [$config]);

                $result[strtolower($config['name'])] = $node->verifyProof($proof);
            }
        }

        return !in_array(false, $result);
    }

}
