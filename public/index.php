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
    $v = new Valitron\Validator($url);
    $v->rule('required', 'name');
    $v->rule('lengthMax', 'name', 255);
    $v->rule('url', 'name');

    if ($v->validate()) {
        $name = $url['name'];
        $dsn = "pgsql:host=localhost;port=5432;dbname=elisad5791";
        $db = new PDO($dsn, 'elisad5791', 'HigginS5791');
        $sql = "INSERT INTO urls(name, created_at) VALUES (?, NOW())";
        $query = $db->prepare($sql);
        $query->execute([$name]);
        $db = null;
    }

    return $response->withRedirect('/');
});

$app->get('/urls/{id}', function ($request, $response, array $args) {
    $id = $args['id'];

    $dsn = "pgsql:host=localhost;port=5432;dbname=elisad5791";
    $db = new PDO($dsn, 'elisad5791', 'HigginS5791');
    $sql = "SELECT * FROM urls WHERE id=?";
    $query = $db->prepare($sql);
    $result = $query->execute([$id]);
    $data = $query->fetch();
    $db = null;

    $params = ['url' => $data];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
});

$app->get('/urls', function ($request, $response) {
    $dsn = "pgsql:host=localhost;port=5432;dbname=elisad5791";
    $db = new PDO($dsn, 'elisad5791', 'HigginS5791');
    $sql = "SELECT * FROM urls";
    $query = $db->prepare($sql);
    $result = $query->execute();
    $data = $query->fetchAll();
    $db = null;

    $params = ['urls' => $data];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
});

$app->run();
