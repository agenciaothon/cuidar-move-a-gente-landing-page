<?php
declare(strict_types=1);
require dirname(__DIR__) . '/api/campaign.php';
$checks=0;
function check(bool $value, string $message): void { global $checks; $checks++; if (!$value) throw new RuntimeException($message); }
$input=['name'=>'Pessoa Teste','cpf'=>'529.982.247-25','phone'=>'(67) 90000-0000','email'=>'teste@example.com','city'=>'Três Lagoas','state'=>'MS','partner'=>'trok','adult'=>true,'terms'=>true,'website'=>''];
$data=campaignValidate($input);
check($data['cpf']==='52998224725', 'Normalize CPF');
check(!campaignCpf('11111111111'), 'Reject repeated CPF');
check(!campaignCpf('52998224726'), 'Reject incorrect check digits');
foreach (['cpf'=>'123', 'phone'=>'000', 'email'=>'invalid', 'state'=>'ZZ', 'partner'=>'forged', 'adult'=>false, 'terms'=>false, 'website'=>'spam', 'name'=>['array']] as $key=>$value) {
    try { campaignValidate(array_replace($input,[$key=>$value])); throw new RuntimeException('Accepted invalid '.$key); }
    catch (InvalidArgumentException $e) { $checks++; }
}
foreach (['2026-10-08T03:59:59Z'=>false,'2026-10-08T04:00:00Z'=>true,'2026-11-09T03:59:59Z'=>true,'2026-11-09T04:00:00Z'=>false] as $date=>$expected) check(campaignIsOpen(new DateTimeImmutable($date))===$expected,'Campaign time boundary '.$date);
// Test the shared business/persistence code with an isolated in-memory adapter.
// Production API requires MySQL; these tests do not claim Hostinger/MySQL verification.
$db=new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE participants (id INTEGER PRIMARY KEY, full_name TEXT, cpf TEXT UNIQUE, phone TEXT, email TEXT, city TEXT, state TEXT, partner TEXT, adult_declared INTEGER, terms_version TEXT, privacy_version TEXT, created_at_utc TEXT)');
$date=new DateTimeImmutable('2026-10-08T15:00:00Z');
campaignRegister($db,$data,$date);
campaignRegister($db,array_replace($data,['name'=>'Changed Name','email'=>'changed@example.com']),$date);
check((int)$db->query('SELECT COUNT(*) FROM participants')->fetchColumn()===1,'Duplicate does not add chance');
$record=$db->query('SELECT * FROM participants')->fetch(PDO::FETCH_ASSOC);
check($record['full_name']==='Pessoa Teste' && $record['email']==='teste@example.com','Duplicate must not overwrite existing data');
check($record['terms_version']===CAMPAIGN_TERMS && $record['adult_declared']===1,'Store consent version and adult declaration');
check($record['created_at_utc']==='2026-10-08 15:00:00','Store UTC timestamp');
try {campaignRegister($db,$data,new DateTimeImmutable('2026-11-09T04:00:00Z')); throw new RuntimeException('Accepted late registration');} catch(DomainException $e){$checks++;}
$db->exec('DROP TABLE participants');
try {campaignRegister($db,$data,$date); throw new RuntimeException('False success on database failure');} catch(PDOException $e){$checks++;}
echo "Passed $checks checks; synthetic data only; no production database touched.\n";
