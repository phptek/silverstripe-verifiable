<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Controller;

use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Versioned\Versioned;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use PhpTek\Verifiable\ORM\FieldType\ChainpointProof;
use PhpTek\Verifiable\Model\VerifiableExtension;
use Dcentrica\Viz\ChainpointViz;

/**
 * Accepts incoming requests for data verification e.g. from within the CMS
 * or framework's admin area, proxies them through {@link VerifiableService} and
 * sends them on their way.
 *
 * Will proxy validation requests to the currently configured backend for both
 * {@link SiteTree} and {@link DataObject} subclasses.
 */
class VerifiableAdminController extends Controller
{
    /**
     * No local proof was found for this version. If this is the first version,
     * you can safely ignore this message. Otherwise, this is evidence that this
     * version has been tampered-with.
     *
     * @var string
     */
    const STATUS_LOCAL_PROOF_NONE = 'Local Proof Not Found';

    /**
     * One or more key components of the local proof, were found to be invalid.
     * Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_LOCAL_COMPONENT_INVALID = 'Local Components Invalid';

    /**
     * A mismatch exists between the stored hash for this version, and the data the
     * hash was generated from. Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_LOCAL_HASH_INVALID = 'Local Hash Invalid';

    /**
     * All verification checks passed. This version's hash and proof are intact and verified.
     *
     * @var string
     */
    const STATUS_VERIFIED_OK = 'Verified';

    /**
     * Some or all verification checks failed. This version's hash and proof are not intact.
     * Evidence that the record has been tampered-with.
     *
     * @var string
     */
    const STATUS_VERIFIED_FAIL = 'Verification failure';

    /**
     * This version is unverified. If this state persists, something is not working
     * correctly. Please consult your developer.
     *
     * @var string
     */
    const STATUS_UNVERIFIED = 'Unverified';

    /**
     * This version's hash confirmation is currently pending. If it's been more than
     * two hours since submission, try again.
     *
     * @var string
     */
    const STATUS_PENDING = 'Pending';

    /**
     * This version's hash confirmation is currently awaiting processing. If it's
     * been more than two hours since submission, please check the automated update job.
     * Consult your developer..
     *
     * @var string
     */
    const STATUS_INITIAL = 'Initial';

    /**
     * The verification process encountered a network error communicating with the
     * backend. Try again in a moment.
     *
     * @var string
     */
    const STATUS_UPSTREAM_ERROR = 'Upstream Error';

    /**
     * @var array
     */
    private static $allowed_actions = [
        'verifyhash',
    ];

    /**
     * Verify the integrity of arbitrary data by means of a single hash.
     *
     * Responds to URIs of the following prototype: /verifiable/verify/<model>/<ID>/<VID>
     * by echoing a JSON response for consumption by client-side logic.
     *
     * @param  HTTPRequest $request
     * @return void
     */
    public function verifyhash(HTTPRequest $request)
    {
        if (!Permission::checkMember(Security::getCurrentUser(), 'ADMIN')) {
            return $this->httpError(401, 'Unauthorised');
        }

        $class = $request->param('ClassName');
        $id = $request->param('ModelID');
        $version = $request->param('VersionID');
        $verificationData = [];

        if (empty($id) || !is_numeric($id) ||
                empty($version) || !is_numeric($version) ||
                empty($class)) {
            return $this->httpError(400, 'Bad request');
        }

        if (!$record = Versioned::get_version($class, $id, $version)) {
            return $this->httpError(400, 'Bad request');
        }

        try {
            $status = $this->getStatus($record, $record->getExtraByIndex(), $verificationData);
        } catch (ValidationException $ex) {
            $status = self::STATUS_UPSTREAM_ERROR;
        }

        $response = json_encode([
            'Record' => [
                'RecordID' => "$record->RecordID",
                'CreatedDate' => self::display_date($record->Created),
                'Version' => "$record->Version",
                'Class' => get_class($record),
                'VerifiableFields' => $record->sourceMode() === VerifiableExtension::SOURCE_MODE_FIELD ?
                    json_decode($record->VerifiableFields) :
                    [],
            ],
            'Status' => [
                'Nice' => $status,
                'Code' => $this->getCodeMeta($status, 'code'),
                'Def' => $this->getCodeMeta($status, 'defn'),
            ],
            'Proof' => [
                'SubmittedDate' => self::display_date($verificationData['SubmittedAt'] ?? ''),
                'SubmittedTo' => $record->dbObject('Extra')->getStoreAsArray(),
                'ChainpointProof' => $verificationData['ChainpointProof'] ?? '',
                'ChainpointViz' => $verificationData['ChainpointViz'] ?? '',
                'MerkleRoot' => $verificationData['MerkleRoot'] ?? '',
                'BlockHeight' => $verificationData['BlockHeight'] ?? '',
                'Hashes' => $verificationData['Hashes'] ?? '',
                'UUID' => $verificationData['UUID'] ?? '',
                'GV' => $verificationData['Dotfile'] ?? '',
                'TXID' => $verificationData['TXID'] ?? '',
                'OPRET' => $verificationData['OPRET'] ?? '',
            ]
        ], JSON_UNESCAPED_SLASHES);

        $this->renderJSON($response);
    }

