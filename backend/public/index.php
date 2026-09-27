<?php

declare(strict_types=1);

use Nuances\Facture\Auth\SessionAuth;
use Nuances\Facture\Domain\DocumentStatus;
use Nuances\Facture\Http\Request;
use Nuances\Facture\Http\Response;
use Nuances\Facture\Http\Router;
use Nuances\Facture\Repositories\ClientRepository;
use Nuances\Facture\Repositories\CompanyRepository;
use Nuances\Facture\Repositories\DocumentRepository;
use Nuances\Facture\Services\DocumentNumberService;
use Nuances\Facture\Services\PdfService;
use Nuances\Facture\Services\TotalsCalculator;

$app = require dirname(__DIR__) . '/bootstrap.php';
/** @var array $config */
$config = $app['config'];
/** @var PDO $pdo */
$pdo = $app['pdo'];
$root = $app['root'];

$auth = new SessionAuth($pdo);
$clients = new ClientRepository($pdo);
$numbers = new DocumentNumberService($pdo);
$company = new CompanyRepository($pdo, $config['paths']['logos'], $numbers);
$docs = new DocumentRepository($pdo, new TotalsCalculator(), $numbers);
$pdf = new PdfService($pdo, $root . '/templates/pdf', $config['paths']['logos']);

header('X-Content-Type-Options: nosniff');

$request = Request::fromGlobals();
$router = new Router();

$mutate = static function (Request $req) use ($auth): bool {
    if ($auth->requireUser() === null) {
        return false;
    }
    return $auth->requireCsrf($req);
};

$router->get('/health', static function () {
    Response::json(['ok' => true]);
});

$router->get('/setup/status', static function () use ($auth) {
    Response::json([
        'needs_setup' => $auth->userCount() === 0,
        'authenticated' => $auth->userId() !== null,
        'csrf_token' => $auth->ensureCsrf(),
    ]);
});

$router->post('/setup', static function (Request $req) use ($auth) {
    if ($auth->userCount() > 0) {
        Response::error('Installation déjà effectuée', 409);
        return;
    }
    $email = trim((string) $req->input('email', ''));
    $password = (string) $req->input('password', '');
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email invalide');
        return;
    }
    if (strlen($password) < 8) {
        Response::error('Mot de passe : 8 caractères minimum');
        return;
    }
    $id = $auth->createUser($email, $password);
    $auth->login($id);
    Response::json(['ok' => true, 'csrf_token' => $auth->ensureCsrf()], 201);
});

$router->post('/auth/login', static function (Request $req) use ($auth) {
    $email = trim((string) $req->input('email', ''));
    $password = (string) $req->input('password', '');
    $user = $auth->findByEmail($email);
    if ($user === null || !password_verify($password, (string) $user['password_hash'])) {
        Response::error('Identifiants incorrects', 401);
        return;
    }
    $auth->login((int) $user['id']);
    Response::json(['ok' => true, 'csrf_token' => $auth->ensureCsrf(), 'email' => $user['email']]);
});

$router->post('/auth/logout', static function (Request $req) use ($auth) {
    if (!$auth->requireCsrf($req)) {
        return;
    }
    $auth->logout();
    Response::json(['ok' => true]);
});

