<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;
use Slim\Psr7\Response;

$container = new Container();
use Slim\Flash\Messages;

$container->set('flash', function() {
    return new Messages();
});

$container->set('renderer', function () {
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});

$container->set(PDO::class, function () {
    $conn = new PDO('sqlite:' . __DIR__ . '/../database.sqlite');
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $initFilePath = __DIR__ . '/../init.sql';
    if (file_exists($initFilePath)) {
        $initSql = file_get_contents($initFilePath);
        $conn->exec($initSql);
    }

    return $conn;
});

use App\CarRepository;

$container->set(CarRepository::class, function () use ($container) {
    return new CarRepository($container->get(PDO::class));
});

$app = AppFactory::createFromContainer($container);
$router = $app->getRouteCollector()->getRouteParser();
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$users = ['mike', 'mishel', 'adel', 'keks', 'kamila'];

$app->post('/logout', function ($request, $response) {
    session_destroy();
    return $response
        ->withHeader('Location', '/')
        ->withStatus(302);
});

$app->get('/', function ($request, $response) {
    if (empty($_SESSION['email'])) {
        $html = '<h1>Login</h1>
            <form method="POST" action="/">
                <input type="email" name="email" required />
                <button type="submit" name="action" value="login">Login</button>
            </form>';
    } else {
        $html = '<h1>Hello, ' . htmlspecialchars($_SESSION["email"]) . '</h1>
            <form method="POST" action="/logout">
                <button type="submit">Logout</button>
            </form>';
    }
    $response->getBody()->write($html);
    return $response;
})->setName('home');

// Обработка логина (POST /)
$app->post('/', function ($request, $response) {
    $data = $request->getParsedBody();
    if (!empty($data['action']) && $data['action'] === 'login' && !empty($data['email'])) {
        $_SESSION['email'] = $data['email'];
    }
    return $response
        ->withHeader('Location', '/')
        ->withStatus(302);
});

function getCurrentUser()
{
    $cookieUserId = $_COOKIE['user_id'] ?? null;
    if (!$cookieUserId) {
        return null;
    }

    $filePath = __DIR__ . '/../data/users.json';
    if (!file_exists($filePath)) {
        return null;
    }

    $users = json_decode(file_get_contents($filePath), true);
    foreach ($users as $user) {
        if ($user['id'] === $cookieUserId) {
            return $user;
        }
    }

    return null;
}

$app->get('/users/new', function ($request, $response) {
    return $this->get('renderer')->render($response, 'users/new.phtml');
})->setName('users.new');

$app->post('/users', function ($request, $response) use ($router) {
    $data = $request->getParsedBody();

    $user = $data['user'] ?? [];

    $errors = [];

    if (empty(trim($user['nickname'] ?? ''))) {
        $errors['nickname'] = 'Никнейм обязателен';
    }

    if (empty(trim($user['email'] ?? ''))) {
        $errors['email'] = 'Email обязателен';
    }

    if (!empty($errors)) {
        $params = [
            'user' => $user,
            'errors' => $errors
        ];
        return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
    }

    $userData = [
        'id' => uniqid(),
        'user' => $user
    ];

    $filePath = __DIR__ . '/../data/users.json';
    $users = [];

    if (file_exists($filePath)) {
        $users = json_decode(file_get_contents($filePath), true);
    }

    $users[] = $userData;
    file_put_contents($filePath, json_encode($users, JSON_PRETTY_PRINT));

$cookieHeader = sprintf('user_id=%s; Path=/; HttpOnly', $userData['id']);

$this->get('flash')->addMessage('success', 'Пользователь успешно создан и вошел в систему!');

return $response
        ->withHeader('Set-Cookie', $cookieHeader)
        ->withHeader('Location', $router->urlFor('users.index'))
        ->withStatus(302);

})->setName('users.store');

