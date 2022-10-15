<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $params = [];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
});

$app->post('/', function ($request, $response) {
    $url = $request->getParsedBodyParam('url');
    $name = $url['name'];
    $dsn = "pgsql:host=localhost;port=5432;dbname=elisad5791";
    $db = new PDO($dsn, 'elisad5791', 'HigginS5791');
    $sql = "INSERT INTO urls(name, created_at) VALUES (?, NOW())";
    $query = $db->prepare($sql, [PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY]);
    $query->execute([$name]);
    return $response->withRedirect('/');
});

$app->run();
