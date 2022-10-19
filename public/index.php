<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Carbon\Carbon;

session_start();

if (isset($_ENV['DATABASE_URL'])) {
    $databaseUrl = parse_url($_ENV['DATABASE_URL']);
    $username = $databaseUrl['user'];
    $password = $databaseUrl['pass'];
    $host = $databaseUrl['host'];
    $port = $databaseUrl['port'];
    $dbname = ltrim($databaseUrl['path'], '/');
} else {
    $username = 'elisad5791';
    $password = 'HigginS5791';
    $host = 'localhost';
    $port = '5432';
    $dbname = 'elisad5791';
}
$dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
$db = new PDO($dsn, $username, $password);

$container = new Container();
$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});
$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$router = $app->getRouteCollector()->getRouteParser();

$app->get('/', function ($request, $response) {
    $messages = $this->get('flash')->getMessages();
    $params = ['valid' => true, 'name' => '', 'messages' => $messages];
    return $this->get('renderer')->render($response, 'index.phtml', $params);
})->setName('root');


$app->post('/', function ($request, $response) use ($router, $db) {
    $url = $request->getParsedBodyParam('url');
    $name = $url['name'];

    $v = new Valitron\Validator($url);
    $v->rule('required', 'name');
    $v->rule('lengthMax', 'name', 255);
    $v->rule('url', 'name');
    if (!$v->validate()) {
        $params = ['valid' => false, 'name' => $name];
        return $this->get('renderer')->render($response, 'index.phtml', $params);
    }

    $route = $router->urlFor('root');

    $sql = "SELECT * FROM urls WHERE name=?";
    $query = $db->prepare($sql);
    $query->execute([$name]);
    $count = $query->rowCount();
    if ($count !== 0) {
        $db = null;
        $this->get('flash')->addMessage('error', 'Сайт уже добавлен');
        return $response->withRedirect($route);
    }

    $date = Carbon::now();
    $sql = "INSERT INTO urls(name, created_at) VALUES (?, ?)";
    $query = $db->prepare($sql);
    $query->execute([$name, $date]);

    $this->get('flash')->addMessage('success', 'Сайт добавлен');
    return $response->withRedirect($route);
});

$app->get('/urls/{id}', function ($request, $response, array $args) use ($db) {
    $id = $args['id'];

    $sql = "SELECT * FROM urls WHERE id=?";
    $query = $db->prepare($sql);
    $result = $query->execute([$id]);
    $data = $query->fetch();
    $db = null;

    $params = ['url' => $data];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
});

$app->get('/urls', function ($request, $response) use ($db) {
    $sql = "SELECT * FROM urls";
    $query = $db->prepare($sql);
    $result = $query->execute();
    $data = $query->fetchAll();
    $db = null;

    $params = ['urls' => $data];
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
});

$app->run();
$db = null;
