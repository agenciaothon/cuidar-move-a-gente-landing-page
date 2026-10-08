<?php
declare(strict_types=1);
// CLI only. Place in cuidar-tools beside public_html. No public export endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc !== 2) { fwrite(STDERR, "Uso: php export.php /caminho/privado/nova-pasta\n"); exit(1); }
$parent=realpath(dirname($argv[1]));
$web=realpath(__DIR__.'/../public_html');
if (!$parent || ($web && ($parent===$web || str_starts_with($parent,$web.DIRECTORY_SEPARATOR))) || file_exists($argv[1])) throw new RuntimeException('Use uma pasta nova fora de public_html.');
umask(0077);
if (!mkdir($argv[1],0700)) throw new RuntimeException('Não foi possível criar a pasta.');
$config=require __DIR__.'/../cuidar-private/config.php';
$db=new PDO($config['dsn'],$config['user'],$config['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$db->beginTransaction();
$stmt=$db->query("SELECT * FROM participants WHERE created_at_utc >= '2026-10-08 04:00:00' AND created_at_utc < '2026-11-09 04:00:00' ORDER BY id");
$private=fopen($argv[1].'/participantes-restrito.csv','xb');
$draw=fopen($argv[1].'/identificadores-sorteio.csv','xb');
if (!$private || !$draw) throw new RuntimeException('Erro na criação dos arquivos.');
fwrite($private,"\xEF\xBB\xBF");
fputcsv($private,['Inscrição','Nome','CPF','Celular','E-mail','Cidade','UF','Estabelecimento','Cadastro UTC','Regulamento'], ';','"','');
fputcsv($draw,['inscricao'], ',', '"','');
$count=0;
while($r=$stmt->fetch(PDO::FETCH_ASSOC)) {
    $id='CMG-'.str_pad((string)$r['id'],6,'0',STR_PAD_LEFT);
    $fields=[$id,$r['full_name'],$r['cpf'],$r['phone'],$r['email'],$r['city'],$r['state'],$r['partner'],$r['created_at_utc'],$r['terms_version']];
    $fields=array_map(fn($v)=>preg_match('/^[=+@\-\t\r\n]/u',(string)$v) ? "'".$v : $v,$fields);
    fputcsv($private,$fields,';','"',''); fputcsv($draw,[$id],',','"',''); $count++;
}
$db->commit(); fclose($private); fclose($draw);
$closed=time()>=(new DateTimeImmutable('2026-11-09T04:00:00Z'))->getTimestamp();
$manifest=['exported_at_utc'=>gmdate(DATE_ATOM),'registrations'=>$count,'registration_period_closed'=>$closed,'status'=>$closed?'Base após encerramento das inscrições; conferir ocorrências antes da apuração.':'Acompanhamento parcial; NÃO usar no sorteio final.','sha256_private'=>hash_file('sha256',$argv[1].'/participantes-restrito.csv'),'sha256_draw'=>hash_file('sha256',$argv[1].'/identificadores-sorteio.csv')];
file_put_contents($argv[1].'/manifesto.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
echo "Exportação privada concluída. $count registros. ".($closed?'Inscrições encerradas.':'Base parcial.')."\n";
