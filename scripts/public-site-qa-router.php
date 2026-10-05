<?php

// Local acceptance harness: public site + the existing isolated MySQL QA app.
// Production uses backend/public/index.php directly and never this proxy.
if (! in_array($_SERVER['SERVER_NAME'] ?? '', ['127.0.0.1', 'localhost'], true)) {
    http_response_code(403);
    exit;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$static = realpath(__DIR__.'/../backend/public'.$path);
$public = realpath(__DIR__.'/../backend/public');
if ($static && str_starts_with($static, $public.DIRECTORY_SEPARATOR) && is_file($static) && ! str_ends_with($static, '.php') && ! str_ends_with($static, 'robots.txt')) {
    return false;
}
$finance = require __DIR__.'/../backend/bootstrap/application-path.php';
if ($finance(rawurldecode($path))) {
    $curl = curl_init('http://127.0.0.1:8001'.$_SERVER['REQUEST_URI']);
    $headers = [];
    foreach (getallheaders() as $key => $value) {
        if (! in_array(strtolower($key), ['host', 'content-length', 'connection'], true)) {
            $headers[] = $key.': '.$value;
        }
    }
    curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $_SERVER['REQUEST_METHOD'], CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH', 'DELETE']) ? file_get_contents('php://input') : null,
        CURLOPT_HEADERFUNCTION => function ($curl, string $line): int {
            if (preg_match('~^HTTP/\S+ (\d+)~', $line, $match)) {
                http_response_code((int) $match[1]);
            } elseif (str_contains($line, ':')) {
                $name = strtolower(explode(':', $line, 2)[0]);
                if (! in_array($name, ['content-length', 'transfer-encoding', 'connection'], true)) {
                    header(trim(str_replace('http://127.0.0.1:8001', 'http://127.0.0.1:8090', $line)), false);
                }
            }
            return strlen($line);
        }, CURLOPT_TIMEOUT => 30]);
    $body = curl_exec($curl);
    if ($body === false) { http_response_code(502); echo 'Local financial QA service unavailable'; } else { echo $body; }
    return true;
}
require __DIR__.'/../public-site/public/index.php';