    /**
     * Return data used for verifiable statuses.
     *
     * @param  string $status
     * @param  string $key
     * @return mixed
     */
    private function getCodeMeta($status, $key)
    {
        $refl = new \ReflectionClass(__CLASS__);
        $const = array_search($status, $refl->getConstants());
        $keyJson = file_get_contents(realpath(__DIR__) . '/../../statuses.json');
        $keyMap = json_decode($keyJson, true);
        $defn = '';

        foreach ($keyMap as $map) {
            if (isset($map[$const])) {
                $defn = $map[$const];
            }
        }

        $data = [
            'code' => $const,
            'defn' => $defn,
        ];

        return $data[$key] ?? $data;
    }

    /**
     * Gives us the current verification status of the given record. Takes into
     * account the state of the saved proof as well as by making a backend
     * verification call.
     *
     * @param  DataObject $record           The versioned record we're checking.
     * @param  array      $nodes            Array of cached chainpoint node IPs.
     * @param  array      $verificationData Array of data for manual verification.
     * @return string
     */
    public function getStatus($record, $nodes, &$verificationData) : string
    {
        // Set some extra data on the service. In this case, the actual chainpoint
        // node addresses, used to submit hashes for the given $record
        $this->service->setExtra($nodes);
        $proof = $record->dbObject('Proof');

        // Basic existence of proof
        if (!$proof->getValue()) {
            return self::STATUS_LOCAL_PROOF_NONE;
        }

        if ($proof->isInitial()) {
            return self::STATUS_INITIAL;
        }

        if ($proof->isPending()) {
            return self::STATUS_PENDING;
        }

        // So the saved proof claims to be full. Perform some rudimentary checks
        // before we send a full-blown verification request to the backend
        if ($proof->isFull()) {
            // Tests 3 of the key components of the local proof. Sending a verification
            // request will do this and much more for us, but rudimentary local checks
            // can prevent a network request
            if (!$proof->getHashIdNode() || !$proof->getProof() || !count($proof->getAnchorsComplete())) {
                return self::STATUS_LOCAL_COMPONENT_INVALID;
            }

            // We've got this far. The local proof seems to be good. Let's verify
            // it against the backend
            $response = $this->service->call('verify', $proof->getProof());
            $responseModel = ChainpointProof::create()->setValue($response);
            $isVerified = $responseModel->isVerified();

            if (!$isVerified) {
                return self::STATUS_VERIFIED_FAIL;
            }

            // OK, so we have an intact local full proof, let's ensure it still
            // matches a hash of the data it purports to represent
            $remoteHash = $responseModel->getHash();
            $reCalculated = $this->service->hash($record->source());

            if ($reCalculated !== $remoteHash) {
                return self::STATUS_LOCAL_HASH_INVALID;
            }

            $chainpointViz = new ChainpointViz($proof->getProofJson(), 'btc');

            // Setup data for display & manual re-verification
            $v3proof = ChainpointProof::create()->setValue($proof->getProofJson());
            $verificationData['ChainpointProof'] = $proof->getProofJson();
            $verificationData['MerkleRoot'] = $v3proof->getMerkleRoot('btc');
            $verificationData['BlockHeight'] = $v3proof->getBitcoinBlockHeight();
            $verificationData['UUID'] = $v3proof->getHashIdNode();
            $verificationData['TXID'] = $chainpointViz->getBtcTXID();
            $verificationData['OPRET'] = $chainpointViz->getBtcOpReturn();
            $verificationData['SubmittedAt'] = $v3proof->getSubmittedAt();
            $verificationData['Hashes'] = [
                'local' => $reCalculated,
                'remote' => $v3proof->getHash(),
            ];
            $verificationData['ChainpointViz'] = $this->getProofViz($chainpointViz);

            // All is well. As you were...
            return self::STATUS_VERIFIED_OK;
        }

        // Default status
        return self::STATUS_UNVERIFIED;
    }

    /**
     * Properly return JSON, allowing consumers to render returned JSON correctly.
     *
     * @param  string $json
     * @return void
     */
    private function renderJSON(string $json)
    {
        header('Content-Type: application/json');
        echo $json;
        exit(0);
    }

    /**
     * Common date format in ISO 8601.
     *
     * @return string
     */
    private static function display_date(string $date) : string
    {
        return date('Y-m-d H:i:s', strtotime($date));
    }

    /**
     * Generate a visualisation of a chainpoint proof.
     *
     * @param  ChainpointViz $viz         A passed ChainpointViz object to work with.
     * @param  string $format             Any format accepted by Graphviz.
     * @return string                     A URI path to the location of the generated graphic.
     */
    private function getProofViz(ChainpointViz $viz, $format = 'svg')
    {
        $fileName = sprintf('chainpoint.%s', $format);
        $filePath = sprintf('%s/%s', ASSETS_PATH, $fileName);
        $fileHref = sprintf('/%s/%s', ASSETS_DIR, $fileName);

        $viz->setFilename($filePath);
        $viz->visualize();

        if (!file_exists($filePath)) {
            return '';
        }

        return $fileHref;
    }

}
