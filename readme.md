[![Build Status](https://travis-ci.org/hparadiz/divergence.svg?branch=master)](https://travis-ci.org/hparadiz/divergence) [![Coverage Status](https://coveralls.io/repos/github/hparadiz/divergence/badge.svg?branch=master)](https://coveralls.io/github/hparadiz/divergence?branch=master) [![codecov](https://codecov.io/gh/hparadiz/divergence/branch/master/graph/badge.svg)](https://codecov.io/gh/hparadiz/divergence)

# About
Divergence is a PHP framework designed for rapid development and modern practices without becoming an over abstracted mess.

## Purpose
This collection of classes contains my favorite building blocks for developing websites with PHP and they have an impressive track record with hundreds of currently active websites using one version or another of the classes in this framework. While they were originally written years ago they are all PSR compatible and support modern practices out of the box.

Unit testing the code base and providing code coverage is a primary goal of this project.

# Main Features
 * Models
    * Real PHP classes.
    * Extend an `ActiveRecord` class.
    * Use `traits` for versioning and ORM.
    * Automatically creates table on first time use.
    * Built in support for MySQL.

 * Database
    * Use the existing DB class or access PDO directly by calling `DB::getConnection()`.

 * Routing
    * Simpler, faster, tree based routing system.
    * Built with basic class inheritence in mind.

* Controllers
    * Integrated CRUD controllers load templates or JSON depending on the response mode. 
    * Build HTTP APIs in minutes by extending `RecordsRequestHandler` and setting the one config variable: the name of your model class.
    * Use a pre-made security trait with RecordsRequestHandler or extend it and write in your own permissions.
    * Reuse permission traits from one model to another. 
 
 * Templates
    * Out of the box support for Smarty & Dwoo Templates using the Dwoo engine
    * Respond with a Template instantly `RequestHandler::respond('/path/to/tpl')` 


## Background
ActiveRecord, RequestHandler, RecordsRequestHandler, and the DB class were originally written by Chris Alfano for MICS, a predecessor of the current [Emergence](https://github.com/JarvusInnovations/Emergence-Skeleton) framework. Various versions of these classes have since been used to build hundreds of websites by him and others at his company [Jarvus Innovations](https://github.com/JarvusInnovations). I have also used these files to build dozens of websites both personally and professionally. Many of these sites are still very much alive. There are minor differences between our versions. For example I let you set a primary key when defining a model where as his version doesn't allow for that.


### Contributing To Divergence

**All issues and pull requests should be filed on the [hparadiz/divergence](http://github.com/hparadiz/divergence) repository.**

### License

The Divergence framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Achnowledgements
Based on the [PHP framework portion](https://github.com/JarvusInnovations/Emergence-Skeleton) of [Emergence](https://github.com/JarvusInnovations/Emergence).