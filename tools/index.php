<?php

if (!class_exists('Hicurl', false)) {
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
    }

    if (!class_exists('Hicurl')) {
        echo 'Hicurl tools require Composer autoload. Include vendor/autoload.php before loading tools.';
        exit(1);
    }
}

require_once __DIR__ . '/PhpCodeGenerator.php';
require_once __DIR__ . '/CurlImporter.php';
require_once __DIR__ . '/TestController.php';

$controller = new TestController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $controller->handle($_POST);
    $requestSpec = $result['requestSpec'];
    $responseData = $result['result'];
    $generatedCode = $result['phpCode'];
    $includeCookies = $result['includeCookies'] ?? false;
    $curlInput = $_POST['curl_input'] ?? '';
} else {
    $requestSpec = $controller->getInitialRequestSpec();
    $responseData = null;
    $generatedCode = null;
    $includeCookies = $requestSpec['hasCookies'];
    $curlInput = '';
}

echo "<!DOCTYPE html>\n<html>\n<head>\n\t<meta charset=\"utf-8\">\n\t<title>Hicurl Tools</title>\n</head>\n<body>\n";

include __DIR__ . '/views/form.php';

if ($responseData !== null) {
    include __DIR__ . '/views/result.php';
}

if ($generatedCode !== null) {
    include __DIR__ . '/views/php_code.php';
}

echo "\n</body>\n</html>";
