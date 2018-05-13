[![Latest Stable Version](https://poser.pugx.org/divergence/divergence/v/stable)](https://packagist.org/packages/divergence/divergence) | [![Total Downloads](https://poser.pugx.org/divergence/divergence/downloads)](https://packagist.org/packages/divergence/divergence) | [![Latest Unstable Version](https://poser.pugx.org/divergence/divergence/v/unstable)](https://packagist.org/packages/divergence/divergence) | [![License](https://poser.pugx.org/divergence/divergence/license)](https://packagist.org/packages/divergence/divergence) | [![Build Status](https://travis-ci.org/hparadiz/divergence.svg?branch=master)](https://travis-ci.org/hparadiz/divergence) [![Coverage Status](https://coveralls.io/repos/github/hparadiz/divergence/badge.svg?branch=master)](https://coveralls.io/github/hparadiz/divergence?branch=master) [![codecov](https://codecov.io/gh/hparadiz/divergence/branch/master/graph/badge.svg)](https://codecov.io/gh/hparadiz/divergence)

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



### Contributing To Divergence

**All issues and pull requests should be filed on the [hparadiz/divergence](http://github.com/hparadiz/divergence) repository.**

### License

The Divergence framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Achnowledgements
Based on the [PHP framework portion](https://github.com/JarvusInnovations/Emergence-Skeleton) of [Emergence](https://github.com/JarvusInnovations/Emergence).