$router->get('/auth/me', static function () use ($auth, $pdo) {
    $id = $auth->requireUser();
    if ($id === null) {
        return;
    }
    $stmt = $pdo->prepare('SELECT id, email FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch();
    Response::json([
        'user' => $user,
        'csrf_token' => $auth->ensureCsrf(),
    ]);
});

$router->get('/company', static function () use ($auth, $company) {
    if ($auth->requireUser() === null) {
        return;
    }
    Response::json($company->get());
});

$router->put('/company', static function (Request $req) use ($mutate, $company) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $company->update($req->body);
        Response::json($company->get());
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->post('/company/logo', static function (Request $req) use ($mutate, $company) {
    if (!$mutate($req)) {
        return;
    }
    try {
        if (!isset($req->files['logo'])) {
            Response::error('Fichier logo manquant');
            return;
        }
        $company->saveLogo($req->files['logo']);
        Response::json($company->get());
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->get('/company/logo', static function () use ($auth, $company) {
    if ($auth->requireUser() === null) {
        return;
    }
    $path = $company->logoAbsolutePath();
    if ($path === null) {
        Response::error('Pas de logo', 404);
        return;
    }
    $mime = mime_content_type($path) ?: 'image/png';
    header('Content-Type: ' . $mime);
    header('Cache-Control: private, max-age=3600');
    readfile($path);
});

$router->get('/clients', static function (Request $req) use ($auth, $clients) {
    if ($auth->requireUser() === null) {
        return;
    }
    $q = isset($req->query['q']) ? (string) $req->query['q'] : null;
    Response::json(['items' => $clients->list($q)]);
});

$router->get('/clients/{id}', static function (Request $req, array $params) use ($auth, $clients) {
    if ($auth->requireUser() === null) {
        return;
    }
    $row = $clients->find((int) $params['id']);
    if ($row === null) {
        Response::error('Client introuvable', 404);
        return;
    }
    Response::json($row);
});

$router->post('/clients', static function (Request $req) use ($mutate, $clients) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $id = $clients->create($req->body);
        Response::json($clients->find($id), 201);
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->put('/clients/{id}', static function (Request $req, array $params) use ($mutate, $clients) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $clients->update((int) $params['id'], $req->body);
        Response::json($clients->find((int) $params['id']));
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->delete('/clients/{id}', static function (Request $req, array $params) use ($mutate, $clients) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $clients->delete((int) $params['id']);
        Response::noContent();
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->get('/documents', static function (Request $req) use ($auth, $docs) {
    if ($auth->requireUser() === null) {
        return;
    }
    $type = isset($req->query['type']) ? (string) $req->query['type'] : null;
    Response::json(['items' => $docs->list($type)]);
});

$router->get('/documents/{id}', static function (Request $req, array $params) use ($auth, $docs) {
    if ($auth->requireUser() === null) {
        return;
    }
    $doc = $docs->find((int) $params['id']);
    if ($doc === null) {
        Response::error('Document introuvable', 404);
        return;
    }
    Response::json($doc);
});

$router->post('/documents', static function (Request $req) use ($mutate, $docs) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $id = $docs->create($req->body);
        Response::json($docs->find($id), 201);
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->put('/documents/{id}', static function (Request $req, array $params) use ($mutate, $docs) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $docs->update((int) $params['id'], $req->body);
        Response::json($docs->find((int) $params['id']));
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->delete('/documents/{id}', static function (Request $req, array $params) use ($mutate, $docs) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $docs->delete((int) $params['id']);
        Response::noContent();
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->post('/documents/{id}/send', static function (Request $req, array $params) use ($mutate, $docs) {
    if (!$mutate($req)) {
        return;
    }
    try {
        Response::json($docs->send((int) $params['id']));
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->post('/documents/{id}/status', static function (Request $req, array $params) use ($mutate, $docs) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $status = DocumentStatus::from((string) $req->input('status', ''));
        Response::json($docs->setQuoteStatus((int) $params['id'], $status));
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->post('/documents/{id}/convert', static function (Request $req, array $params) use ($mutate, $docs) {
    if (!$mutate($req)) {
        return;
    }
    try {
        $invoiceId = $docs->convertToInvoice((int) $params['id']);
        Response::json($docs->find($invoiceId), 201);
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->get('/documents/{id}/pdf', static function (Request $req, array $params) use ($auth, $docs, $pdf) {
    if ($auth->requireUser() === null) {
        return;
    }
    try {
        $doc = $docs->find((int) $params['id']);
        if ($doc === null) {
            Response::error('Document introuvable', 404);
            return;
        }
        $binary = $pdf->renderDocument((int) $params['id']);
        $base = $doc['number'] ?: ('brouillon-' . $doc['id']);
        $filename = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $base) . '.pdf';
        Response::pdf($binary, $filename);
    } catch (Throwable $e) {
        Response::error($e->getMessage(), 400);
    }
});

try {
    $router->dispatch($request);
} catch (Throwable $e) {
    $debug = (bool) ($config['app']['debug'] ?? false);
    Response::error($debug ? $e->getMessage() : 'Erreur serveur', 500);
}
