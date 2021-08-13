<?php


require "vendor/autoload.php";

use Inbenta\WatsonConnector\WatsonConnector;

header('Content-Type: application/json');

// Instance new Connector
$appPath = __DIR__ . '/';

$app = new WatsonConnector($appPath);
$inbentaResponse = $app->handleRequest();
if (isset($inbentaResponse)) {
	echo json_encode($inbentaResponse);
}
