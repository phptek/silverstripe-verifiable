# SilverStripe Verifiable

[![Build Status](https://api.travis-ci.org/phptek/silverstripe-verifiable.svg?branch=master)](https://travis-ci.org/phptek/silverstripe-verifiable)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/?branch=master)
[![License](https://poser.pugx.org/phptek/verifiable/license.svg)](https://github.com/phptek/silverstripe-verifiable/blob/master/LICENSE.md)

## What is this?

For decades software users have taken it for granted that their application's data is safe from tampering. That developers, vendors and DBA's are somehow above changing things that will fundamentally affect data integrity. We have trusted that parties will not behave in a manner counter to the security and integrity of application data.

Because no such system can claim _immutibility_ we therefore offer _verifiability_; data who's integrity is mathematically verifiable at some point in time. If something were to change, perhaps when it isn't supposed to change, then an audit-trail of sorts is available that can be consulted.

This is an addon for SilverStripe applications that gives content authors and business owners the ability to verify the integrity of their data at any point in time; Data entered by an author can be verified independently, and for years after the fact.

By default, the module offers a simple CMS UI that allows configurable content of a specific version of any page, to be verified as not having been tampered-with.

Of course; the identification of bad behaviour and negative outcomes is not the only application of verifiability. Verifiability is actually more concerned with _transparency_,
especially in the context of public data.

## How does it work?

With the most basic configuation (see below); on each page write, a sha256 hash of selected field-data is taken and submitted to a 3rd party backend that implements a [Merkle or Binary Hash Tree)](https://en.wikipedia.org/wiki/Merkle_tree).
At time of writing, the two service classifications we are aware of that fit the bill are; public blockchains (notably the Bitcoin and Ethereum networks) and standalone or clustered Merkle Tree storage systems like [Trillian](https://github.com/google/trillian/).

## Blockchain

In addition to processing and persisting value-based transactions in their native cryptocurrencies, the Bitcoin and Ethereum blockchains are also capable of storing (limited size) arbitrary data, making them ideal for storing Merkle Root hashes from which individual "leaf" hashes can be mathematically derived. We make use of REST calls to the [Tierion](https://tierion.com/) blockchain network using its [Chainpoint](https://chainpoint.org) service. Tierion is itself a permissionless blockchain network which itself periodically writes hashed data to both Bitcoin and Ethereum. 

## Install

    #> composer require phptek/verifiable

## Configuration

Configure the desired backend:

```YML
PhpTek\Verifiable\Verify\VerifiableService:
  # One of: "trillian" or "chainpoint"
  backend: chainpoint
```

Add the `VerifiableExtension` to each data-model that you'd like to be "Verifiable":

```YML
My\Name\Space\Model\MyModel:
  extensions:
    - PhpTek\Verifiable\Verify\VerifiableExtension
```

By default, any fields on your decorated model(s) that you define in the `verifiable_fields` array, will be hashed and submitted to the backend thus:

```YML
My\Name\Space\Model\MyModel:
  verifiable_fields:
    - Title
    - Content
```

Be sure to run `flush=all` via your browser or the CLI to refresh SilverStripe's YML config cache.

You'll need to install a simple cron job on your hosting environment, to invoke the `FullProofFetchTask` which does the job of periodically querying the backend for a full-proof (Chainpoint backend only).

    /path/to/docroot/vendor/silverstripe/framework/cli-script.php dev/cron quiet=1

### Advanced

The true power of this module comes with giving developers the ability to supply their own data. Simply declare a `verify()` method on any decorated `DataObject` subclass, and its return value is what becomes hashed and submitted to the backend.

## Background Reading

* [Trustworthy technology: The future of digital archives](https://blog.nationalarchives.gov.uk/blog/trustworthy-technology-future-digital-archives/)
* [Xero Integrates With Tierion To Secure Accounting Data Using Chainpoint](https://blog.tierion.com/2018/04/19/xero-integrates-with-tierion-to-secure-accounting-data-using-chainpoint/)
