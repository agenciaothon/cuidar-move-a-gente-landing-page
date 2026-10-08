# Cadastro e sorteio

## Armazenamento

Banco exclusivo MariaDB/MySQL na Hostinger. O formulário chama `api/inscricoes.php` no mesmo domínio. A mensagem de conclusão só aparece após persistência no banco. Uma restrição UNIQUE no CPF impede duas chances mesmo em envios simultâneos. Reenvios não alteram dados existentes e recebem a mesma resposta, sem expor se outro CPF está cadastrado.

Dados: nome, CPF, celular, e-mail, cidade, UF, estabelecimento, declaração de maioridade, versões dos termos e horário UTC. Não há comprovantes, OTP, senha do participante, área individual, pontuação, marketing ou compartilhamento automático de leads. Correções passam pelo WhatsApp oficial com verificação de identidade pela equipe.

Acesso administrativo: Hostinger → site → Bancos de dados → phpMyAdmin. Nenhuma consulta de dados pessoais é exposta na landing page. A equipe acessa somente pelos administradores autorizados da hospedagem. Não criar planilha pública ou subir dados/credenciais no GitHub.

## Implantação

1. Usuário cria banco e usuário exclusivos no hPanel e define sua senha.
2. Importar `schema.sql` nesse banco via phpMyAdmin. Não usar bancos de outros sites.
3. Colocar `config.php` preenchido dentro de `cuidar-private`, ao lado de `public_html`. O modelo é `config.example.php`; não publicar a configuração real. Manter acesso restrito e permissões 600 quando suportado.
4. Gerar `rate_secret` aleatório com no mínimo 32 caracteres, apenas no arquivo privado. A proteção usa HMAC do IP + hora, não guarda o IP em claro, e limpa buckets antigos após 24 horas quando houver novos envios.
5. Confirmar e publicar o prazo de retenção aprovado no aviso; só então ajustar `privacy_retention_approved` e `registration_enabled` para true. Confirmar banco/tabelas e backup no painel.
6. Subir apenas index.html, app.js, poster.css, API e regulamentos atualizados. Preservar os assets existentes. Não colocar tests, deploy ou dados na pasta pública.
7. Confirmar API, cookies Secure/HttpOnly/SameSite, persistência, duplicidade e erros em ambiente de teste antes da ativação. A suíte local usa SQLite isolado para regras compartilhadas e não substitui o teste MySQL real.

Sem configuração válida, o cadastro fica indisponível e jamais informa sucesso. No GitHub Pages, o formulário direciona ao domínio oficial: GitHub Pages não executa PHP.

## Consulta diária

No phpMyAdmin, consultar o total com `SELECT COUNT(*) AS participantes FROM participants;` e a origem com `SELECT partner, COUNT(*) AS participantes FROM participants GROUP BY partner ORDER BY participantes DESC;`.

O painel atual informa backups diários. Verificar que o novo banco integra a rotina e fazer exportação privada antes e depois do sorteio. O cadastro não registra faturamento nem repasses; estes controles financeiros continuam separados.

## Encerramento e apuração

O backend aceita inscrições até 08/11/2026 23h59m59s de Três Lagoas (UTC−4); fecha às 00h00 de 09/11. Salva horário UTC, com limite exclusivo `2026-11-09 04:00:00`.

Após o fechamento, conferir ocorrências e gerar uma cópia final da lista. O script `export.php` deve ficar em `cuidar-tools`, fora de public_html, e só executa pela linha de comando da hospedagem. Ele gera CSV restrito com os dados, CSV com apenas identificadores para a apuração e manifesto com contagem, data e hashes SHA-256. Exportações anteriores ao fechamento são marcadas como parciais. Alternativamente, a exportação CSV/SQL pode ser feita pelo phpMyAdmin por administrador autorizado; guardar a mesma separação entre lista privada e identificadores.

Para o evento, definir e registrar a ferramenta e a ordem das três extrações. Carregar apenas a LISTA de identificadores efetivamente cadastrados, e nunca um intervalo de 1 até o maior ID (sequências de banco podem ter lacunas). Todos terão uma chance. Após cada extração, remover o identificador contemplado das próximas. Gravar a transmissão e a apuração; guardar a lista usada, seu hash, responsáveis, horários, resultados e ocorrências. Contatar os ganhadores usando o CSV privado. Não publicar CPF, telefone ou e-mail. A ferramenta de sorteio e a ordem de apuração ainda não estão definidas; nenhum sorteio foi implementado ou executado nesta entrega.

## Pendências operacionais

Local do evento, presença dos contemplados, detalhes de entrega/validade dos prêmios, ferramenta e ordem de apuração, eventual suplência, retenção dos cadastros e prazo de prestação de contas. Esses pontos não devem ser inventados nem alterados retroativamente.
