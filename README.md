# SilverStripe Verifiable

[![Build Status](https://api.travis-ci.org/phptek/silverstripe-verifiable.svg?branch=master)](https://travis-ci.org/phptek/silverstripe-verifiable)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/?branch=master)
[![License](https://poser.pugx.org/phptek/verifiable/license.svg)](https://github.com/phptek/silverstripe-verifiable/blob/master/LICENSE.md)

## What is this?

...A WORK IN PROGRESS...

A module for SilverStripe applications that provides content authors and business owners with the ability to verify integrity of their data over time; Data entered by an author can be verified independently, and perhaps for many years after the fact.

Software users have for decades taken it for granted that their application's data is safe from tampering. That developers, vendors, DBA's and even power-users, are somehow above tampering with things that affect data integrity. Application users simply trust that these parties will not behave in a manner counter to the security and integrity of an application's data.

But what do we mean by "integrity"? Well we don't mean "tamper proof" becuase there can never be any such guarantee. However we can do "Verified Data"; data who's integrity is mathatically proveable. If something changes then an audit-trail of sorts is available that can be consulted.

## How does it work?

This module will connect to a backend service that implements Merkle Storage (Or binary tree storage). At time of writing, the two service classes we are aware of that fit the bill are most public blockchains and standalone Merkle Tree storage systems like [Trillian](https://github.com/google/trillian/).

As part of the module's configuration, it will need to know which service should be used to "anchor" metadata to such that data verification can occur. This can be done in several different ways, and the module provides 2 mechanisms or "backends" to acheive this. Once data has been successfully anchored to the Merkle Tree store, the module will produce a valid JSON-LD [Chainpoint Proof](https://chainpoint.org/) which is stored to a pre-defined field. This can be used to programmatically or manually verify some data's integrity against the Merkle Storage service.

To provide an additional level of security, generated hashes can also be digitally signed by users.

## Trillian

[Trillian](https://github.com/google/trillian/) is an OSS project from Google that provides a complimentary verification service to some data-store (SilverStripe's database for example) by means of a Merkle Tree. With this backend enabled in the module's YML config, fields on your `DataObject` subclasses can be hashed and stored as leaf nodes in Trillian itself, where the backend is known in Trillan-speak as a ["Personality"](https://github.com/google/trillian/#personalities). 

## Public Blockchain

For a more public and visible means of verifiability, we make use of both the [Bitcoin](https://bitcoin.org/) and [Ethereum](https://ethereum.org) blockchain networks. In addition to storing value-based transactions in their native cryptocurrencies, these blockchains are also capable of storing arbitrary strings, making them ideal for storing Merkle Root hashes from which individual "leaf" hashes of data can be mathematically derived.

We make use of REST calls to the [Tieron](https://tieron.com/) blockchain network, which itself writes hashed data to both Bitcoin and Ethereum on a periodic (hourly) basis.

## Install

    #> composer install phptek/verifiable

## Configuration

Add the `VerifiableExtension` to each of your data-models (including `SiteTree` subclasses) that you'd like to be "Verifiable", and for each such model, declare a list of fields who's data should be verifiable:


```YML
My\Name\Space\Model\MyModel:
  extensions:
    - PhpTek\Verifiable\Verify\VerifiableExtension
  verifiable_fields:
    - Foo
    - Bar
    - FooBar
```

Be sure to run `flush=all` via your browser or the CLI to refresh SilversStripe's YML config cache.
