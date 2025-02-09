<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';

use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;
use Brahmic\ClientDTO\Test\Provider\DataProvider;

ob_start();
// Создаем экземпляр Whoops
$whoops = new Run;

// Настраиваем обработчик для красивого вывода ошибок
$handler = new PrettyPageHandler;

$whoops->pushHandler($handler);

$whoops->register();


ob_clean();

new DataProvider();
