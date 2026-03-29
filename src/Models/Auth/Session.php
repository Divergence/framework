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

use Divergence\IO\Database\Connections;
use Divergence\IO\Database\PostgreSQL as PostgreSQLStorage;
use Divergence\IO\Database\SQLite as SQLiteStorage;
use Divergence\Models\Model;
use Divergence\Models\Relations;
use Divergence\Models\Mapping\Column;
use Throwable;

/**
 * Session object
 *
 * @author Henry Paradiz <henry.paradiz@gmail.com>
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

    // ActiveRecord configuration
    public static $tableName = 'sessions';
    public static $indexes = [
        'SESSION_HANDLE' => [
            'unique' => true,
            'fields' => ['Handle'],
        ],
    ];

    #[Column(default:null)]
    private ?string $ContextClass;

    #[Column(type:'int', default:null)]
    private ?int $ContextID;

    #[Column(length:32)]
    private string $Handle;

    #[Column(type:'timestamp')]
    private ?string $LastRequest;

    #[Column(type:'binary', length:16)]
    private $LastIP;

    public function getValue($name)
    {
        if ($name === 'LastIP') {
            $value = parent::getValue($name);

            if ($value !== null && static::isUsingSQLite()) {
                return ctype_xdigit($value) && strlen($value) % 2 === 0 ? hex2bin($value) : $value;
            }

            if ($value !== null && static::isUsingPostgreSQL()) {
                return str_starts_with($value, '\\x') ? hex2bin(substr($value, 2)) : $value;
            }

            return $value;
        }

        return parent::getValue($name);
    }

    public function setValue($name, $value)
    {
        $value = $this->normalizeValueForStorage($name, $value);

        return parent::setValue($name, $value);
    }

    public function setField($field, $value)
    {
        parent::setField($field, $this->normalizeValueForStorage($field, $value));
    }

    public function setFields($values)
    {
        foreach ($values as $field => $value) {
            $values[$field] = $this->normalizeValueForStorage($field, $value);
        }

        parent::setFields($values);
    }

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
        if (!$this->getValue('Handle')) {
            $this->setValue('Handle', static::generateUniqueHandle());
        }

        // call parent
        parent::save($deep);

        // set cookie
        if (!headers_sent()) {
            // @codeCoverageIgnoreStart
            setcookie(
                static::$cookieName,
                $this->getValue('Handle'),
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

    protected static function isUsingSQLite(): bool
    {
        try {
            return Connections::getConnectionType() === SQLiteStorage::class;
        } catch (Throwable $e) {
            return false;
        }
    }

    protected static function isUsingPostgreSQL(): bool
    {
        try {
            return Connections::getConnectionType() === PostgreSQLStorage::class;
        } catch (Throwable $e) {
            return false;
        }
    }

    protected function normalizeValueForStorage(string $field, $value)
    {
        if ($field === 'LastIP' && $value !== null && static::isUsingSQLite()) {
            return bin2hex($value);
        }

        return $value;
    }
}
