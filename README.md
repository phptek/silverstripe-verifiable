# SilverStripe Verifiable

[![Build Status](https://api.travis-ci.org/phptek/silverstripe-verifiable.svg?branch=master)](https://travis-ci.org/phptek/silverstripe-verifiable)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/?branch=master)
[![License](https://poser.pugx.org/phptek/verifiable/license.svg)](https://github.com/phptek/silverstripe-verifiable/blob/master/LICENSE.md)

## What is this?

For decades software users have taken it for granted that their application's data is safe from tampering. That developers, vendors and DBA's are somehow above changing things that will fundamentally affect data integrity. We have trusted that parties will not behave in a manner counter to the security and integrity of application data.

Because no such system can claim _immutibility_ we therefore offer _verifiability_; data who's integrity is mathematically verifiable at some point in time. If something were to change, perhaps when it isn't supposed to change, then an audit-trail of sorts is available that can be consulted.

This is an addon for SilverStripe applications that gives content authors and business owners the ability to verify the integrity of their data at any point in time; Data entered by an author can be verified independently, and for years after the fact.

By default, the module offers a simple CMS UI that allows configurable content of a specific version of any page, to be verified as not having been tampered-with.

Of course, verifiability is not restricted for use in identifying bad behaviour. In the main, the data verifiability community is more concerned with _transparency_, especially in the context of public data.

## How does it work?

With regard to the default functionality; on each page write, a sha256 hash of selected field-data is taken and submitted to a 3rd party backend that implements a [Merkle Tree (Or Binary Hash Tree)](https://en.wikipedia.org/wiki/Merkle_tree). At time of writing, the two service classifications we are aware of that fit the bill are; most public blockchains and standalone or clustered Merkle Tree storage systems like [Trillian](https://github.com/google/trillian/).

Module configuration comprises telling it which of these services should be used to "anchor" data to, such that verification may occur. This can be done in several different ways, and the module provides 2 "backends" to acheive this, described below. Once data has been successfully anchored to the backend, the module produces a valid JSON-LD [Chainpoint Proof](https://chainpoint.org/) which is stored to a pre-defined, [JSON-aware](https://github.com/phptek/silverstripe-verifiable/) field in SilverStripe's database. This can be used to programmatically or manually verify data integrity against the Merkle Storage backend.

To provide an additional level of security, generated hashes can also be digitally signed by users. (Not implemented yet).

## Trillian

Incomplete support.

[Trillian](https://github.com/google/trillian/) is an OSS project from Google that provides a complimentary verification service to some data-store (SilverStripe's database for example) by means of a Merkle Tree. With this backend enabled in the module's YML config, fields on your `DataObject` subclasses can be hashed and stored as leaf nodes in Trillian itself, where the backend is known in Trillan-speak as a ["Personality"](https://github.com/google/trillian/#personalities). 

## Public Blockchain

For a more public and visible means of verifiability, we make use of both the [Bitcoin](https://bitcoin.org/) and [Ethereum](https://ethereum.org) blockchain networks. In addition to storing value-based transactions in their native cryptocurrencies, these blockchains are also capable of storing arbitrary strings, making them ideal for storing Merkle Root hashes from which individual "leaf" hashes of data can be mathematically derived.

We make use of REST calls to the [Tierion](https://tierion.com/) blockchain network, which itself writes hashed data to both Bitcoin and Ethereum on a periodic basis.

## Install

    #> composer require phptek/verifiable

## Configuration

Add the `VerifiableExtension` to each of your data-models that you'd like to be "Verifiable", and for each such model, declare a list of fields who's data should be verifiable:


```YML
My\Name\Space\Model\MyModel:
  extensions:
    - PhpTek\Verifiable\Verify\VerifiableExtension
  verifiable_fields:
    - Title
    - Content
    - Foo
    - Bar

PhpTek\Verifiable\Verify\VerifiableService:
  # One of: "trillian" or "chainpoint"
  backend: chainpoint
```

Be sure to run `flush=all` via your browser or the CLI to refresh SilverStripe's YML config cache.

## Features
    
* Automatically sends hashes of configurable data-model field-data for anchoring after every write
* Arbitrarily elect to re-verify specific content/document version histories via the CMS
* Digitally sign changes, making SilverStripe act as a proxy notary
* Bitcoin and Ethereum integration via the Tierion Network
* Google Trillian integration

## TODO

* What to do about `Versioned::onAfterRollback()` - disallow rolling-back to previous version if it's verifiable (That is: It has populated "Proof" field)?
