<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/core/app.php';
require_once __DIR__ . '/core/Controller.php';

spl_autoload_register(function ($className) {
    $paths = [
        __DIR__ . '/app/models/' . $className . '.php',
        __DIR__ . '/app/controller/Client/' . $className . '.php',
        __DIR__ . '/app/controller/admin/' . $className . '.php',
        __DIR__ . '/core/' . $className . '.php',
    ];

    foreach ($paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            return;
        }
    }
});

$app = new App();