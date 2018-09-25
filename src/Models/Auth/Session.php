<?php
/**
 * This file is part of the Divergence package.
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 * @copyright 2018 Henry Paradiz <henry.paradiz@gmail.com>
 * @license MIT For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 *
 * @since 1.1
 */
namespace Divergence\Models\Auth;

use Divergence\Models\Model;
use Divergence\Models\Relations;

/**
 * Session object
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
 * @author  Chris Alfano <themightychris@gmail.com>
 *
 * {@inheritDoc}
 */
class Session extends Model
{
    use Relations;

    // Session configurables
    public static $cookieName = 's';
    public static $cookieDomain = null;
    public static $cookiePath = '/';
    public static $cookieSecure = false;
    public static $cookieExpires = false;
    public static $timeout = 31536000; //3600;

    // support subclassing
    public static $rootClass = __CLASS__;
    public static $defaultClass = __CLASS__;
    public static $subClasses = [__CLASS__];

    // ActiveRecord configuration
    public static $tableName = 'sessions';
    public static $singularNoun = 'session';
    public static $pluralNoun = 'sessions';

    public static $fields = [
        'ContextClass' => null,
        'ContextID' => null,
        'Handle' => [
            'unique' => true,
        ],
        'LastRequest' => [
            'type' => 'timestamp',
            'notnull' => false,
        ],
        'LastIP' => [
            'type' => 'binary'
        ],
    ];


    // Session
    public static function __classLoaded()
    {
        parent::__classLoaded();

        // auto-detect cookie domain
        if (empty(static::$cookieDomain)) {
            static::$cookieDomain = preg_replace('/^www\.([^.]+\.[^.]+)$/i', '$1', $_SERVER['HTTP_HOST']);
        }
    }


    public static function getFromRequest($create = true)
    {
        $sessionData = [
            'LastIP' => inet_pton($_SERVER['REMOTE_ADDR']),
            'LastRequest' => time(),
        ];

        // try to load from cookie
        if (!empty($_COOKIE[static::$cookieName])) {
            if ($Session = static::getByHandle($_COOKIE[static::$cookieName])) {
                // update session & check expiration
                $Session = static::updateSession($Session, $sessionData);
            }
        }

        // try to load from any request method
        if (empty($Session) && !empty($_REQUEST[static::$cookieName])) {
            if ($Session = static::getByHandle($_REQUEST[static::$cookieName])) {
                // update session & check expiration
                $Session = static::updateSession($Session, $sessionData);
            }
        }

        if (!empty($Session)) {
            // session found
            return $Session;
        } elseif ($create) {
            // create session
            return static::create($sessionData, true);
        } else {
            // no session available
            return false;
        }
    }

    public static function updateSession(Session $Session, $sessionData)
    {

        // check timestamp
        if ($Session->LastRequest < (time() - static::$timeout)) {
            $Session->terminate();

            return false;
        } else {
            // update session
            $Session->setFields($sessionData);
            if (function_exists('fastcgi_finish_request')) {
                register_shutdown_function(function (&$Session) {
                    $Session->save();
                }, $Session);
            } else {
                $Session->save();
            }

            return $Session;
        }
    }

    public static function getByHandle($handle)
    {
        return static::getByField('Handle', $handle, true);
    }

    public function getData()
    {
        // embed related object(s)
        return array_merge(parent::getData(), [
            'Person' => $this->Person ? $this->Person->getData() : null,
        ]);
    }

    public function save($deep = true)
    {
        // set handle
        if (!$this->Handle) {
            $this->Handle = static::generateUniqueHandle();
        }

        // call parent
        parent::save($deep);

        // set cookie
        setcookie(
            static::$cookieName,
            $this->Handle,
            static::$cookieExpires ? (time() + static::$cookieExpires) : 0,
            static::$cookiePath,
            static::$cookieDomain,
            static::$cookieSecure
        );
    }

    public function terminate()
    {
        setcookie(static::$cookieName, '', time() - 3600);
        unset($_COOKIE[static::$cookieName]);

        $this->destroy();
    }



    public static function generateUniqueHandle()
    {
        do {
            $handle = md5(mt_rand(0, mt_getrandmax()));
        } while (static::getByHandle($handle));

        return $handle;
    }
}
