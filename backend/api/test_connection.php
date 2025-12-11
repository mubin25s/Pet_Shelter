<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

echo json_encode([
    "success" => true, 
    "message" => "Backend is reachable!",
    "server_software" => $_SERVER['SERVER_SOFTWARE'],
    "php_version" => phpversion()
]);
?>
