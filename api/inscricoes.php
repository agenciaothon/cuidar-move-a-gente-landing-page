<?php
declare(strict_types=1);
require_once __DIR__ . '/campaign.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
ini_set('display_errors', '0');

function reply(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}
try {
    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    if (!in_array($method, ['GET','POST'], true)) { header('Allow: GET, POST'); reply(405, ['message'=>'Método não permitido.']); }
    // Stored outside public_html; never committed to Git.
    $configFile = dirname(__DIR__, 2) . '/cuidar-private/config.php';
    if (!is_file($configFile)) reply(503, ['open'=>false, 'message'=>'As inscrições estão sendo preparadas. Tente novamente em breve.']);
    $config = require $configFile;
    if (($config['registration_enabled'] ?? false) !== true) reply(503, ['open'=>false, 'message'=>'As inscrições estão sendo preparadas. Tente novamente em breve.']);
    if (empty($config['rate_secret']) || strlen($config['rate_secret']) < 32 || empty($config['privacy_retention_approved'])) throw new RuntimeException('Configuration not ready');
    $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    if (!campaignIsOpen($now)) reply(410, ['open'=>false, 'message'=>'Inscrições de 08/10 a 08/11/2026, até as 23h59 no horário de Três Lagoas/MS.']);
    $db = new PDO($config['dsn'], $config['user'], $config['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_TIMEOUT=>5]);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') throw new RuntimeException('MySQL required');
    $db->query('SELECT id FROM participants LIMIT 0');
    $db->query('SELECT bucket FROM registration_limits LIMIT 0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('cuidar_session');
    session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Strict']);
    session_start();
    if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    $csrf = $_SESSION['csrf'];
    session_write_close();
    if ($method === 'GET') reply(200, ['open'=>true, 'csrf'=>$csrf]);
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (!in_array($origin, ['https://cuidarmoveagente.com.br', 'https://www.cuidarmoveagente.com.br'], true)
        || !hash_equals($csrf, $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) reply(403, ['message'=>'Sua sessão expirou. Tente enviar novamente.']);
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) reply(415, ['message'=>'Formato de envio inválido.']);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 8192) reply(413, ['message'=>'Envio excede o tamanho permitido.']);
    $body = file_get_contents('php://input', false, null, 0, 8193);
    if ($body === false || strlen($body) > 8192) reply(413, ['message'=>'Envio excede o tamanho permitido.']);
    $bucket = hash_hmac('sha256', ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . $now->format('Y-m-d-H'), $config['rate_secret']);
    $rate = $db->prepare('INSERT INTO registration_limits (bucket, attempts, created_at_utc) VALUES (?, 1, ?) ON DUPLICATE KEY UPDATE attempts = attempts + 1');
    $rate->execute([$bucket, $now->format('Y-m-d H:i:s')]);
    $count = $db->prepare('SELECT attempts FROM registration_limits WHERE bucket = ?'); $count->execute([$bucket]);
    if ((int)$count->fetchColumn() > 120) { header('Retry-After: 3600'); reply(429, ['message'=>'Muitos envios nesta conexão. Tente mais tarde ou fale com a Trok.']); }
    $db->exec('DELETE FROM registration_limits WHERE created_at_utc < UTC_TIMESTAMP() - INTERVAL 1 DAY');
    try { $input = json_decode($body, true, 16, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { reply(400, ['message'=>'Não foi possível ler os dados enviados.']); }
    if (!is_array($input)) reply(400, ['message'=>'Envio inválido.']);
    try { $data = campaignValidate($input); }
    catch (InvalidArgumentException $e) { reply(422, ['message'=>$e->getMessage()]); }
    campaignRegister($db, $data, $now);
    reply(200, ['ok'=>true]);
} catch (DomainException $e) {
    reply(410, ['message'=>$e->getMessage()]);
} catch (Throwable $e) {
    error_log('Cuidar registration unavailable: ' . get_class($e));
    reply(503, ['open'=>false, 'message'=>'Não foi possível salvar agora. Tente novamente em alguns minutos.']);
}
