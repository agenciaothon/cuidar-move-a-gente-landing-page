<?php
declare(strict_types=1);

const CAMPAIGN_TERMS = 'v5-2026-10-08';
const CAMPAIGN_PRIVACY = '2026-10-08';
const CAMPAIGN_PARTNERS = ['trok', 'pizzaria-da-mama', 'brutus-ms', 'best-life', 'apollo-pet-salon', 'mais-tintas', 'geracao-store', 'alvorada-veiculos', 'beone', 'jessica-walter', 'frigodil', 'tricarne', 'ccaa', 'concept', 'virtus'];
const CAMPAIGN_STATES = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];

function campaignIsOpen(DateTimeImmutable $now): bool {
    $zone = new DateTimeZone('America/Campo_Grande');
    return $now >= new DateTimeImmutable('2026-10-08 00:00:00', $zone)
        && $now < new DateTimeImmutable('2026-11-09 00:00:00', $zone);
}
function campaignCpf(string $input): bool {
    $d = preg_replace('/\D/', '', $input);
    if (strlen($d) !== 11 || preg_match('/^(\d)\1{10}$/', $d)) return false;
    for ($n = 9; $n < 11; $n++) {
        $sum = 0;
        for ($i = 0; $i < $n; $i++) $sum += (int)$d[$i] * ($n + 1 - $i);
        $digit = ($sum * 10) % 11;
        if ($digit === 10) $digit = 0;
        if ($digit !== (int)$d[$n]) return false;
    }
    return true;
}
function campaignValidate(array $input): array {
    $limits = ['name'=>100, 'cpf'=>14, 'phone'=>15, 'email'=>150, 'city'=>80, 'state'=>2, 'partner'=>40, 'website'=>100];
    $data = [];
    foreach ($limits as $key => $limit) {
        $value = $input[$key] ?? '';
        if (!is_string($value) || mb_strlen($value) > $limit || preg_match('/[\x00-\x1F\x7F]/u', $value)) throw new InvalidArgumentException('Confira os dados informados.');
        $data[$key] = trim($value);
    }
    if ($data['website'] !== '') throw new InvalidArgumentException('Não foi possível concluir este envio.');
    if (preg_match('/^\S+\s+\S+/u', $data['name']) !== 1) throw new InvalidArgumentException('Informe seu nome completo.');
    $data['cpf'] = preg_replace('/\D/', '', $data['cpf']);
    $data['phone'] = preg_replace('/\D/', '', $data['phone']);
    if (!campaignCpf($data['cpf'])) throw new InvalidArgumentException('Confira o CPF informado.');
    if (!preg_match('/^[1-9]\d{9,10}$/', $data['phone'])) throw new InvalidArgumentException('Informe um telefone com DDD.');
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Informe um e-mail válido.');
    if (mb_strlen($data['city']) < 2 || !in_array($data['state'], CAMPAIGN_STATES, true)) throw new InvalidArgumentException('Informe sua cidade e UF.');
    if (!in_array($data['partner'], CAMPAIGN_PARTNERS, true)) throw new InvalidArgumentException('Selecione o estabelecimento participante.');
    if (($input['adult'] ?? null) !== true || ($input['terms'] ?? null) !== true) throw new InvalidArgumentException('Confirme as declarações e o regulamento.');
    unset($data['website']);
    return $data;
}
function campaignRegister(PDO $db, array $data, DateTimeImmutable $now): void {
    if (!campaignIsOpen($now)) throw new DomainException('As inscrições estão fora do período da campanha.');
    $statement = $db->prepare('INSERT INTO participants (full_name, cpf, phone, email, city, state, partner, adult_declared, terms_version, privacy_version, created_at_utc) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)');
    try {
        $statement->execute([$data['name'], $data['cpf'], $data['phone'], $data['email'], $data['city'], $data['state'], $data['partner'], CAMPAIGN_TERMS, CAMPAIGN_PRIVACY, $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')]);
    } catch (PDOException $e) {
        $mysqlDuplicate = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' && (int)($e->errorInfo[1] ?? 0) === 1062;
        $testDuplicate = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' && str_contains($e->getMessage(), 'UNIQUE constraint failed: participants.cpf');
        if (!$mysqlDuplicate && !$testDuplicate) throw $e;
        // A second submission never overwrites existing personal details.
    }
}
