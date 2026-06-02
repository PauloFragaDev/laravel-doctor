<?php

declare(strict_types=1);

/**
 * Front controller para el servidor embebido (`php -S host:port router.php`) que sirve el
 * dashboard de laravel-doctor. El directorio base llega por la variable de entorno
 * LARAVEL_DOCTOR_BASE (la fija ServeCommand).
 */

use LaravelDoctor\Web\WebController;

foreach ([__DIR__ . '/../../../../autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $autoload) {
    if (file_exists($autoload)) {
        require $autoload;
        break;
    }
}

$base = getenv('LARAVEL_DOCTOR_BASE') ?: getcwd();
$controller = new WebController((string) $base);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$json = static function (array $data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
};

switch ($path) {
    case '/':
        header('Content-Type: text/html; charset=utf-8');
        echo file_get_contents(__DIR__ . '/dashboard.html');
        break;

    case '/api/projects':
        $json($controller->projects());
        break;

    case '/api/inspect':
        $project = (string) ($_GET['project'] ?? '');
        $boot = ($_GET['boot'] ?? '0') === '1';
        $json($controller->inspect($project, $boot));
        break;

    default:
        http_response_code(404);
        $json(['error' => 'Not found']);
}
