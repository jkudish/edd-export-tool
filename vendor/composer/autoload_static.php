<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit3f64c53bd3ba055a5d7d5ef7bd54d683
{
    public static $prefixLengthsPsr4 = array (
        'E' => 
        array (
            'EDD\\ExtensionUtils\\' => 19,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'EDD\\ExtensionUtils\\' => 
        array (
            0 => __DIR__ . '/..' . '/easydigitaldownloads/edd-addon-tools/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit3f64c53bd3ba055a5d7d5ef7bd54d683::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit3f64c53bd3ba055a5d7d5ef7bd54d683::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit3f64c53bd3ba055a5d7d5ef7bd54d683::$classMap;

        }, null, ClassLoader::class);
    }
}
