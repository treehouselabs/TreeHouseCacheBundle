<?php

use Doctrine\Common\Annotations\AnnotationRegistry;

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->setPsr4('TreeHouse\\Getgeo\\Tests\\', __DIR__ . '/TreeHouse/Getgeo/Tests');
$loader->setPsr4('TreeHouse\\FunctionalTestBundle\\', __DIR__ . '/src/TreeHouse/FunctionalTestBundle');

AnnotationRegistry::registerLoader([$loader, 'loadClass']);
