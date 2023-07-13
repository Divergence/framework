<?php
/**
 * This file is part of the Divergence package.
 *
 * (c) Henry Paradiz <henry.paradiz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Divergence\Models\Auth;

use Divergence\Models\Model;
use Divergence\Models\Relations;
use Divergence\Models\Mapping\Column;

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
    public static string $cookieName = 's';
    public static string $cookieDomain = '';
    public static string $cookiePath = '/';
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

    #[Column(notnull: false, default:null)]
    protected $ContextClass;

    #[Column(type:'int',notnull: false, default:null)]
    protected $ContextID;

    #[Column(unique:true,length:32)]
    protected $Handle;

    #[Column(type:'timestamp',notnull:false)]
    protected $LastRequest;

    #[Column(type:'binary',length:16)]
    protected $LastIP;

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
        if (time() > $Session->__get('LastRequest') + static::$timeout) {
            $Session->terminate();

            return false;
        } else {
            // update session
            $Session->setFields($sessionData);
            if (function_exists('fastcgi_finish_request')) {
                // @codeCoverageIgnoreStart
                register_shutdown_function(function ($Session) {
                    $Session->save();
                }, $Session);
            // @codeCoverageIgnoreEnd
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
        if (!$this->__get('Handle')) {
            $this->__set('Handle',static::generateUniqueHandle());
        }

        // call parent
        parent::save($deep);

        // set cookie
        if (!headers_sent()) {
            // @codeCoverageIgnoreStart
            setcookie(
                static::$cookieName,
                $this->__get('Handle'),
                static::$cookieExpires ? (time() + static::$cookieExpires) : 0,
                static::$cookiePath,
                static::$cookieDomain,
                static::$cookieSecure
            );
            // @codeCoverageIgnoreEnd
        }
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
        if (!headers_sent()) {
            // @codeCoverageIgnoreStart
            setcookie(static::$cookieName, '', time() - 3600);
            // @codeCoverageIgnoreEnd
        }
        unset($_COOKIE[static::$cookieName]);

        $this->destroy();
    }

    /**
     * Makes a random 32 digit string by generating 16 random bytes
     * Is cryptographically secure.
     * @see http://php.net/manual/en/function.random-bytes.php
     *
     * @return string
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
