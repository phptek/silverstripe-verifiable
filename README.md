# SilverStripe Verifiable

[![Build Status](https://api.travis-ci.org/phptek/silverstripe-verifiable.svg?branch=master)](https://travis-ci.org/phptek/silverstripe-verifiable)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/?branch=master)
[![License](https://poser.pugx.org/phptek/verifiable/license.svg)](https://github.com/phptek/silverstripe-verifiable/blob/master/LICENSE.md)

## What is this?

A configurable data and content verification module for SilverStripe applications. It provides independent data-integrity verification features to content authors and developers. Data can be verified independently of SilverStripe and its database, at any time.

## Background

For decades users of software have taken it for granted that their data is safe from tampering. That developers, vendors and database administrators would never make unauthorised data modifications, regardless of any mal-intent. Simply put: Users have put their faith into these entities for no reason other than they probably sounded like they knew what they were doing.

No centralised I.T. can claim immutability. This module therefore offers verifiability; data who's integrity is mathematically provable at any point in time. If some data were to change when it shouldn't have, then those that need to know, can.

The identification of unwarranted behaviour and negative outcomes is not the only application of verifiability. Verifiability is a research domain of its own, that is closely aligned with those of the decentralisation movement typified by cryptocurrencies and permissionless blockchain networks. Verifiability is also concerned with transparency and accountability in the context of public data and this module hopes to help with this.

Without any configuration; the module's defaults offer a simple administrative interface that allows the content of a specific version of any [versioned](https://github.com/silverstripe/silverstripe-versioned) `DataObject`, to be verified as not having changed since it was published.

## How does it work?

With the most basic configuration; on each write-operation, a sha256 hash of selected field-data is created and submitted to a separate backend system that implements a [Merkle or Binary Hash Tree](https://en.wikipedia.org/wiki/Merkle_tree). This backend is either a local or a remote immutable, semi-immutable or proxy data-store.

The two systems that we are aware of that fit the bill as servicable Merkle backends of this kind are; public blockchains (notably Bitcoin and Ethereum) and standalone or clustered Merkle Tree storage systems like [Trillian](https://github.com/google/trillian/). The default is to use the [Chainpoint](https://chainpoint.org) service as a proxy data-store. In addition to processing and persisting value-based transactions in their native cryptocurrencies, the Bitcoin and Ethereum blockchains are also capable of storing arbitrary data of a limited size. This makes them ideal for storing Merkle Root hashes from which individual "leaf" hashes can be mathematically derived. The module's default Chainpoint adaptor makes use of REST calls to the [Chainpoint](https://chainpoint.org/) Network. Chainpoint periodically writes Merkle Root hashes to the Bitcoin blockchain.

Developers are also free and able to integrate with different backends using the module's pluggable API. See the "Extending" section below.

![alt text](doc/img/screenshot-asset-admin-ss4.2.png "Screenshot from SilverStripe 4.2 asset admin")

![alt text](doc/img/screenshot-page-admin-ss4.2.png "Screenshot from SilverStripe 4.2 page admin")

## Requirements

* At least PHP7 and SilverStripe 4.
* Your PHP setup also needs Zlib and [msgpack](https://msgpack.org/). These are required to decode the binary format proof returned from most Chainpoint REST API calls, into a valid JSON-LD v3 Chainpoint Proof.

## Install

    #> composer require phptek/verifiable

## Configuration

Configure the desired backend:

```YML
PhpTek\Verifiable\Backend\VerifiableServiceFactory
  backend: chainpoint
```

Add the `VerifiableExtension` to each data-model that you'd like to be "Verifiable":

```YML
My\Name\Space\Model\MyModel:
  extensions:
    - PhpTek\Verifiable\Extension\VerifiableExtension
```

And for SilverStripe 4 `File` classes:

```YML
SilverStripe\Assets\File:
  extensions:
    - SilverStripe\Versioned\Versioned
    - PhpTek\Verifiable\Extension\VerifiableExtension
SilverStripe\AssetAdmin\Forms\FileFormFactory:
  extensions:
    - PhpTek\Verifiable\Model\VerifiableFileExtension
```

By default, any fields on your decorated model(s) that you define in the `verifiable_fields` array, will be hashed and submitted to the backend thus:

```YML
My\Name\Space\Model\MyModel:
  verifiable_fields:
    - Title
    - Content
```

When `verifiable_fields` are declared on `File` classes and subclasses, then content of the file itself is also taken into account and will comprise
the generated hash, as well as the values of each DB field.

Developers can also supply their own data to be hashed and submitted. Simply declare a `verify()` method on any `DataObject` subclass that is decorated with `VerifiableExtension`
and its return value will be hashed and submitted to the backend. E.g:

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

You'll also need to install a simple cron job on your hosting environment which invokes `UpdateProofController`. This will do the job of periodically querying the backend for a full-proof (Chainpoint backend only).

    ./vendor/bin/sake verifiable/tools/update

## Extending

The true power of this module for developers is twofold:

 1. Give developers the ability to supply their own data to be hashed and submitted. 

All developers need to do is declare a `verify()` method on any decorated and [versioned](https://github.com/silverstripe/silverstripe-versioned) `DataObject` subclass, and the module will call it on every write. Uses of this method might be to notarise uploaded `File` objects or use SilverStripe to become the next [NewsDiffs](https://newsdiffs.org/). See the configuration section below.

 2. To use an alternative to Chainpoint, the module's pluggable API allows developers to use a different Merkle backend. 

See the `GatewayProvider` and `ServiceProvider` interfaces in the "src/Backend" directory, as well as `BackendServiceFactory` to see how backends are instantiated.

## Known Issues and Caveats

### Version 1.x

* For `File` objects, only the contents of the _original file_ and its file name are hashed along with any `verifiable_fields` present. For all resized images
  present in your system, changes to these will not be flagged as evidence of tampering. Only changes to the original are accounted for.
* Note that when publishing content, `SiteTree` or otherwise, a slight delay will occur due to the required network requests to the Chainpoint network.

## Background Reading

* [Trustworthy technology: The future of digital archives](https://blog.nationalarchives.gov.uk/blog/trustworthy-technology-future-digital-archives/)
* [Xero Integrates With Tierion To Secure Accounting Data Using Chainpoint](https://blog.tierion.com/2018/04/19/xero-integrates-with-tierion-to-secure-accounting-data-using-chainpoint/)
* [The Chainpoint Protocol](https://chainpoint.org/)
* [The ChainPoint Whitepaper](https://github.com/chainpoint/whitepaper/blob/master/chainpoint_white_paper.pdf)
