<?php

require_once __DIR__ . '/../vendor/autoload.php';

$router = new \Thruway\Peer\Router();

$router->registerModule(
    new \RFCThruwayTransport\RFC6455RouterTransportProvider()
);

$router->start();