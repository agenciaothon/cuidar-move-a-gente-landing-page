<?php
declare(strict_types=1);
require_once __DIR__ . '/campaign.php';

const METRICS_FIELDS = ['', 'name', 'cpf', 'phone', 'email', 'city', 'state', 'partner', 'adult', 'terms'];
const METRICS_STAGES = ['dados', 'estabelecimento', 'declaracoes', 'envio', 'confirmacao'];
const METRICS_SOURCES = ['direto', 'qr-legado', 'cartaz', 'instagram', 'facebook', 'whatsapp', 'parceiro', 'outro'];
const METRICS_CODES = ['required', 'name_invalid', 'cpf_invalid', 'phone_invalid', 'availability_failed', 'session_expired', 'timeout', 'network', 'invalid_response', 'server_rejected', 'rate_limited', 'service_unavailable', 'campaign_closed', 'unknown'];

function metricsId(mixed $value): bool { return is_string($value) && preg_match('/^[a-f0-9]{32}$/D', $value) === 1; }
function metricsToken(string $id, string $secret): string { return hash_hmac('sha256', 'campaign-metrics-v1|' . $id, $secret); }
function metricsContext(mixed $context, string $secret): ?array {
    if (!is_array($context) || !metricsId($context['visit'] ?? null) || !metricsId($context['attempt'] ?? null)
        || !is_string($context['token'] ?? null) || !hash_equals(metricsToken($context['visit'], $secret), $context['token'])) return null;
    return ['visit'=>$context['visit'], 'attempt'=>$context['attempt']];
}
function metricsTimestamp(DateTimeImmutable $now): string { return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'); }
function metricsStage(string $field): string {
    return $field === 'partner' ? 'estabelecimento' : (in_array($field, ['adult','terms'], true) ? 'declaracoes' : 'dados');
}
function metricsValidate(array $body, string $secret): array {
    $allowed = ['page_view','form_start','progress','submit_attempt','validation_error','request_error','success_view','leave'];
    $event = $body['event'] ?? '';
    if (!is_string($event) || !in_array($event, $allowed, true) || !metricsId($body['visit'] ?? null) || !metricsId($body['event_id'] ?? null)) throw new InvalidArgumentException('Invalid event');
    $field = $body['field'] ?? ''; $code = $body['code'] ?? 'unknown';
    if (!is_string($field) || !in_array($field, METRICS_FIELDS, true) || !is_string($code) || !in_array($code, METRICS_CODES, true)) throw new InvalidArgumentException('Invalid category');
    $partner = $body['partner'] ?? '';
    if (!is_string($partner) || ($partner !== '' && !in_array($partner, CAMPAIGN_PARTNERS, true))) throw new InvalidArgumentException('Invalid partner');
    $source = $body['source'] ?? 'direto'; $device = $body['device'] ?? 'desktop';
    if (!is_string($source) || !in_array($source, METRICS_SOURCES, true) || !in_array($device, ['mobile','tablet','desktop'], true)) throw new InvalidArgumentException('Invalid source');
    if ($event !== 'page_view' && (!is_string($body['token'] ?? null) || !hash_equals(metricsToken($body['visit'], $secret), $body['token']))) throw new InvalidArgumentException('Invalid token');
    $attempt = $body['attempt'] ?? '';
    if ($attempt !== '' && !metricsId($attempt)) throw new InvalidArgumentException('Invalid attempt');
    if (in_array($event, ['form_start','progress','submit_attempt','validation_error','success_view'], true) && $attempt === '') throw new InvalidArgumentException('Missing attempt');
    // Build a fresh object from categories only. Extra payload keys and input values are never stored.
    return ['event'=>$event, 'visit'=>$body['visit'], 'event_id'=>$body['event_id'], 'attempt'=>$attempt, 'field'=>$field, 'code'=>$code, 'partner'=>$partner, 'source'=>$source, 'device'=>$device];
}
function metricsInsertIgnore(PDO $db, string $table, array $columns): string {
    return ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? 'INSERT OR IGNORE' : 'INSERT IGNORE') . ' INTO ' . $table . ' (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0,count($columns),'?')) . ')';
}
function metricsRecord(PDO $db, array $event, DateTimeImmutable $now): void {
    $stamp = metricsTimestamp($now);
    $db->beginTransaction();
    try {
        $dedup=$db->prepare(metricsInsertIgnore($db,'campaign_metric_receipts',['event_id','created_at_utc']));
        $dedup->execute([$event['event_id'],$stamp]);
        if ($dedup->rowCount() === 0) { $db->commit(); return; }
        if ($event['event'] === 'page_view') {
            $insert=$db->prepare(metricsInsertIgnore($db,'campaign_visits',['id','created_at_utc','last_activity_utc','source','source_partner','device','page_views']));
            $insert->execute([$event['visit'],$stamp,$stamp,$event['source'],$event['partner'],$event['device'],0]);
            $db->prepare('UPDATE campaign_visits SET page_views=page_views+1,last_activity_utc=? WHERE id=?')->execute([$stamp,$event['visit']]);
        } else {
            $check=$db->prepare('SELECT id FROM campaign_visits WHERE id=?'); $check->execute([$event['visit']]);
            if (!$check->fetchColumn()) throw new InvalidArgumentException('Visit not initialized');
            $db->prepare('UPDATE campaign_visits SET last_activity_utc=? WHERE id=?')->execute([$stamp,$event['visit']]);
            if ($event['attempt'] !== '') {
                $insert=$db->prepare(metricsInsertIgnore($db,'campaign_form_attempts',['id','visit_id','started_at_utc','last_activity_utc','last_field','last_stage','partner']));
                $insert->execute([$event['attempt'],$event['visit'],$stamp,$stamp,$event['field'],metricsStage($event['field']),$event['partner']]);
                $exists=$db->prepare('SELECT id FROM campaign_form_attempts WHERE id=? AND visit_id=?'); $exists->execute([$event['attempt'],$event['visit']]);
                if (!$exists->fetchColumn()) throw new InvalidArgumentException('Invalid attempt association');
                $stage = in_array($event['event'], ['submit_attempt','request_error'],true) ? 'envio' : metricsStage($event['field']);
                if ($event['event'] === 'success_view') $stage='confirmacao';
                $sql='UPDATE campaign_form_attempts SET last_activity_utc=?'; $args=[$stamp];
                if ($event['event'] !== 'leave') { $sql.=",last_stage=CASE WHEN confirmed_at_utc IS NOT NULL THEN 'confirmacao' ELSE ? END"; $args[]=$stage; }
                if ($event['field'] !== '') { $sql.=',last_field=?'; $args[]=$event['field']; }
                if ($event['partner'] !== '') { $sql.=',partner=?'; $args[]=$event['partner']; }
                if ($event['event'] === 'submit_attempt') $sql.=',submit_count=submit_count+1';
                if ($event['event'] === 'success_view') { $sql.=',success_screen_at_utc=COALESCE(success_screen_at_utc,?)'; $args[]=$stamp; }
                $sql.=' WHERE id=? AND visit_id=?'; $args[]=$event['attempt']; $args[]=$event['visit'];
                $db->prepare($sql)->execute($args);
            }
            if (in_array($event['event'], ['validation_error','request_error'], true)) {
                $db->prepare('INSERT INTO campaign_metric_errors (event_id,visit_id,attempt_id,kind,code,field,created_at_utc) VALUES (?,?,?,?,?,?,?)')
                    ->execute([$event['event_id'],$event['visit'],$event['attempt'],$event['event'],$event['code'],$event['field'],$stamp]);
            }
        }
        $db->commit();
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
function metricsRegistration(PDO $db, mixed $input, string $secret, bool $created, DateTimeImmutable $now): void {
    $context=metricsContext($input,$secret); if (!$context) return;
    $stamp=metricsTimestamp($now);
    $visit=$db->prepare('SELECT id FROM campaign_visits WHERE id=?');$visit->execute([$context['visit']]);
    if (!$visit->fetchColumn()) return;
    // The registration response can beat the asynchronous first-field event.
    $db->prepare(metricsInsertIgnore($db,'campaign_form_attempts',['id','visit_id','started_at_utc','last_activity_utc','last_field','last_stage','partner']))
        ->execute([$context['attempt'],$context['visit'],$stamp,$stamp,'','envio','']);
    $sql='UPDATE campaign_form_attempts SET confirmed_at_utc=COALESCE(confirmed_at_utc,?),last_activity_utc=?,last_stage=?,submit_count=CASE WHEN submit_count<1 THEN 1 ELSE submit_count END';
    $args=[$stamp,$stamp,'confirmacao'];
    if ($created) { $sql.=',registered_at_utc=COALESCE(registered_at_utc,?)'; $args[]=$stamp; }
    $sql.=' WHERE id=? AND visit_id=?'; $args[]=$context['attempt']; $args[]=$context['visit'];
    $db->prepare($sql)->execute($args);
}
function metricsDateExpr(PDO $db, string $column): string {
    return $db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite' ? "date($column, '-4 hours')" : "DATE(DATE_SUB($column, INTERVAL 4 HOUR))";
}
function metricsQuery(PDO $db, string $sql, array $args=[]): array { $q=$db->prepare($sql);$q->execute($args);return $q->fetchAll(PDO::FETCH_ASSOC); }
function metricsReport(PDO $db, string $from, string $to, DateTimeImmutable $now): array {
    $zone=new DateTimeZone('America/Campo_Grande');
    $start=new DateTimeImmutable($from.' 00:00:00',$zone); $end=(new DateTimeImmutable($to.' 00:00:00',$zone))->modify('+1 day');
    $range=[metricsTimestamp($start),metricsTimestamp($end)]; $cutoff=metricsTimestamp($now->modify('-30 minutes'));
    $w='created_at_utc >= ? AND created_at_utc < ?'; $aw='started_at_utc >= ? AND started_at_utc < ?';
    $visits=metricsQuery($db,"SELECT COUNT(*) AS visits,COALESCE(SUM(page_views),0) AS page_views FROM campaign_visits WHERE $w",$range)[0];
    $visitFunnel=metricsQuery($db,'SELECT COALESCE(SUM(CASE WHEN EXISTS(SELECT 1 FROM campaign_form_attempts a WHERE a.visit_id=v.id) THEN 1 ELSE 0 END),0) AS visits_started,COALESCE(SUM(CASE WHEN EXISTS(SELECT 1 FROM campaign_form_attempts a WHERE a.visit_id=v.id AND a.submit_count>0) THEN 1 ELSE 0 END),0) AS visits_submitted,COALESCE(SUM(CASE WHEN EXISTS(SELECT 1 FROM campaign_form_attempts a WHERE a.visit_id=v.id AND a.confirmed_at_utc IS NOT NULL) THEN 1 ELSE 0 END),0) AS visits_confirmed FROM campaign_visits v WHERE v.created_at_utc>=? AND v.created_at_utc<?',$range)[0];
    $attempts=metricsQuery($db,"SELECT COUNT(*) AS started,COALESCE(SUM(CASE WHEN confirmed_at_utc IS NOT NULL THEN 1 ELSE 0 END),0) AS confirmed,COALESCE(SUM(CASE WHEN registered_at_utc IS NOT NULL THEN 1 ELSE 0 END),0) AS new_tracked,COALESCE(SUM(CASE WHEN confirmed_at_utc IS NULL AND last_activity_utc <= ? THEN 1 ELSE 0 END),0) AS abandoned,COALESCE(SUM(CASE WHEN confirmed_at_utc IS NULL AND last_activity_utc > ? THEN 1 ELSE 0 END),0) AS active,COALESCE(SUM(submit_count),0) AS submits FROM campaign_form_attempts WHERE $aw",[$cutoff,$cutoff,...$range])[0];
    $total=metricsQuery($db,"SELECT COUNT(*) AS total FROM participants WHERE $w",$range)[0]['total'];
    $started=metricsQuery($db,"SELECT value FROM campaign_metric_settings WHERE name='measurement_started_at'")[0]['value'] ?? null;
    $baseline=$started ? metricsQuery($db,'SELECT COUNT(*) AS total FROM participants WHERE created_at_utc < ?',[$started])[0]['total'] : 0;
    $dailyVisits=metricsQuery($db,'SELECT '.metricsDateExpr($db,'created_at_utc')." AS day,COUNT(*) AS visits,SUM(page_views) AS page_views FROM campaign_visits WHERE $w GROUP BY day ORDER BY day",$range);
    $dailyForms=metricsQuery($db,'SELECT '.metricsDateExpr($db,'started_at_utc')." AS day,COUNT(*) AS started,SUM(CASE WHEN confirmed_at_utc IS NOT NULL THEN 1 ELSE 0 END) AS confirmed,SUM(CASE WHEN confirmed_at_utc IS NULL AND last_activity_utc <= ? THEN 1 ELSE 0 END) AS abandoned FROM campaign_form_attempts WHERE $aw GROUP BY day ORDER BY day",[$cutoff,...$range]);
    $dailyRegistrations=metricsQuery($db,'SELECT '.metricsDateExpr($db,'created_at_utc')." AS day,COUNT(*) AS registrations FROM participants WHERE $w GROUP BY day ORDER BY day",$range);
    $sources=metricsQuery($db,"SELECT v.source,v.source_partner,COUNT(DISTINCT v.id) AS visits,COUNT(a.id) AS started,SUM(CASE WHEN a.confirmed_at_utc IS NOT NULL THEN 1 ELSE 0 END) AS confirmed,SUM(CASE WHEN a.confirmed_at_utc IS NULL AND a.last_activity_utc <= ? THEN 1 ELSE 0 END) AS abandoned FROM campaign_visits v LEFT JOIN campaign_form_attempts a ON a.visit_id=v.id WHERE v.created_at_utc >= ? AND v.created_at_utc < ? GROUP BY v.source,v.source_partner ORDER BY visits DESC",[$cutoff,...$range]);
    $fields=metricsQuery($db,"SELECT last_field,last_stage,COUNT(*) AS abandoned FROM campaign_form_attempts WHERE $aw AND confirmed_at_utc IS NULL AND last_activity_utc <= ? GROUP BY last_field,last_stage ORDER BY abandoned DESC",[...$range,$cutoff]);
    $errors=metricsQuery($db,"SELECT kind,code,field,COUNT(*) AS total FROM campaign_metric_errors WHERE $w GROUP BY kind,code,field ORDER BY total DESC",$range);
    $partners=metricsQuery($db,"SELECT partner,COUNT(*) AS registrations FROM participants WHERE $w GROUP BY partner ORDER BY registrations DESC,partner",$range);
    $devices=metricsQuery($db,"SELECT device,COUNT(*) AS visits FROM campaign_visits WHERE $w GROUP BY device ORDER BY visits DESC",$range);
    $notStarted=metricsQuery($db,'SELECT COUNT(*) AS total FROM campaign_visits v WHERE v.created_at_utc>=? AND v.created_at_utc<? AND NOT EXISTS (SELECT 1 FROM campaign_form_attempts a WHERE a.visit_id=v.id)',$range)[0]['total'];
    return ['from'=>$from,'to'=>$to,'generated_at'=>$now->setTimezone($zone)->format(DATE_ATOM),'measurement_started_at'=>$started,'baseline_registrations'=>(int)$baseline,'summary'=>array_map('intval',[...$visits,...$visitFunnel,...$attempts,'registrations'=>$total,'not_started'=>$notStarted]),'daily_visits'=>$dailyVisits,'daily_forms'=>$dailyForms,'daily_registrations'=>$dailyRegistrations,'sources'=>$sources,'abandonment_fields'=>$fields,'errors'=>$errors,'partners'=>$partners,'devices'=>$devices];
}
