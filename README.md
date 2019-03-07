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

<img src="data:image/png;base64,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48YNGza0U1tpbd26dd26dR4u8K677rrtttus2y16qjLd2RYytcWdBWqIPNemoZDI19h65pln5s2bV1BQsHDhwmHDhq1ateqee+5pp7Z27NgxadKk2tradlp+xoVCDdsuyD1li2dE1g+Fr3/ddP369dFo9N1337Xufvzxx/n5+e3U1vnz59tpyUHw5ptv2reD3FO2eEZk/VD4erQViUTOnj174cIF6+7w4cMHDhxo3X7ttdduuumm7t27l5WVvfzyy9aDJ0+efOihh0aPHt29e/e77rrr008/tR4vKyu77777XnjhhZtuuqlnz57z58+/fPlyYkMzZsxYsmSJUqqgoMAwjIMHDyqlcnJyXnzxxaRvefbZZ2+44Ybu3bvfdtttqWYNkq6hfQZ08803v/3224mvf/bZZ0ePHt2zZ8+777770qVLLZ5K1dz8+fM7depknUldvXq1S5cu48aNs0fDMIxnnnnGGgHr8aQ9de6sUurGG28cPny4fdda8m9+8xuHHl133XUzZsyw7xYXF9t3y8rK7r///k2bNg0ZMuSOO+5IbMhhi1sbcdOmTdYoPfnkk/F4/Omnnx40aNDAgQOfe+45e92S7gMt2i0qKmo9DmVlZT/96U9/9atfjRw5slevXvPnz7fXxHmIUg2C84537ZvVobOptG401S7hPJ4OY+U8jJmhffTGG2/k5+f36NFj6dKlp06dsh+35n2mTZu2du3aefPmTZ06NRaLaa0rKirGjx+/bNmyVatWFRUVlZeXW6+PRqOdO3fu16/fI488Yp10LF++PLGhXbt23XnnnUqpzZs3b9u2rbq6OhqNKqWSvmXZsmX5+flLlizZsmXLnDlzCgoKTp8+3WLNk67h6tWrlVJz585du3bt1KlTlVIvvfSS9fq1a9cqpWbOnLlmzZrZs2crpUpLS900t337dqXUnj17tNZ79uyxttHZs2e11hs3blRKffLJJ9YIjB07NmlPrWdTddaybds2pdQ777xj3V21alVOTk5VVZVDj0pKSqZPn24voW/fvvbdaDTau3fvoqKiBx98cMuWLW62uPWu/Pz8AQMGLFmyxOrCqFGjxowZ88QTT4wdOzYnJ+fEiRMO+0CLdh999NFU4zBixIgVK1Y88sgjkUhk8uTJ9ntTDZHDIDjseJ5sVocd3trcLW4nbTTpLmFzaMJ5rJI+lSm+xpbW+tixY9OnT1dKhUKhxYsX19TUVFdX5+fn/+hHP2r94vPnz9u3ly5dqpSydoVoNNqvX7+qqirrqaKiotbjuHjxYqVUTU2NdTfVW06fPh0KhdatW2e/saSkZMWKFYmLSrqGVVVVkUhkwYIF9iMVFRVFRUWxWOzixYsFBQWzZ8+2n5o8ebIVW2mbq66uDoVCS5cu1VovWbKkoqIiPz//+eef11rPnTvXzr7EfbdFT92MT21tbe/evefNm2fdHTt27MyZMx16pNPFVigU2rt3r06m9RZvvZInTpxQSpWXl9fW1mqtX331VaWUlYCp9oHW7SYdh8GDB1vL1FqvWrVKKbVv3z6HIXIehO+8F7ncrA47fOvYcmi09VDYHJpwGKtUT2WK379JHDp06Ouvv37ixIl77713/fr1U6ZM2bdvX21t7Zw5c1q/uGfPngcPHnz44YdvvfXW9evXW0NsPdW7d+9evXpZt0eMGFFVVZW26aRv2bdvn2maixYtMhqdPn361KlTiW/cv39/6zXcv39/XV2d9YG0TJ8+vbq6+oMPPjh8+HBNTc2sWbPsp+zp87TNfe9735s4ceLu3buVUm+99daUKVPKy8vfeustpdSePXusH/5uOI9PXl7eAw888Pvf//7ChQsffvjhoUOHFixY4NCjtM2NGjXqlltuSfpU6y1u/4bLXskhQ4ZEIpHBgwfn5eVZK6yUstbZYR9wbtfSrVs3a5lKKeuo4ejRow5DlHYQvtte5HKzOne2BTe7bmsOTTiMlcNTGZGZC44NGTJk06ZNhYWFq1evtgba/mAneuihh3bs2LFw4cJf/vKXhw4dWrRoUdKl5eS0uRf2W3r06KGUWr16dXl5uf1s3759E18ci8VSrWHig9Zt0zQvXryolIpEIq1f76a5adOmLV++/Pjx44cOHdqwYUNOTs6aNWuOHDlSVVVlHfy3VdLxeeCBB1auXLl9+/bz588XFhZOnz7d+lAl7ZF1177RWtLBSZS4xT/66KORI0c6LCFxhZ33gbTtJrJ+s1ZQUND6qRZD5DAISd/l1WZ1ucO7b7Q1l004jJXDU77x72jr6tWrDz/8cOIso7UrlJeX5+Tk7Nixw368vr5eKXXkyJHKysqVK1c+/vjjzj9Rk7JSo6amxvll48eP79y584kTJ25OMGTIkMTXRKPR1mtoPbhz5077wZ07d/bo0WPUqFGjRo1SSu3du9d+yl4NN81NmzbNNM01a9b06NEjGo1OnTq1urq6srIyEoncfvvt37mnLRQXF8+ZM+f5559/5ZVX5s6dm5eX59AjpVRhYeGxY8esx48fP97ilwxJpdri1ufNjTbtA0nHITFu3njjjVAoNHHiRIeFOA9CKp5s1rbu8A6NptolnJtwGKtUT3355ZcZ+cq+f0dbR48e3b59+5YtW6ZNm1ZaWvree+/t2rVr3rx548aNW7p06YoVK2praydPnvzBBx/s2rXr0KFD+fn5oVBo48aNdXV1R48eraysbFNz48ePV0o99thj0Wh05syZqV7Ws2fPlStXLl68+MqVK1OmTKmurt6+ffvOnTv79Oljv6a4uDjpGi5fvvyJJ56IxWLRaHT37t27d+/etm1bOBweNGjQT37yk8rKStM0hw0btmPHjgMHDpSWlrpsbsKECb179968efOsWbPC4fANN9zQt2/fDRs2VFRUdOnSJW1P7V/VpbVw4ULrB7U1K9ynT59UPVJKVVRUrFu37tFHH41EIlu3bnVzmJNqi/fv39/lGrZpH0g6DocPH54xY8add9556NChLVu2LF68eNCgQQ4LcR6EVDzZrA6dLSkp2bdv3/vvvz9u3LjE26kaTbVLOI+nw1glfSoej48fP/7KlStnzpzp1KmTw/h4z8+JtKqqqkWLFlk/JUaNGrV69Wp7nm/jxo1jx44tKCiIRqP2L262bt06YMCAoqKiuXPnvvLKKyphjtCeodRaV1RUjBgxokVbsVjswQcfLCwsHDx48M6dO53f8tJLL0Wj0YKCghEjRixZsiRx2tKWag2tBydNmrRz5077xbW1tYsXLy4pKSkpKVm0aNHChQvtaVc3zS1YsEApVVlZad21fmm1atUq+wWJ3WnRU5fjYyktLW3xVKoenT9/ftasWd26dZs0adKOHTumTZuWOCWf2Fwihy3e4l35+fn2rwisGXqrv6n2gdZLSDoOpaWl99xzT2FhYWlp6cqVK62Z9bRDlGoQrnEvSrtZU3XWOuJ77LHHWtxO1WjroUjbhPNYpXpq5syZFRUV2ncdpSYRrR0/fvwHP/hBZWXlfffdl+l1aReU77nnMFYBHMbMTMkjs44cObJ79+7NmzePHTv23nvvzfTqAG3DH67piHbv3r1s2bJ+/fr97ne/c561AQKIk0QAwnC0BUAYYguAMMQWAGGILQDCEFsAhCG2AAjz3y9sGzLMkyfWAAAAAElFTkSuQmCC" alt="I accept Bitcoin" />
<p>&nbsp;</p>
<p>&nbsp;</p>
