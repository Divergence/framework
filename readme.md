[![Code Coverage](https://scrutinizer-ci.com/g/Divergence/framework/badges/coverage.png?b=develop)](https://scrutinizer-ci.com/g/Divergence/framework/?branch=develop) [![Latest Stable Version](https://poser.pugx.org/divergence/divergence/downloads)](https://packagist.org/packages/divergence/divergence) [![Build Status](https://scrutinizer-ci.com/g/Divergence/framework/badges/build.png?b=develop)](https://scrutinizer-ci.com/g/Divergence/framework/build-status/develop) [![Latest Unstable Version](https://poser.pugx.org/divergence/divergence/v/stable)](https://packagist.org/packages/divergence/divergence) [![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/Divergence/framework/badges/quality-score.png?b=develop)](https://scrutinizer-ci.com/g/Divergence/framework/?branch=release) [![License](https://poser.pugx.org/divergence/divergence/license)](https://packagist.org/packages/divergence/divergence)

---
Divergence is a PHP framework designed for rapid development and modern practices without becoming an over abstracted mess.

**Requires PHP 8.1+**

## [Documentation](https://github.com/Divergence/docs#divergence-framework-documentation)
## [Getting Started](https://github.com/Divergence/docs/blob/release/gettingstarted.md#getting-started)

## Minimal Model

```php
<?php

namespace App\Models;

use Divergence\Models\Model;
use Divergence\Models\Mapping\Column;

class Article extends Model
{
    public static $tableName = 'articles';

    #[Column(type: 'string', notnull: true)]
    private string $Title;

    #[Column(type: 'clob', notnull: true)]
    private string $Body;
}
```


## Purpose
Divergence is a full-featured ActiveRecord framework built on a reflection-driven DTO-style backend. It is designed for performance, and it backs that up with benchmarks. It gives developers a fast procedural-global path for getting real work done, while its internal abstractions stay disciplined and modern. Divergence follows PSR-4, PSR-7, and PSR-15 wherever doing so strengthens the framework instead of turning it into ceremony.

Unit testing the code base and providing code coverage is a primary goal of this project.

# Main Features
 * Models
    * Real PHP classes.
    * Declare fields with static arrays or PHP 8 attributes (`#[Column(...)]`).
    * Declare relationships with static arrays or PHP 8 attributes (`#[Relation(...)]`).
    * Built in support for relationships and object versioning.
    * Speed up prototyping and automate new deployments by automatically creating tables based on your models when none are found.
    * Built in support for MySQL, PostgreSQL, and SQLite.

 * Routing
    * Simpler, faster, tree based routing system.
    * Built with basic class inheritance in mind.

* Controllers
    * PSR-7 compatible controllers.
    * Pre-made REST API controllers allow you to build APIs rapidly.
    * 100% Unit test coverage for filters, sorters, and conditions.
    * Build HTTP APIs in minutes by extending `RecordsRequestHandler` and setting the one config variable: the name of your model class.
    * `RecordsRequestHandler` and `MediaRequestHandler` route response-producing actions through focused endpoint classes instead of one large handler method pile.
    * Use a pre-made security trait with RecordsRequestHandler or extend it and write in your own permissions.
    * Standard permissions interface allows reuse of permission traits from one model to another.
 
 * Templates
    * Out of the box support for Twig

 * Media
    * Out of the box support for a media storage.
    * Automated thumbnail generation for JPEG, GIF, PNG, and PDF.
    * Built in support for MP4 and WEBM chunkable emitters allowing you to easily host videos with the ability to seek.
    * Manage media remotely with a built in JSON API using the standard permissions interface for all controllers.
    * Supports POST and PUT request types for media uploads.

## Installation

```bash
composer require divergence/divergence
```

The [divergence/cli](https://packagist.org/packages/divergence/cli) package is also available to initialize new projects and manage database configurations from the command line.

## Running Tests

```bash
# Run all test suites
composer test

# MySQL suite only
composer test:mysql

# PostgreSQL suite only
composer test:pgsql

# SQLite in-memory suite only
composer test:sqlite

# Run merged coverage across MySQL, PostgreSQL, and SQLite
composer test:coverage
```

### Contributing To Divergence

**All issues and pull requests should be filed on the [Divergence/framework](http://github.com/Divergence/framework) repository.**

### License

The Divergence framework is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT)

### Credits
- The patterns in this framework are a fork of the [PHP framework portion](https://github.com/JarvusInnovations/Emergence-Skeleton) of [Emergence](https://github.com/JarvusInnovations/Emergence) by [Chris Alfano](https://github.com/themightychris).
- This project is maintained by [Henry Paradiz](https://github.com/hparadiz)
