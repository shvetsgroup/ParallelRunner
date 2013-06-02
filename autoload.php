<?php
/**
 * @copyright 2013 Alexander Shvets
 * @license MIT
 */

namespace shvetsgroup;

/**
 * Generic autoloader.
 *
 * @author Alexander Shvets <neochief@shvetsgroup.com>
 */
class Bootstrap
{
    /**
     * Load class
     *
     * @param string $class Class name
     */
    public static function autoload($class)
    {
        $file = str_replace(array('\\', '_'), '/', $class);
        $path = __DIR__ . '/src/' . $file . '.php';

        if (file_exists($path)) {
            include_once $path;
        }
    }
}

spl_autoload_register('shvetsgroup\Bootstrap::autoload');