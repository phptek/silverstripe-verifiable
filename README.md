# SilverStripe Verifiable

## What is this?

A module for SilverStripe 4 applications and websites that provides application authors and business owners the ability to verify application data. That-is; Data that may be entered by an author or user that can be verified independently, and many years after the fact.

Application users and software development vendor's clients have for decades taken it for granted that their application's data is safe from tampering. Clients and users simply trust that their vendors, developers and users will not behave in a manner that is counter to secure and tamper-evident data manipulation.

But what do we mean by "Tamper Evident"? Surely we mean "Tamper Proof"?. In an ideal world, yes, this is exactly what me might mean but we concede that there are no guarantees in this world, especially in the domain of software development. Systems that are tamper evident however will permit users who's job it is to know about these things, to know if data has been tampered with at any time. 

## How does it work?

As part of the module's configuration, it needs to know what service should be used to "anchor" metadata in or to, such that data verification can occur. This can be done is several different ways, and this module provides 2 mechanisms or "backends" to acheive this, detailed below. 

To provide an additional level of security, generated hashes can also be digitally signed by users.

## Google Trillian

[Trillian](https://github.com/google/trillian/) is a project that came out of Google that provides a complimentary verification service to some data-store (SilverStripe's database for example) by means of a Merkle Tree. In the context of this module, and with this backend enabled in the module's YML config, fields on your `DataObject` subclasses for example, can be hashed and stored as leaf nodes in Trillian itself. 

## Public Blockchain  

For a more public, visible means of verifiability, we make use of both the [Bitcoin](https://bitcoin.org/) and [Ethereum](https://ethereum.org) blockchain networks. In addition to storing value-based transactions in their native cryptocurrencies, these networks' blockchains are also capable of storing arbitrary strings (albeit of a limited length). This makes them ideal for storing Merkle Root hashes from which individual "leaf" hashes of data can be mathematically derived.

We make use of REST calls to the [Tieron](https://tieron.com/) blockchain network, which itself writes hashed data to Bitcoin and Ethereum on a periodic (hourly) basis.

## Install

    #> composer install phptel/verifiable

## Configuration

Optionally install the  

TODO





