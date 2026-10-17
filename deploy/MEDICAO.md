# Medição e painel privado

## Implantação

1. Importar `metrics-schema.sql` no banco da campanha. A migração é aditiva; não modifica participantes existentes. A primeira execução registra o início real da medição.
2. Publicar `api/metrics.php`, `api/medicao.php`, as versões atualizadas de `api/campaign.php` e `api/inscricoes.php`, `tracking.js`, `app.js`, `index.html` e `painel/`.
3. Criar **privadamente**, ao lado de `config.php` e fora de `public_html`, `metrics-admin.php` com `password_hash` vazio e `setup_hash` contendo SHA-256 de uma chave aleatória de 32 bytes. A chave em claro nunca integra o repositório, os arquivos públicos ou este documento.
4. O responsável abre `/painel/#chave=CHAVE_PRIVADA` e define sua própria senha. A chave fica no fragmento e não é transmitida no endereço HTTP. O navegador a envia somente no formulário de ativação. O servidor guarda apenas o hash da senha e inutiliza a chave. A senha não precisa ser enviada ao assistente.
5. O painel em `/painel/` exige login. Sessões expiram por 30 minutos de inatividade e têm duração absoluta máxima de 8 horas. O endpoint JSON e a exportação CSV exigem a mesma autenticação. Não publicar senha ou link de ativação.

O painel não oferece acesso à lista de participantes, CPFs ou contatos. Usa consultas agregadas. A senha do painel não é a senha da hospedagem nem a do banco.

## Definições

- **Sessão:** identificador aleatório na aba, conservado em sessionStorage por até 30 minutos entre interações/aberturas. Não corresponde a pessoa única. Sem fingerprint ou cookie adicional de publicidade.
- **Visualização:** abertura/recarregamento da página oficial. Recarregamentos na mesma sessão somam visualizações e preservam o identificador da sessão.
- **Tentativa iniciada:** primeira alteração em um campo, seleção, declaração ou tentativa de envio. O código lê somente o identificador do campo. O estabelecimento é uma categoria predefinida.
- **Em andamento:** tentativa sem confirmação, com atividade nos últimos 30 minutos.
- **Desistência estimada:** tentativa sem confirmação no servidor, inativa por pelo menos 30 minutos. A janela é calculada a cada consulta; não depende de alguém manter o painel aberto. Sair da página é um sinal auxiliar, não uma conclusão imediata. Se a mesma tentativa for retomada e confirmar, deixa de constar como desistência.
- **Confirmação:** resposta positiva do servidor, registrada após a gravação ou a verificação idempotente de CPF já cadastrado. Evento de sucesso enviado pelo navegador, isoladamente, não confirma a tentativa.
- **Cadastro novo:** nova linha efetivamente inserida em `participants`. CPF repetido não incrementa esse total. Uma mesma sessão pode atender várias pessoas; o botão “Cadastrar outra pessoa” inicia uma nova tentativa.
- **Último campo:** campo interagido ou apontado na validação. Não representa necessariamente o motivo da saída nem prova que o campo foi preenchido.
- **Abandono percentual:** abandonadas / (abandonadas + confirmadas). Tentativas ainda em andamento ficam fora desse percentual.
- **Funil:** sessões distintas com início, envio e confirmação. Não soma múltiplas tentativas da mesma sessão como se fossem visitantes diferentes.

Horários dos relatórios: America/Campo_Grande (UTC−4). Armazenamento: UTC. O período selecionado filtra sessões pela abertura, tentativas pelo início e cadastros pela gravação. O relatório pode mudar com a retomada de uma tentativa ainda existente.

## O que não se coleta

Não se envia conteúdo de nome, CPF, telefone, e-mail, cidade, declarações ou outros campos à medição. Não se guardam URL completa, referrer completo ou IP bruto. O IP é usado temporariamente para gerar HMAC de limite de requisições; esses limites expiram e não integram os relatórios. Eventos aceitam somente categorias predefinidas, e as cargas extras são descartadas. IDs de medição não são gravados na tabela de participantes.

Os relatórios não medem alcance de publicidade, pessoas únicas, vendas, faturamento nem arrecadação para a Rede Feminina. Os sete registros anteriores à implantação permanecem no total, mas visitas, erros e abandonos anteriores não podem ser reconstruídos.

## Origem dos acessos

O QR antigo continua no GitHub e redireciona ao domínio oficial com `origem=qr-legado`. Novos links podem usar `origem=cartaz`, `instagram`, `facebook`, `whatsapp` ou `parceiro` e `parceiro=trok` (ou outro slug da lista oficial). O painel possui um gerador desses links. Origem do link e estabelecimento declarado na inscrição são medidas distintas.

`?medicao=teste` desativa a instrumentação naquela abertura para auditorias técnicas. Não é uma forma de concluir ou validar inscrições; nenhuma inscrição é criada por esse parâmetro.

## Apresentação dos resultados

O painel atualiza a cada minuto enquanto está visível. Exportar CSV produz somente totais e agrupamentos; a versão para impressão permite salvar PDF. Para o relatório de encerramento, selecionar 08/10/2026 a 08/11/2026, aguardar pelo menos 30 minutos após o fechamento, exportar e registrar a data de geração. Identificar separadamente o intervalo efetivamente coberto pela medição.

Conservar esses agregados para prestação de contas e apresentação. Após a consolidação, definir a remoção dos identificadores e eventos detalhados de navegação conforme a política da organização. Os dados não são enviados a plataformas externas de publicidade.

## Verificação

`php tests/registration.php` e `php tests/metrics.php` usam dados sintéticos em SQLite na memória. Verificam regressões no cadastro, deduplicação, corte de 30 minutos, retomada, confirmação do servidor, CPF repetido, mais de uma tentativa na sessão, associação entre tentativas e sessões e fuso local.

Falhas de medição não podem bloquear a inscrição. A gravação real permanece independente; não há sucesso simulado. Se a própria conexão com o banco falhar, a medição também poderá ficar indisponível; ela não substitui monitoramento externo de disponibilidade.
