<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Backend;

use PhpTek\Verifiable\Backend\BackendProvider;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use SilverStripe\Core\Config\Configurable;
use PhpTek\Verifiable\Exception\VerifiableBackendException;
use PhpTek\Verifiable\Exception\VerifiableValidationException;
use SilverStripe\Core\Injector\Injector;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

/**
 * Calls the endpoints of the chainpoint.org (Tierion?) network. Based on the Swagger
 * docs found here: https://app.swaggerhub.com/apis/chainpoint/node/1.0.0.
 *
 * @see https://chainpoint.org
 * @see https://app.swaggerhub.com/apis/chainpoint/node/1.0.0
 */
class Chainpoint implements BackendProvider
{
    use Configurable;

    /**
     * Configuration of this backend's supported blockchain networks and
     * connection details for each one's locally-installed full-node.
     *
     * Tieron supports Bitcoin and Ethereum, but there's nothing to stop custom
     * routines and config appropriating an additional blockchain network to which
     * proofs can be saved e.g. a "local" Hyperledger Fabric network.
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
     * @todo Rename to proofs() as per the "gateway" we're calling
     */
    public function getProof(string $hash) : string
    {
        $response = $this->client("/proofs/$hash", 'GET');

        if ($response->getStatusCode() !== 200) {
            throw new VerifiableBackendException('Unable to fetch proof from backend.');
        }

        return $response->getBody();
    }

    /**
     * Send an array of hashes for anchoring. Initial responses look like the following:
     *
     *  {"meta":{"submitted_at":"2018-06-09T00:27:03Z","processing_hints":{"cal":"2018-06-09T00:27:18Z","btc":"2018-06-09T01:28:03Z"}},"hashes":[{"hash_id_node":"d5558060-6b7b-11e8-a2e4-01345eeea48d","hash":"31bddc977afa06935e3568331264f4efcf720a62"}]}
     *
     * @param  array $hashes
     * @return string
     * @todo Rename to hashes() as per the "gateway" we're calling
     */
    public function writeHash(array $hashes) : string
    {
        $response = $this->client('/hashes', 'POST', ['hashes' => $hashes]);

        return $response->getBody();
    }

    /**
     * Submit a chainpoint proof to the backend for verification.
     *
     * @param  string $proof A valid JSON-LD Chainpoint Proof.
     * @return bool
     * @todo Rename to verify() as per the "gateway" we're calling
     */
    public function verifyProof(string $proof) : bool
    {
        // Consult blockchains directly, if so configured and suitable
        // blockchain full-nodes are available to our RPC connections
        if ((bool) $this->config()->get('direct_verification')) {
            return $this->backend->verifyDirect($proof);
        }

        $response = $this->client('/verify', 'POST', ['proofs' => [$proof]]);

        return json_decode($response->getBody(), true)['status'] === 'verified';
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
     * @see    https://runkit.com/tierion/verify-a-chainpoint-proof-directly-using-bitcoin
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

    /**
     * Return a client to use for all RPC traffic to this backend.
     *
     * @param  string   $url
     * @param  string   $verb
     * @param  array    $payload
     * @param  bool     $simple  Pass "base_uri" to {@link Client}.
     * @return Response Guzzle Response object
     * @throws VerifiableBackendException
     * @todo Client()->setSslVerification() if required
     * @todo Use promises to send concurrent requests: 1). Find a node 2). Pass node URL to second request
     * @todo Use 'body' in POST requests?
     * @todo Port to dedicated ChainpointClient class
     */
    protected function client(string $url, string $verb, array $payload = [], bool $simple = true)
    {
        $verb = strtoupper($verb);
        $method = strtolower($verb);
        $config = $this->config()->get('client_config');
        $client = new Client([
            'base_uri' => $simple ? $this->fetchNodeUrl() : '',
            'verify' => true,
            'timeout'  => $config['timeout'],
            'connect_timeout'  => $config['connect_timeout'],
            'allow_redirects' => false,
            'user-agent' => sprintf(
                '%s %s',
                \GuzzleHttp\default_user_agent(),
                $this->getInfoFromComposer('silverstripe/framework'),
                'phptek/verifiable'
            ),
        ]);

        try {
            // json_encodes POSTed data and sends correct Content-Type header
            if ($payload && $verb === 'POST') {
                $payload['json'] = $payload;
            }

            return $client->$method($url, $payload);
        } catch (RequestException $e) {
            throw new VerifiableValidationException($e->getMessage());
        }
    }

    /**
     * Return the version of $pkg taken from composer.lock.
     *
     * @param  string $pkg e.g. "silverstripe/framework"
     * @return string
     * @todo Port to dedicated ChainpointClient class
     */
    protected function getInfoFromComposer($pkg)
    {
        $lockFileJSON = BASE_PATH . '/composer.lock';

        if (!file_exists($lockFileJSON) || !is_readable($lockFileJSON)) {
            return self::SLW_NOOP;
        }

        $lockFileData = json_decode(file_get_contents($lockFileJSON), true);

        foreach ($lockFileData['packages'] as $package) {
            if ($package['name'] === $pkg) {
                return $package['version'];
            }
        }

        return self::SLW_NOOP;
    }

    /**
     * The Tierion network comprises many nodes, some of which may or may not be
     * online. Pings a randomly selected resource URL, who's response should contain
     * IPs of each advertised node, then calls each until one responds with an
     * HTTP 200.
     *
     * @return string
     * @throws VerifiableBackendException
     */
    protected function fetchNodeUrl()
    {
        $chainpointUrls = $this->config()->get('chainpoint_urls');
        $url = $chainpointUrls[rand(0,2)];
        $response = $this->client($url, 'GET', [], false);

        // TODO Set the URL as a class-property and re-use that, rather than re-calling fetchNodeUrl()
        // TODO Make this method re-entrant and try a different URL
        if ($response->getStatusCode() !== 200) {
            throw new VerifiableBackendException('Bad response from node source URL');
        }

        foreach (json_decode($response->getBody(), true) as $candidate) {
            $response = $this->client($candidate['public_uri'], 'GET', [], false);

            // If for some reason we don't get a response: re-entrant method
            if ($response->getStatusCode() === 200) {
                return $candidate['public_uri'];
            }
        }
    }

}
