<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Carbon\Carbon;
use GuzzleHttp\Client;
use DiDom\Document;

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
        /*$this->get('flash')->addMessage('error', 'Некорректный URL');*/
        $response = $response->withStatus(422);
        return $this->get('renderer')->render($response, 'index.phtml', $params);
        /*$route = $router->urlFor('urls');
        $this->get('flash')->addMessage('error', 'Некорректный URL');
        return $response->withRedirect($route)->withStatus(422);*/
    }


    $sqlSelect = "SELECT id FROM urls WHERE name=?";
    $query = $db->prepare($sqlSelect);
    $query->execute([$name]);
    $count = $query->rowCount();
    if ($count !== 0) {
        $id = $query->fetchColumn();
        $route = $router->urlFor('url', ['id' => $id]);
        $this->get('flash')->addMessage('error', 'Страница уже существует');
        return $response->withRedirect($route);
    }

    $date = Carbon::now();
    $sqlInsert = "INSERT INTO urls(name, created_at) VALUES (?, ?)";
    $query = $db->prepare($sqlInsert);
    $query->execute([$name, $date]);

    $query = $db->prepare($sqlSelect);
    $query->execute([$name]);
    $id = $query->fetchColumn();
    $route = $router->urlFor('url', ['id' => $id]);
    $this->get('flash')->addMessage('success', 'Страница успешно добавлена');
    return $response->withRedirect($route);
});

$app->get('/urls', function ($request, $response) use ($db) {
    $sql = "SELECT id, name FROM urls";
    $query = $db->prepare($sql);
    $result = $query->execute();
    $data = $query->fetchAll();

    $urls = [];
    foreach ($data as $row) {
        $id = $row['id'];
        $name = $row['name'];
        $sql = "SELECT status_code, created_at FROM url_checks WHERE url_id=? ORDER BY created_at DESC LIMIT 1";
        $query = $db->prepare($sql);
        $result = $query->execute([$id]);
        $checkData = $query->fetch();
        $status_code = $checkData['status_code'];
        $created_at = $checkData['created_at'];
        $urls[] = compact('id', 'name', 'created_at', 'status_code');
    }

    $messages = $this->get('flash')->getMessages();
    $params = compact('urls', 'messages');
    return $this->get('renderer')->render($response, 'urls.phtml', $params);
})->setName('urls');

$app->get('/urls/{id}', function ($request, $response, array $args) use ($db) {
    $id = $args['id'];
    $messages = $this->get('flash')->getMessages();

    $sql = "SELECT * FROM urls WHERE id=?";
    $query = $db->prepare($sql);
    $result = $query->execute([$id]);
    $urlData = $query->fetch();

    $sql = "SELECT id, status_code, h1, title, description, created_at FROM url_checks WHERE url_id=?";
    $query = $db->prepare($sql);
    $result = $query->execute([$id]);
    $checksData = $query->fetchAll();

    $params = ['url' => $urlData, 'checks' => $checksData, 'messages' => $messages];
    return $this->get('renderer')->render($response, 'show.phtml', $params);
})->setName('url');

$app->post('/urls/{url_id}/checks', function ($request, $response, array $args) use ($router, $db) {
    $url_id = $args['url_id'];
    $date = Carbon::now();

    $sql = "SELECT name FROM urls WHERE id=?";
    $query = $db->prepare($sql);
    $result = $query->execute([$url_id]);
    $url = $query->fetchColumn();

    $client = new Client();
    $res = $client->request('GET', $url);
    $code = $res->getStatusCode();

    $document = new Document($url, true);
    $elem = $document->first('h1');
    $h1 = $elem ? $elem->text() : '';
    $elem = $document->first('title');
    $title = $elem ? $elem->text() : '';

    $description = $document->has('meta[name=description]')
        ? $document->first('meta[name=description]')->getAttribute('content')
        : '';

    $sql = "INSERT INTO url_checks(url_id, status_code, h1, title, description, created_at) VALUES (?, ?, ?, ?, ?, ?)";
    $query = $db->prepare($sql);
    $query->execute([$url_id, $code, $h1, $title, $description, $date]);

    $route = $router->urlFor('url', ['id' => $url_id]);
    $this->get('flash')->addMessage('success', 'Страница успешно проверена');
    return $response->withRedirect($route);
});

$app->run();
$db = null;
