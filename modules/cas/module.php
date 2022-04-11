<?php

$Module = ['name' => 'Cas Login'];

$ViewList = [];
$ViewList['login'] = [
    'functions' => ['login'],
    'script' => 'login.php',
    'params' => ['IDP'],
    'unordered_params' => [],
];
$ViewList['logout'] = [
    'functions' => ['login'],
    'script' => 'logout.php',
    'params' => [],
    'unordered_params' => [],
];

$FunctionList = [];
$FunctionList['login'] = [];
