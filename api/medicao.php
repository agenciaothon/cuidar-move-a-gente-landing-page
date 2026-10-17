<?php
declare(strict_types=1);
require_once __DIR__.'/metrics.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('X-Content-Type-Options: nosniff');
ini_set('display_errors','0');
function metricReply(int $status,array $data): never { http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit; }
try {
    if (($_SERVER['REQUEST_METHOD']??'')!=='POST') metricReply(405,['ok'=>false]);
    if (!in_array($_SERVER['HTTP_ORIGIN']??'', ['https://cuidarmoveagente.com.br','https://www.cuidarmoveagente.com.br'],true)) metricReply(403,['ok'=>false]);
    if (!str_starts_with(strtolower($_SERVER['CONTENT_TYPE']??''),'application/json') || (int)($_SERVER['CONTENT_LENGTH']??0)>2048) metricReply(400,['ok'=>false]);
    $raw=file_get_contents('php://input',false,null,0,2049);
    if ($raw===false || strlen($raw)>2048) metricReply(400,['ok'=>false]);
    $body=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
    if (!is_array($body)) metricReply(400,['ok'=>false]);
    $config=require dirname(__DIR__,2).'/cuidar-private/config.php';
    $event=metricsValidate($body,$config['rate_secret']);
    $db=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>3]);
    $now=new DateTimeImmutable('now',new DateTimeZone('UTC'));
    $bucket=hash_hmac('sha256','measurement|'.($_SERVER['REMOTE_ADDR']??'unknown').'|'.$now->format('Y-m-d-H'),$config['rate_secret']);
    $db->prepare('INSERT INTO campaign_metric_limits (bucket,attempts,created_at_utc) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=attempts+1')->execute([$bucket,metricsTimestamp($now)]);
    $q=$db->prepare('SELECT attempts FROM campaign_metric_limits WHERE bucket=?');$q->execute([$bucket]);
    if ((int)$q->fetchColumn()>4000) metricReply(429,['ok'=>false]);
    if (random_int(1,100)===1) $db->exec('DELETE FROM campaign_metric_limits WHERE created_at_utc < UTC_TIMESTAMP() - INTERVAL 2 DAY');
    metricsRecord($db,$event,$now);
    metricReply(200,['ok'=>true,'token'=>metricsToken($event['visit'],$config['rate_secret'])]);
} catch (InvalidArgumentException|JsonException $e) { metricReply(400,['ok'=>false]); }
catch (Throwable $e) { error_log('Campaign measurement unavailable: '.get_class($e));metricReply(503,['ok'=>false]); }
