[![Build Status](https://travis-ci.org/Divergence/framework.svg?branch=release)](https://travis-ci.org/Divergence/framework) [![Coverage Status](https://coveralls.io/repos/github/Divergence/framework/badge.svg?branch=release)](https://coveralls.io/github/Divergence/framework?branch=release) [![codecov](https://codecov.io/gh/Divergence/framework/branch/release/graph/badge.svg)](https://codecov.io/gh/Divergence/framework) [![Latest Stable Version](https://poser.pugx.org/divergence/divergence/downloads)](https://packagist.org/packages/divergence/divergence) [![Latest Unstable Version](https://poser.pugx.org/divergence/divergence/v/stable)](https://packagist.org/packages/divergence/divergence) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Divergence/framework/badges/quality-score.png?b=release)](https://scrutinizer-ci.com/g/Divergence/framework/?branch=release) [![License](https://poser.pugx.org/divergence/divergence/license)](https://packagist.org/packages/divergence/divergence)

---
Divergence is a PHP framework designed for rapid development and modern practices without becoming an over abstracted mess.

## [Documentation](https://github.com/Divergence/docs#divergence-framework-documentation)
## [Getting Started](https://github.com/Divergence/docs/blob/release/gettingstarted.md#getting-started)

[![asciicast](https://asciinema.org/a/FhE9hATLKDhH7oQfFbeNG5hzs.png)](https://asciinema.org/a/FhE9hATLKDhH7oQfFbeNG5hzs)

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
    * Built with basic class inheritance in mind.

* Controllers
    * Integrated CRUD controllers load templates or JSON depending on the response mode. 
    * Build HTTP APIs in minutes by extending `RecordsRequestHandler` and setting the one config variable: the name of your model class.
    * Use a pre-made security trait with RecordsRequestHandler or extend it and write in your own permissions.
    * Reuse permission traits from one model to another. 
 
 * Templates
    * Out of the box support for Smarty & Dwoo Templates using the Dwoo engine
    * Respond with a Template instantly `RequestHandler::respond('/path/to/tpl')`

### Contributing To Divergence

**All issues and pull requests should be filed on the [Divergence/framework](http://github.com/Divergence/framework) repository.**

### License

The Divergence framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Credits
- Much of the original code in this framework was published as part of the [PHP framework portion](https://github.com/JarvusInnovations/Emergence-Skeleton) of [Emergence](https://github.com/JarvusInnovations/Emergence) by [Chris Alfano](https://github.com/themightychris).
 - This project is maintained by [Henry Paradiz](https://github.com/hparadiz)