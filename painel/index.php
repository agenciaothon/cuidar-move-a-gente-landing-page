<?php
declare(strict_types=1);
require_once __DIR__.'/../api/metrics.php';
header('Cache-Control: no-store, private, max-age=0');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
ini_set('display_errors','0');
session_name('cuidar_painel');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['lifetime'=>0,'path'=>'/painel/','secure'=>true,'httponly'=>true,'samesite'=>'Strict']);
session_start();
if (empty($_SESSION['csrf'])) $_SESSION['csrf']=bin2hex(random_bytes(32));
$csrf=$_SESSION['csrf'];
$adminFile=dirname(__DIR__,2).'/cuidar-private/metrics-admin.php';
$admin=is_file($adminFile)?require $adminFile:[];
$message='';
$now=time();
if (!empty($_SESSION['authorized']) && ($now-($_SESSION['last_seen']??0)>1800 || $now-($_SESSION['login_at']??0)>28800)) unset($_SESSION['authorized']);
function dashboardDb(): PDO {
    $config=require dirname(__DIR__,2).'/cuidar-private/config.php';
    return new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_TIMEOUT=>5]);
}
function dashboardRate(PDO $db): bool {
    $config=require dirname(__DIR__,2).'/cuidar-private/config.php';
    $bucket=hash_hmac('sha256','panel-login|'.($_SERVER['REMOTE_ADDR']??'unknown').'|'.(string)floor(time()/900),$config['rate_secret']);
    $db->prepare('INSERT INTO campaign_metric_limits (bucket,attempts,created_at_utc) VALUES (?,1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE attempts=attempts+1')->execute([$bucket]);
    $q=$db->prepare('SELECT attempts FROM campaign_metric_limits WHERE bucket=?');$q->execute([$bucket]);return (int)$q->fetchColumn()<=10;
}
function dashboardAuthorize(): void { session_regenerate_id(true);$_SESSION['authorized']=true;$_SESSION['login_at']=time();$_SESSION['last_seen']=time(); }
function html(string $text): string { return htmlspecialchars($text,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
    if (!is_string($_POST['csrf']??null) || !hash_equals($csrf,$_POST['csrf'])) { http_response_code(403);$message='Sua sessão expirou. Recarregue a página.'; }
    elseif (($_POST['action']??'')==='logout') { $_SESSION=[];session_destroy();header('Location: ./');exit; }
    else {
        try {
            if (!dashboardRate(dashboardDb())) throw new RuntimeException('Muitas tentativas. Aguarde 15 minutos.');
            $password=$_POST['password']??'';
            if (!is_string($password) || strlen($password)>128) throw new RuntimeException('Confira a senha informada.');
            if (($_POST['action']??'')==='activate') {
                if (strlen($password)<12 || $password!==($_POST['password_confirmation']??'')) throw new RuntimeException('Use ao menos 12 caracteres e repita a mesma senha.');
                $lock=fopen($adminFile.'.lock','c');
                if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Não foi possível ativar agora.');
                try {
                    $fresh=is_file($adminFile)?require $adminFile:[];
                    $key=$_POST['activation_key']??'';
                    if (!empty($fresh['password_hash']) || empty($fresh['setup_hash']) || !is_string($key) || !hash_equals($fresh['setup_hash'],hash('sha256',$key))) throw new RuntimeException('O link de ativação não é válido ou já foi utilizado.');
                    $next=['password_hash'=>password_hash($password,PASSWORD_DEFAULT),'setup_hash'=>'','activated_at_utc'=>gmdate(DATE_ATOM)];
                    $temp=$adminFile.'.'.bin2hex(random_bytes(8)).'.tmp';
                    if (file_put_contents($temp,"<?php\nreturn ".var_export($next,true).";\n",LOCK_EX)===false) throw new RuntimeException('Não foi possível salvar o acesso.');
                    chmod($temp,0600);
                    if (!rename($temp,$adminFile)) throw new RuntimeException('Não foi possível salvar o acesso.');
                    dashboardAuthorize();
                } finally { flock($lock,LOCK_UN);fclose($lock); }
            } else {
                if (empty($admin['password_hash']) || !password_verify($password,$admin['password_hash'])) throw new RuntimeException('Senha incorreta ou painel ainda não ativado.');
                dashboardAuthorize();
            }
            header('Location: ./');exit;
        } catch (Throwable $e) { $message=$e instanceof RuntimeException && !($e instanceof PDOException)?$e->getMessage():'O painel está temporariamente indisponível.'; }
    }
}
$authorized=!empty($_SESSION['authorized']);
if ($authorized) $_SESSION['last_seen']=$now;
$format=$_GET['format']??'';
if ($format!=='') {
    if (!$authorized) { http_response_code(401);header('Content-Type: application/json');echo '{"message":"Entre novamente para consultar o painel."}';exit; }
    session_write_close();
    try {
        $from=$_GET['from']??'2026-10-08';$to=$_GET['to']??(new DateTimeImmutable('now',new DateTimeZone('America/Campo_Grande')))->format('Y-m-d');
        foreach ([$from,$to] as $date) if (!is_string($date) || !preg_match('/^2026-(10|11|12)-\d{2}$/D',$date) || DateTimeImmutable::createFromFormat('!Y-m-d',$date)?->format('Y-m-d')!==$date) throw new InvalidArgumentException('Selecione datas válidas.');
        if ($from>$to) throw new InvalidArgumentException('O início precisa ser anterior ao fim.');
        $report=metricsReport(dashboardDb(),$from,$to,new DateTimeImmutable('now',new DateTimeZone('UTC')));
        if ($format==='csv') {
            header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="cuidar-resultados-'.$from.'-a-'.$to.'.csv"');
            $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");
            $row=function(array $values)use($out):void{fputcsv($out,array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/u',(string)$v)?"'".$v:$v,$values),';','"','');};
            $row(['Cuidar Move a Gente','Resultados parciais ou finais conforme período selecionado']);$row(['De',$from,'Até',$to]);$row(['Atualizado',$report['generated_at']]);$row(['Início da medição UTC',$report['measurement_started_at']??'']);
            $row(['Definição de abandono','Tentativa sem confirmação no servidor e sem atividade há 30 minutos; não equivale a pessoas únicas.']);
            $labels=['visits'=>'Sessões de navegação','page_views'=>'Visualizações de página','started'=>'Tentativas iniciadas','confirmed'=>'Tentativas confirmadas no servidor','abandoned'=>'Tentativas abandonadas','active'=>'Tentativas em andamento','registrations'=>'Cadastros novos no período (banco)','new_tracked'=>'Cadastros novos atribuídos a tentativas','visits_started'=>'Sessões com início','visits_submitted'=>'Sessões com envio','visits_confirmed'=>'Sessões com confirmação','not_started'=>'Sessões sem início','submits'=>'Tentativas de envio'];
            $row([]);$row(['RESUMO']);foreach($report['summary'] as $k=>$v)$row([$labels[$k]??$k,$v]);
            foreach(['daily_visits'=>'SESSÕES POR DIA','daily_forms'=>'TENTATIVAS POR DIA','daily_registrations'=>'CADASTROS POR DIA','sources'=>'ORIGENS','abandonment_fields'=>'ABANDONO POR CAMPO','errors'=>'ERROS','partners'=>'CADASTROS POR ESTABELECIMENTO','devices'=>'DISPOSITIVOS'] as $k=>$title){$row([]);$row([$title]);if($report[$k]){$row(array_keys($report[$k][0]));foreach($report[$k] as $r)$row(array_values($r));}}
            fclose($out);exit;
        }
        if ($format!=='json') throw new InvalidArgumentException('Formato inválido.');
        header('Content-Type: application/json; charset=utf-8');echo json_encode($report,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
    } catch (Throwable $e) { http_response_code($e instanceof InvalidArgumentException?400:503);header('Content-Type: application/json');echo json_encode(['message'=>$e instanceof InvalidArgumentException?$e->getMessage():'Não foi possível carregar os indicadores agora.'],JSON_UNESCAPED_UNICODE);exit; }
}
session_write_close();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Resultados · Cuidar Move a Gente</title><link rel="stylesheet" href="painel.css?v=20261017"><script defer src="painel.js?v=20261017"></script></head>
<body data-authorized="<?= $authorized?'true':'false' ?>">
<?php if (!$authorized): ?>
<main class="login"><p class="eyebrow">TROK CAR BRASIL · OUTUBRO ROSA 2026</p><h1>O cuidado<br>em números.</h1><p>Acesso privado aos resultados da campanha Cuidar Move a Gente.</p>
<?php if($message): ?><p class="notice error" role="alert"><?=html($message)?></p><?php endif; ?>
<form method="post" id="login-form"><input type="hidden" name="csrf" value="<?=html($csrf)?>"><input type="hidden" name="action" value="login"><label>Senha do painel<input type="password" name="password" autocomplete="current-password" maxlength="128" required></label><button type="submit">Entrar no painel <span>↗</span></button></form>
<form method="post" id="activation-form" hidden><input type="hidden" name="csrf" value="<?=html($csrf)?>"><input type="hidden" name="action" value="activate"><input type="hidden" name="activation_key" id="activation-key" value="<?=html(is_string($_POST['activation_key']??null)?$_POST['activation_key']:'')?>"><p class="notice">Defina uma senha exclusiva para os responsáveis pelo acompanhamento. O link de ativação será inutilizado após esta etapa.</p><label>Crie a senha do painel<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="128" required><small>Ao menos 12 caracteres.</small></label><label>Repita a senha<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="128" required></label><button type="submit">Ativar acesso privado <span>↗</span></button></form>
<a class="back" href="../?medicao=teste">← Voltar à campanha</a></main>
<?php else: ?>
<div class="dashboard"><header class="top"><div><p class="eyebrow">CUIDAR MOVE A GENTE / RESULTADOS</p><h1>Cada participação<br>conta uma história.</h1></div><div class="top-right"><span class="live">Atualiza a cada minuto</span><form method="post"><input type="hidden" name="csrf" value="<?=html($csrf)?>"><input type="hidden" name="action" value="logout"><button class="quiet" type="submit">Sair ↗</button></form></div></header>
<section class="filters" aria-label="Período do relatório"><label>De<input type="date" id="from" value="2026-10-08" min="2026-10-08" max="2026-12-31"></label><label>Até<input type="date" id="to" value="<?=html((new DateTimeImmutable('now',new DateTimeZone('America/Campo_Grande')))->format('Y-m-d'))?>" min="2026-10-08" max="2026-12-31"></label><button id="apply">Aplicar período</button><button id="export" class="quiet">Exportar CSV ↓</button><button id="print" class="quiet">Apresentar / PDF ↗</button></section>
<p class="notice" id="coverage">Carregando indicadores…</p><p class="notice error" id="panel-error" role="alert" hidden></p>
<section class="kpis" aria-label="Indicadores principais"><article><span>Cadastros no período</span><strong id="kpi-registrations">—</strong><small>Participantes gravados no banco</small></article><article><span>Sessões de navegação</span><strong id="kpi-visits">—</strong><small>Uma aba / intervalo de 30 minutos</small></article><article><span>Cadastros iniciados</span><strong id="kpi-started">—</strong><small>Tentativas de preenchimento</small></article><article class="rose"><span>Desistências identificadas</span><strong id="kpi-abandoned">—</strong><small>Sem conclusão e inativos há 30 min</small></article></section>
<section class="two-cols"><article class="panel"><div class="section-heading"><span class="eyebrow">01 / DO ACESSO À CONFIRMAÇÃO</span><h2>O caminho da participação.</h2></div><div id="funnel"></div><p class="caption">O funil conta sessões com cada etapa. Um mesmo navegador pode cadastrar mais de uma pessoa; sessões não equivalem a pessoas únicas.</p></article><article class="panel"><div class="section-heading"><span class="eyebrow">02 / TENTATIVAS DE CADASTRO</span><h2>Quem ficou pelo caminho.</h2></div><div id="attempt-status"></div><p class="caption">Desistência é uma estimativa por inatividade. Se a mesma tentativa voltar e concluir, o painel passa a classificá-la como concluída. Quem ainda está preenchendo aparece em andamento.</p></article></section>
<section class="panel wide"><div class="section-heading row"><div><span class="eyebrow">03 / EVOLUÇÃO DIÁRIA</span><h2>A corrente, dia após dia.</h2></div><span class="caption">Horário de Três Lagoas/MS · UTC−4</span></div><div id="chart" aria-label="Evolução diária de sessões e cadastros"></div><div class="table-wrap" id="daily-table"></div></section>
<section class="two-cols"><article class="panel"><span class="eyebrow">04 / ÚLTIMA INTERAÇÃO</span><h2>Onde houve desistência.</h2><div class="table-wrap" id="fields-table"></div><p class="caption">Mostra o campo acessado ou indicado na validação. O conteúdo digitado não é coletado e o último campo não prova o motivo da desistência.</p></article><article class="panel"><span class="eyebrow">05 / DIFICULDADES ENCONTRADAS</span><h2>Erros que merecem atenção.</h2><div class="table-wrap" id="errors-table"></div></article></section>
<section class="two-cols"><article class="panel"><span class="eyebrow">06 / ORIGEM DOS ACESSOS</span><h2>Por onde a corrente chega.</h2><div class="table-wrap" id="sources-table"></div><div id="devices" class="devices"></div><p class="caption">“Direto / não identificado” inclui acessos sem identificação de origem. O estabelecimento indicado no cadastro é diferente da origem do link.</p></article><article class="panel"><span class="eyebrow">07 / ESTABELECIMENTOS</span><h2>Participações por parceiro.</h2><div class="table-wrap" id="partners-table"></div></article></section>
<section class="panel link-builder"><span class="eyebrow">08 / MATERIAIS E DIVULGAÇÃO</span><h2>Identifique os próximos links.</h2><p>Use links identificados nas próximas peças e mensagens. O QR Code antigo continua funcionando e será identificado como “QR dos cartazes antigos”.</p><div class="filters"><label>Origem<select id="link-source"><option value="cartaz">Cartaz / display</option><option value="instagram">Instagram</option><option value="whatsapp">WhatsApp</option><option value="parceiro">Link do parceiro</option></select></label><label>Parceiro<select id="link-partner"><option value="">Sem parceiro específico</option></select></label></div><label>Link pronto<input type="url" id="generated-link" readonly></label><button id="copy-link" class="quiet">Copiar link</button></section>
<footer><p id="updated-at">—</p><p>Medição de navegação sem nome, CPF, telefone, e-mail ou conteúdo dos campos. O relatório contém apenas indicadores agregados. Os totais não representam alcance publicitário, faturamento ou recursos arrecadados.</p><p>Após o encerramento, selecione 08/10 a 08/11 e exporte o CSV ou salve esta página em PDF. Os dados de navegação anteriores à ativação da medição não podem ser reconstruídos.</p></footer></div>
<?php endif; ?></body></html>
