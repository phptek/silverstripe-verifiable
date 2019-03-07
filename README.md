# SilverStripe Verifiable

[![Build Status](https://api.travis-ci.org/phptek/silverstripe-verifiable.svg?branch=master)](https://travis-ci.org/phptek/silverstripe-verifiable)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/?branch=master)
[![License](https://poser.pugx.org/phptek/verifiable/license.svg)](https://github.com/phptek/silverstripe-verifiable/blob/master/LICENSE.md)

## What is this?

SilverStripe content authors are able to verify whether or not their content has been tampered with.

It is a configurable data and content verification module for SilverStripe applications. It provides independent and data-integrity verification to content authors and developers. Data can be verified independently of SilverStripe and its database, and at any time. The module can also be extended by means of a powerful API.

## Background

For decades users of software have taken it for granted that their data is safe from tampering. That developers, vendors and database administrators will not make unauthorised modifications to data or code, regardless of any mal-intent. Simply put: Users have put their faith into these entities for no reason other than they probably sounded like they knew what they were doing.

No centralised I.T. can claim immutability. This module therefore offers verifiability; data who's integrity is mathematically provable at any point in time. If data changes when it shouldn't have, then those that need to know, can.

The identification of unwarranted behaviour and negative outcomes is not the only application of verifiability. Verifiability is a research domain of its own that is closely aligned with those of the decentralisation movement, typified by cryptocurrencies and permissionless blockchain networks. Verifiability is also concerned with transparency and accountability in the context of public data and this module will help achieve this for SilverStripe applications.

Without any configuration; the module's defaults offer a simple administrative interface that allows the content of a specific version of any [versioned](https://github.com/silverstripe/silverstripe-versioned) `DataObject`, to be verified as not having changed since it was published.

## How does it work?

With the most basic configuration; on each database write-operation, a sha256 hash of selected field-data is created and submitted to a separate backend system that implements a [Merkle or Binary Hash Tree](https://en.wikipedia.org/wiki/Merkle_tree). This backend can be a local or remote immutable or semi-immutable data store, or a proxy data-store to either.

The two systems that we are aware of that fit the bill as serviceable Merkle backends of this kind are; public blockchains (notably Bitcoin and Ethereum) and standalone or clustered Merkle Tree storage systems like [Trillian](https://github.com/google/trillian/). 

In addition to processing and persisting value-based transactions in their native cryptocurrencies, the Bitcoin and Ethereum blockchains are also capable of storing arbitrary data of a limited size, the former by means of its [OP_RETURN](https://en.bitcoin.it/wiki/OP_RETURN) opcode. This makes them ideal for storing Merkle Root hashes from which individual "leaf" hashes can be mathematically derived.

The module's default Chainpoint adaptor makes use of REST calls to the [Chainpoint](https://chainpoint.org/) Network. Chainpoint will periodically write Merkle Root hashes to the Bitcoin blockchain.

Developers are also free and able to integrate with different backends using the module's pluggable API. See the "Extending" section below.

![alt text](doc/img/screenshot-asset-admin-ss4.2.png "Screenshot from SilverStripe 4.2 asset admin")

![alt text](doc/img/screenshot-page-admin-ss4.2.png "Screenshot from SilverStripe 4.2 page admin")

## Requirements

 * At least PHP7 and SilverStripe 4.
 * PHP setup with the following to decode binary format proofs returned from Chainpoint REST API calls
   * [Zlib](https://secure.php.net/manual/en/book.zlib.php)
   * [msgpack](https://msgpack.org/)
 * [allow_url_fopen](http://nz2.php.net/manual/en/filesystem.configuration.php#ini.allow-url-fopen) enabled in php.ini.

## Install

    #> composer require phptek/verifiable

### Verify the package

The package comes with a `CHECKSUM` file which can be used to verify that the package contents have not altered since they were pushed to GitHub. Simply change into the "verifiable" directory, run the following command and compare its output with the `CHECKSUM` file. If for any reason, the `CHECKSUM` file is missing, you can still compare with the file for the equivalent build on GitHub itself:

```
#> diff CHECKSUM <( ./tools/checksum.sh true )
```
## Configuration

Configure the desired backend via SilverStripe's YML config API e.g in a custom `app/_config/verifiable.yml` file:

```YML
---
Name: mysite-verifiable-chainpoint-backend-config
After: '#verifiable-chainpoint-backend-config'
---
PhpTek\Verifiable\Backend\VerifiableServiceFactory:
  backend: mybackend
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

When `verifiable_fields` are declared on `File` classes and subclasses, then content of the file itself is also taken into account and will comprise the generated hash, as well as the values of each DB field.

Developers can also supply their own data to be hashed and submitted. Simply declare a `verify()` method on any `DataObject` subclass that is decorated with `VerifiableExtension` and its return value will be hashed and submitted to the backend. E.g:

```PHP
class MyDataObject extends DataObject
{

    /**
     * Take the contents of a file for a very basic form of digital notarisation.
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

Be sure to run `flush=all` via your browser or the CLI, in order to refresh SilverStripe's YML config cache.

You'll also need to install a simple cron job on your hosting environment which invokes `UpdateProofController`. This will do the job of periodically querying the backend for a full-proof (Chainpoint backend only).

    ./vendor/bin/sake verifiable/tools/update

## Extending

Developers can extend the module by means of a powerful API.

 1. Developers can customise the data to be hashed and submitted to a backend.

Developers can alter a `DataObject`'s `verifiable_fields` (see above) to hash a greater number of fields or; by declaring a `verify()` method on any decorated and [versioned](https://github.com/silverstripe/silverstripe-versioned) `DataObject` subclass, the module will call it on every write. Uses of this method might be to notarise uploaded `File` objects or to use SilverStripe to become the next [NewsDiffs](https://newsdiffs.org/). See the configuration section above.

 2. Developers can build alternative backend adaptors.

The module comes pre-configured with a backend for the [Chainpoint](https://chainpoint.org/) network. But should you wish to use something else as an immutible data store, or simple an alternative Merkle-to-Bitcoin system, then this can be done. Have a look at the `GatewayProvider` and `ServiceProvider` interfaces in the "src/Backend" directory, as well as `BackendServiceFactory` to see how backends are instantiated. Once you've developed your backend, refer to the "Configuration" section above, for how to override the module's default "Chainpoint" backend.

## Known Issues and Caveats

### Version 1.x

* For `File` objects; changes to any resized images present in your system will not be flagged as evidence of tampering. Verifiable will only detect changes made to the original image file.
* Note that when publishing content, `SiteTree` or otherwise, a slight delay will occur due to the required network requests to the Chainpoint network.

## Background Reading

* [Trustworthy technology: The future of digital archives](https://blog.nationalarchives.gov.uk/blog/trustworthy-technology-future-digital-archives/)
* [Xero Integrates With Tierion To Secure Accounting Data Using Chainpoint](https://blog.tierion.com/2018/04/19/xero-integrates-with-tierion-to-secure-accounting-data-using-chainpoint/)
* [The Chainpoint Protocol](https://chainpoint.org/)
* [The ChainPoint Whitepaper](https://github.com/chainpoint/whitepaper/blob/master/chainpoint_white_paper.pdf)

## Support Me

If you like what you see, support me! I accept Bitcoin:

<table border="0">
       <tr border="0">
               <td rowspan="2" border="0">
                       <img src="https://bitcoin.org/img/icons/logo_ios.png" alt="Bitcoin" width="64" height="64" />
               </td>
       </tr>
       <tr border="0">
               <td border="0">
                       <b>bc1qmg0jjtmu3fmm53mkvw69xz8kerq3l3lnh6529d</b>
               </td>
       </tr>
</table>
<p>&nbsp;</p>
<p>&nbsp;</p>
