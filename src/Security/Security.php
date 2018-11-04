<?php

/**
 * @author  Russell Michell 2018 <russ@theruss.com>
 * @package silverstripe-verifiable
 */

namespace PhpTek\Verifiable\Security;

use PhpTek\Verifiable\Exception\VerifiableSecurityException;
use PhpTek\Verifiable\Exception\VerifiableNoVersionException;

/**
 * Security orientated methods and routines.
 */
class Security
{
    /**
     * @const string
     */
    const CHECKSUM_FILE = 'CHECKSUM';

    /**
     * Generate an appropriate checksum for the files that comprise the given
     * directory.
     *
     * @param  string     $dir   The directory who's contents will be checksummed.
     * @param  bool       $write Write the output to the checksum output file.
     * @return string            The hash output from running the CLI checksum
     *                           command(s).
     * @throws VerifiableSecurityException
     */
    public function checksumGenerate(string $dir = '', $write = false) : string
    {
        if (!self::which(['tar', 'sha256sum'])) {
            throw new VerifiableSecurityException('Missing dependency. Cannot calculate checksum.');
        }

        // tar flags appropriate to this module's use-case
        $opts = implode(' ', [
            '-c',
            '--sort=name',
            '--owner=0',
            '--group=0',
            '--mode=755',
            '--numeric-owner',
            '--mtime=2018-01-01 00:00Z',
            '--clamp-mtime',
            '--exclude-vcs',
            '--no-ignore-case',
            '--exclude=' . self::CHECKSUM_FILE
        ]);

        $cmd = sprintf('cd %s && tar %s | sha256sum', $dir, $opts, self::CHECKSUM_FILE);

        if ($write) {
            $cmd = sprintf('cd %s && tar %s | sha256sum > %s', $dir, $opts, self::CHECKSUM_FILE);
        }

        $ret = 0;
        $output = [];

        exec($cmd, $output, $ret);

        return trim(rtrim($output[0], '-'));
    }

    /**
     * Verify that a given local checksum matches a remote checksum.
     *
     * @param  string $dir The dir who's contents should be checksummed.
     * @return array  An array of the local and remote checksums.
     * @throws VerifiableSecurityException
     */
    public function checksumVerify(string $dir = '') : array
    {
        // We're  using file_get_contents() so check we can fetch remote files
        if (ini_get('allow_url_fopen') !== "1") {
            throw new VerifiableSecurityException('System cannot read remote files');
        }

        // What is this package's version who's file-hash we will compute?
        // Get the current tagged release or branch-name
        $versionFile = $dir ?: __DIR__ . '/../../../verifiable/VERSION';
        $checksum = null;

        // Before 0.7.3, this would be a legitimate failure
        if (!$this->fileExists($versionFile) || !$version = file_get_contents($versionFile)) {
            throw new VerifiableNoVersionException('System cannot find local version');
        }

        $checksumFile = sprintf('https://github.com/phptek/silverstripe-verifiable/blob/%s/%s', trim($version), self::CHECKSUM_FILE);

        if (!$this->fileExists($checksumFile) || !$checksum = file_get_contents($checksumFile)) {
            throw new VerifiableSecurityException('System cannot find checksum file');
        }

        // Compute the local checksum and compare it with the remote equivalent
        $computed = $this->checksumGenerate(__DIR__ . '/../../../verifiable');

        if (trim($computed) !== trim($checksum)) {
            throw new VerifiableSecurityException('Invalid checksum');
        }

        return ['local' => $computed, 'remote' => $checksum];
    }

    /**
     * Does the passed $cmd exist on this filesystem?
     *
     * @param  array $cmds One or more commands to test.
     * @return int
     */
    private static function which(array $cmds) : bool
    {
        $ret = 0;

        foreach ($cmds as $cmd) {
            system($cmd, $ret);

            // One of the commands failed
            if ($ret !== 0) {
                return $ret;
            }
        }

        return 0;
    }

    /**
     * Deals with local and remote file-existence checks.
     *
     * @param  string $file
     * @return bool
     */
    private function fileExists(string $file) : bool
    {
        return @fopen($file, 'r') !== false;
    }

}
