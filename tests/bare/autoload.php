<?php

// Run by omnisense/omnibase's docker/bin/bare before the classes (a script, sorted first): the
// classes loaded are this checkout's, whichever application's autoloader bare was given (the
// core's harness does not install this bundle). Nothing else is loaded here.
foreach (Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
    $loader->addPsr4('Base\\Consent\\', dirname(__DIR__, 2).'/src', true);
}

return 'Base\\Consent\\ in '.realpath(dirname(__DIR__, 2).'/src');
