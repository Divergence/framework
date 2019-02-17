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
 * @author Chris Alfano <themightychris@gmail.com>
 * @inheritDoc
 * @property string $Handle Unique identifier for this session used by the cookie.
 * @property string $LastRequest Timestamp of the last time this session was updated.
 * @property string $Binary Actually raw binary in the string
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
            'type' => 'binary',
        ],
    ];


    /**
     * Gets or sets up a session based on current cookies.
     * Will always update the current session's LastIP and LastRequest fields.
     *
     * @param boolean $create
     * @return static|false
     */
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

    /**
     * Gets by handle.
     *
     * @param string $handle
     * @return static
     */
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

    /**
     * @inheritDoc
     *
     * Saves the session to the database and sets the session cookie.
     * Will generate a unique handle for the session if none exists.
     *
     * @param boolean $deep
     * @return void
     */
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

    /**
     * Attempts to unset the cookie.
     * Unsets the variable from the $_COOKIE global.
     * Deletes session from database.
     *
     * @return void
     */
    public function terminate()
    {
        setcookie(static::$cookieName, '', time() - 3600);
        unset($_COOKIE[static::$cookieName]);

        $this->destroy();
    }



    /**
     * Makes a random 32 digit string by generating 16 random bytes
     * Is cryptographically secure.
     * @see http://php.net/manual/en/function.random-bytes.php
     *
     * @return void
     */
    public static function generateUniqueHandle()
    {
        do {
            $handle = bin2hex(random_bytes(16));
        } while (static::getByHandle($handle));
        // just in case checks if the handle exists in the database and if it does makes a new one.
        // chance of happening is 1 in 2^128 though so might want to remove the database call

        return $handle;
    }
}
