<?php
namespace Divergence\Helpers;

use Divergence\App as App;

class Debug
{
    public static $log = [];
    
    public static function logMessage($message, $source = null)
    {
        return static::log(['message' => $message], $source);
    }
    
    public static function log($entry, $source = null)
    {
        if (App::$Config['environment']!='dev') {
            return;
        }
    
        static::$log[] = array_merge($entry, [
            'source' => isset($source) ? $source : static::_detectSource()
            ,'time' => sprintf('%f', microtime(true)),
        ]);
    }
    
    public static function showLog()
    {
        echo '<table><tr><td>#</td><td>Duration (ms)</td><td>Query</td><td>Method</td></tr>';
        foreach (static::$log as $LogItem) {
            $i++;
            echo "<tr><td>$i</td><td>{$LogItem['time_duration_ms']}</td><td>{$LogItem['query']}</td><td>{$LogItem['method']}</td></tr>";
        }
        echo '</table>';
    }
    
    protected static function _detectSource()
    {
        $backtrace = debug_backtrace();
        
        while ($trace = array_shift($backtrace)) {
            if (!empty($trace['class'])) {
                if ($trace['class'] == __CLASS__) {
                    continue;
                }
                return $trace['class'];
            } elseif (!empty($trace['file'])) {
                return basename($trace['file']);
            }
        }

        return basename($_SERVER['SCRIPT_NAME']);
    }
}
