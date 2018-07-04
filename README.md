# SilverStripe Verifiable

[![Build Status](https://api.travis-ci.org/phptek/silverstripe-verifiable.svg?branch=master)](https://travis-ci.org/phptek/silverstripe-verifiable)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/?branch=master)
[![License](https://poser.pugx.org/phptek/verifiable/license.svg)](https://github.com/phptek/silverstripe-verifiable/blob/master/LICENSE.md)

## What is this?

A content verification module for SilverStripe applications. It gives content authors and business owners the ability to verify the integrity of their data at any point in time; Data entered by an author can be verified independently.

## Background

For decades software users have taken it for granted that their application's data is safe from tampering. That developers, vendors and DBA's are somehow above changing things, whether they meant to or not. Simply put: We have have put our faith in these entities for no real reason other than they probably sounded like they knew what they were doing.

Because no I.T. system can claim _immutability_, this module therefore offers _verifiability_; data who's integrity is mathematically provable at any point in time. If some data were to change when it wasn’t supposed to, then those that need to know, can be in the know. 

Of course; the identification of unwarranted behaviour and negative outcomes is not the only application of verifiability. Verifiability is actually more concerned with _transparency_,
especially in the context of public data.

Without any specialist configuration; by default, the module offers a simple CMS interface that allows the content of a specific version of any page, to be verified as not having changed since it was published.

## How does it work?

With the most basic configuration; on each page-write, a sha256 hash of selected field-data is created and submitted to a 3rd party backend that implements a [Merkle or Binary Hash Tree](https://en.wikipedia.org/wiki/Merkle_tree). The true power of this module however, comes with giving developers the ability to supply their own data to be hashed and submitted. All developers need to is declare a `verify()` method on any decorated `DataObject` subclass, and the module will call that on every write. Uses of this method might be to notarise uploaded `File` objects or use SilverStripe to become the next [NewsDiffs](https://newsdiffs.org/). See the configuration section below. 

The two service classifications that fit the bill are; public blockchains (notably Bitcoin’s and Ethereum’s) and standalone or clustered Merkle Tree storage systems like [Trillian](https://github.com/google/trillian/).

In addition to processing and persisting value-based transactions in their native cryptocurrencies, the Bitcoin and Ethereum blockchains are also capable of storing limited-sized arbitrary data, making them ideal for storing Merkle Root hashes from which individual "leaf" hashes can be mathematically derived. We make use of REST calls to the [Tierion](https://tierion.com/) blockchain network using its [Chainpoint](https://chainpoint.org) service. Tierion is itself a permissionless blockchain network which will periodically write Merkle Root hashes to both Bitcoin and Ethereum.

## Requirements

At least PHP7 and SilverStripe 4.

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

You can supply your own data to be hashed and submitted. Developers simply declare a `verify()` method on any `DataObject` subclass that is decorated with `VerifiableExtension`, and its return value will be hashed and submitted to the backend. E.g:

```PHP
class MyDataObject extends DataObject
{

    /**
     * Take the contents of a file for a basic form of digital notarisation.
     * 
     * @param string $filename 
     * @return string
      */ 
      public function verify(string $filename) : string
      {
            return file_get_contents($filename);
      }

}

```

Be sure to run `flush=all` via your browser or the CLI to refresh SilverStripe's YML config cache.

You'll also need to install a simple cron job on your hosting environment which invokes the `FullProofFetchTask`. This will do the job of periodically querying the backend for a full-proof (Chainpoint backend only).

    ./vendor/bin/sake dev/cron quiet=1

## Background Reading

* [Trustworthy technology: The future of digital archives](https://blog.nationalarchives.gov.uk/blog/trustworthy-technology-future-digital-archives/)
* [Xero Integrates With Tierion To Secure Accounting Data Using Chainpoint](https://blog.tierion.com/2018/04/19/xero-integrates-with-tierion-to-secure-accounting-data-using-chainpoint/)
