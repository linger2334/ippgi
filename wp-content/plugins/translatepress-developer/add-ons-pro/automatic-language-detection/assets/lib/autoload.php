<?php

/**
 * PSR-4 autoloader implementation for the MaxMind\DB namespace.
 * First we define the 'mmdb_autoload' function, and then we register
 * it with 'spl_autoload_register' so that PHP knows to use it.
 */

/**
 * Automatically include the file that defines <code>class</code>.
 *
 * @param string $class
 *     the name of the class to load
 *
 * @return void
 */
function trp_mmdb_autoload($class)
{
    /*
    * A project-specific mapping between the namespaces and where
    * they're located. By convention, we include the trailing
    * slashes. The one-element array here simply makes things easy
    * to extend in the future if (for example) the test classes
    * begin to use one another.
    */
    $namespace_map = array('TP_MaxMind\\Db\\' => __DIR__ . '/TP_MaxMind/Db/');

    foreach ($namespace_map as $prefix => $dir)
    {
        /* Check if the class uses this namespace prefix */
        $prefix_length = strlen($prefix);
        if (strncmp($prefix, $class, $prefix_length) !== 0) {
            /* Class doesn't belong to this namespace, skip it */
            continue;
        }

        /* Get the relative class name (without the prefix) */
        $relative_class = substr($class, $prefix_length);

        /* Build the path: directory + relative class name + .php */
        $path = $dir . str_replace('\\', '/', $relative_class) . '.php';

        /* $path should now contain the path to a PHP file defining $class */
        if (file_exists($path)) {
            include $path;
        }

        return;
    }
}

spl_autoload_register('trp_mmdb_autoload');