$app->delete('/users/{id}', function ($request, $response, array $args) {
    $id = $args['id'];

    // Авторизация (пример: только если ты админ)
    $currentUser = $_SESSION['user'] ?? null;
    if (!$currentUser || !$currentUser['is_admin']) {
        return $response->withStatus(403)->write('Access denied');
    }

    $repo = new App\UserRepository();
    $repo->destroy($id);

    $this->get('flash')->addMessage('success', 'User has been deleted');
return $response->withHeader('Location', '/users')->withStatus(302);
});
$app->get('/users/{id}', function ($request, $response, $args) {
    $id = $args['id'];
    $filePath = __DIR__ . '/../data/users.json';

    if (!file_exists($filePath)) {
        $response->getBody()->write('⚠️ Файл с пользователями не найден.');
        return $response->withStatus(500);
    }

    $users = json_decode(file_get_contents($filePath), true);
    $foundUser = null;

    foreach ($users as $user) {
        if ($user['id'] === $id) {
            $foundUser = $user;
            break;
        }
    }

    if (!$foundUser) {
        $response->getBody()->write("<h2>❌ Пользователь с ID {$id} не найден</h2>");
        return $response->withStatus(404);
    }

    $params = [
        'id' => $foundUser['id'],
        'nickname' => $foundUser['user']['nickname'],
        'email' => $foundUser['user']['email'] ?? '(не указан)'
    ];

    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('users.show');

$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $repo = new App\UserRepository();
    $id = $args['id'];
    $user = $repo->find($id);  // Найти пользователя по ID

    $flash = $this->get('flash')->getMessages();
    
    $params = [
        'user' => $user,
        'errors' => [],
	'flash' => $flash,
    ];
    
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('user.edit');

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router) {
    $repo = new App\UserRepository();
    $id = $args['id'];
    $user = $repo->find($id);
    $data = $request->getParsedBodyParam('user');

    // Валидация (пример простого валидатора)
    $errors = [];
    if (empty($data['name'])) {
        $errors['name'] = "Имя не может быть пустым";
    }
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Введите корректный email";
    }

    if (!empty($errors)) {
        // Ошибки: показываем форму с ошибками и данными пользователя
        $params = [
            'user' => array_merge($user, $data),
            'errors' => $errors
        ];
        $response = $response->withStatus(422);
        return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
    }

    // Обновляем данные пользователя вручную, чтобы избежать изменения нежелательных полей
    $user['name'] = $data['name'];
    $user['email'] = $data['email'];

    // Сохраняем пользователя
    $repo->save($user);

    // Добавляем flash сообщение
    $this->get('flash')->addMessage('success', 'Пользователь успешно обновлён');

    // Перенаправляем обратно на страницу редактирования или на список пользователей
    $url = $router->urlFor('user.edit', ['id' => $user['id']]);
    return $response->withRedirect($url);
});

$app->get('/users', function ($request, $response) {
    $term = $request->getQueryParam('term');
    $filePath = __DIR__ . '/../data/users.json';

    $users = [];
    if (file_exists($filePath)) {
        $users = json_decode(file_get_contents($filePath), true);
    }

    // Отфильтровать пользователей по нику, если задан term
    if ($term) {
        $users = array_filter($users, function ($user) use ($term) {
            return str_contains(strtolower($user['user']['nickname']), strtolower($term));
        });
    }

    $flashMessages = $this->get('flash')->getMessages();

    $params = [
        'users' => $users,
        'term' => $term,
        'flash' => $flashMessages
    ];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users.index');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('courses.show');

$app->get('/debug-db', function ($request, $response) {
    $pdo = $this->get(PDO::class);
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll();

    $output = "Список таблиц в базе данных:<br><ul>";
    foreach ($tables as $table) {
        $output .= "<li>" . htmlspecialchars($table['name']) . "</li>";
    }
    $output .= "</ul>";

    $response->getBody()->write($output);
    return $response;
});

$app->get('/cars', function ($request, $response) use ($container) {
    /** @var CarRepository $repo */
    $repo = $container->get(CarRepository::class);
    $cars = $repo->all();

    // Можно отрендерить шаблон или просто вывести список:
    $output = "<h1>Список машин</h1><ul>";
    foreach ($cars as $car) {
        $output .= "<li>" . htmlspecialchars($car->getMake()) . " " . htmlspecialchars($car->getModel()) . "</li>";
    }
    $output .= "</ul>";

    $response->getBody()->write($output);
    return $response;
});

$app->run();
