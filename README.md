# SilverStripe Verifiable

[![Build Status](https://api.travis-ci.org/phptek/silverstripe-verifiable.svg?branch=master)](https://travis-ci.org/phptek/silverstripe-verifiable)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/phptek/silverstripe-verifiable/?branch=master)
[![License](https://poser.pugx.org/phptek/verifiable/license.svg)](https://github.com/phptek/silverstripe-verifiable/blob/master/LICENSE.md)

## What is this?

THIS IS A WORK IN PROGRESS

A module for SilverStripe 4 applications that provides content authors and business owners the ability to verify the integrity of data over time. That-is; Data that is entered by an author or user that can be verified or audited independently, and many years after the fact.

Software users have for decades taken it for granted that their application's data is safe from tampering. Users simply trust that their vendors and developers will not behave in a manner that is counter to security and integrity.

But what do we mean by "integrity"? Well we don't mean "Tamper Proof" becuase there is no such guarantee, ever in life and in software. However we can do "Tamper Evident". Systems that are tamper evident will permit those who's job it is, to know if data has been tampered with at any time. 

## How does it work?

As part of the module's configuration, it needs to know what service should be used to "anchor" metadata in or to, such that data verification can occur. This can be done is several different ways, and this module provides 2 mechanisms or "backends" to acheive this, detailed below. Once data has been successfully anchored to the Merkle Tree store, the module will produce a valid JSON-LD [Chainpoint Proof](https://chainpoint.org/) which is stored to a pre-defined field.

To provide an additional level of security, generated hashes can also be digitally signed by users.

## Google Trillian

[Trillian](https://github.com/google/trillian/) is a project that came out of Google that provides a complimentary verification service to some data-store (SilverStripe's database for example) by means of a Merkle Tree. In the context of this module, and with this backend enabled in the module's YML config, fields on your `DataObject` subclasses for example, can be hashed and stored as leaf nodes in Trillian itself. 

## Public Blockchain  

For a more public, visible means of verifiability, we make use of both the [Bitcoin](https://bitcoin.org/) and [Ethereum](https://ethereum.org) blockchain networks. In addition to storing value-based transactions in their native cryptocurrencies, these networks' blockchains are also capable of storing arbitrary strings (albeit of a limited length). This makes them ideal for storing Merkle Root hashes from which individual "leaf" hashes of data can be mathematically derived.

We make use of REST calls to the [Tieron](https://tieron.com/) blockchain network, which itself writes hashed data to Bitcoin and Ethereum on a periodic (hourly) basis.

## Install

    #> composer install phptek/verifiable

## Configuration

Optionally install the [JSONText](https://github.com/phptek/silverstripe-jsontext) module to provide a JSON query API to all your stored [Chainpoint Proof](https://chainpoint.org/) documents.





