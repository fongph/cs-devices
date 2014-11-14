<?php

require 'vendor/autoload.php';

date_default_timezone_set('UTC');

$config = array(
    'db' => array(
        'host' => 'localhost',
        'dbname' => 'main',
        'username' => 'root',
        'password' => 'password',
        'charset' => 'utf8',
        'options' => array()
    )
);

if (is_file('tests/config.php')) {
    include 'tests/config.php';
}

try {
    $db = new PDO(
        'mysql:host=' . $config['db']['host'] . ';dbname=' . $config['db']['dbname'] . ';charset=' . $config['db']['charset'],
        $config['db']['username'],
        $config['db']['password'],
        $config['db']['options']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage() . PHP_EOL);
}

$db->beginTransaction();

\CS\Models\TestHelper::create($db);