# Cuidar Move a Gente

Landing page da campanha Outubro Rosa 2026, com 14 empresas parceiras e realização da Trok Car Brasil.

Atualização preparada em 08/10/2026: cadastro direto em uma etapa, regulamento V5 baseado no Word enviado pelo cliente, datas de 08/10 a 08/11, uma chance por CPF, prêmios distribuídos em três colocações e evento de 14/11 às 18h30 com local a definir.

## Publicação

[Domínio oficial na Hostinger](https://cuidarmoveagente.com.br/) · [Espelho no GitHub Pages](https://agenciaothon.github.io/cuidar-move-a-gente-landing-page/)

O cadastro real exige PHP 8.1 ou superior, PDO MySQL, mbstring e um banco dedicado na Hostinger. O GitHub Pages apresenta o conteúdo e direciona ao domínio oficial para cadastro; não executa PHP.

## Funcionamento

- Nome, CPF, celular, e-mail, cidade/UF, estabelecimento e aceite.
- Sem código de confirmação, comprovante, segunda etapa ou validação prévia do parceiro.
- CPF único garantido pelo banco; reenvios não criam chances nem alteram cadastros existentes.
- Confirmação na tela somente depois da gravação. Conexão/configuração indisponível impede envio; não há sucesso simulado.
- Dados de participantes e credenciais nunca integram este repositório.

Configuração, exportação privada e proposta de apuração estão em [deploy/OPERACAO.md](deploy/OPERACAO.md). O arquivo real de configuração fica em `cuidar-private/config.php`, fora de `public_html`. O modelo é `deploy/config.example.php`; inicia com inscrições desativadas.

## Medição e resultados

Atualização de 17/10/2026: sessões de navegação, início do formulário, erros por categoria, confirmação no servidor e desistências estimadas após 30 minutos sem atividade. O painel privado em `/painel/` oferece filtros, evolução diária, origem, parceiros, CSV e impressão/PDF. A medição não registra o conteúdo dos campos nem altera a regra de uma participação por CPF. Visitas anteriores à implantação não podem ser reconstruídas.

Implantação, senha inicial do responsável, definições e limites estão em [deploy/MEDICAO.md](deploy/MEDICAO.md). A configuração de acesso fica fora de `public_html` e não integra este repositório. Testes adicionais: `php tests/metrics.php` e `node tests/tracking.cjs`.

## Prévia e testes

`php -S 127.0.0.1:8787 -t .`

`php tests/registration.php`

A suíte verifica CPF, campos, maioridade/aceite, janela da campanha no fuso correto, repetição de CPF, preservação do cadastro original e falha de banco. Usa dados sintéticos e SQLite em memória para as funções compartilhadas; a API de produção exige MySQL e deve ser verificada na hospedagem antes da ativação.

## Arquivos

- `index.html`: página, formulário, regulamento integral e privacidade.
- `poster.css`, `style.css`: identidade do cartaz, responsividade e animações.
- `app.js`: validação, integração HTTP e interações.
- `api/`: endpoint de inscrição e regras de participação.
- `assets/`: identidade original, 14 logos, marca da Rede Feminina, fontes e regulamentos Word/PDF.
- `deploy/`: esquema SQL, modelo sem credenciais, exportador CLI e orientações. Não enviar esta pasta à raiz pública.

Sem marketing automático, área individual ou pontos. Retenção dos dados e condição de presença no evento aguardam definição da organização. Não há ferramenta de sorteio implantada nesta versão.
