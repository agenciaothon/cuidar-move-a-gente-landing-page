# Cuidar Move a Gente — protótipo da landing page

Protótipo estático, responsivo, com identidade original da campanha e formulário demonstrativo. Atualização: 29/09/2026.

## Acesso

[Visualizar o protótipo](https://agenciaothon.github.io/cuidar-move-a-gente-landing-page/)

Publicado no GitHub Pages a partir da branch `main`, pasta raiz.

## Arquivos

- `index.html`: página, conteúdo e regulamento integral da minuta V4.
- `style.css`: estrutura, composição e interações visuais de base.
- `poster.css`: refinamento conforme o cartaz, paleta, Montserrat variável, oferta com presente e adaptações móveis.
- `app.js`: duas etapas do cadastro, máscaras, validação e diálogos.
- `assets`: selo 3D e textura enviados pelo cliente, marca Trok, imagens originais extraídas do SVG do cartaz (presente, fundo e mulher com laço), Montserrat sob licença OFL, fontes legadas Bricolage Grotesque, regulamento PDF/Word e favicon.

## Prévia local

Execute `python3 -m http.server 8787 --bind 127.0.0.1 --directory .` nesta pasta e abra http://127.0.0.1:8787/.

Não há build nem dependências de execução externas. As fontes são servidas junto com a página. A apresentação anterior e seu repositório permanecem separados.

## Escopo demonstrativo

O formulário não realiza inscrições, não envia dados e não usa armazenamento local ou cookies. Os campos são apagados na conclusão, no reinício e na saída da página. A política de conteúdo bloqueia submissão nativa e conexões iniciadas pela página. O botão de envio é habilitado apenas após os listeners JavaScript estarem instalados. Usar dados fictícios ou o botão “Usar dados de exemplo”.

Não há protocolo, saldo de pontos ou chances simulados. A geração de chances, datas-limite, composição dos prêmios e demais decisões abertas seguem indicadas na minuta. Marketing não faz parte da inscrição demonstrativa e não há compartilhamento de leads.

## Antes de transformar em cadastro oficial

Concluir as definições da minuta; inserir os 14 nomes/logos e compromissos individuais; implementar serviço de cadastro e validação de compras com controle de acesso, deduplicação e área individual protegida; finalizar aviso de privacidade e canais oficiais. Somente então substituir os textos e o comportamento demonstrativos.

Para adicionar logos, substituir cada `.logo-placeholder` por uma imagem com nome da empresa no atributo `alt`, mantendo a área `.partner-logos`. Atualizar as opções de `#partner` com as empresas e unidades elegíveis.

## Verificação

Fluxo de cadastro em duas etapas, erro de campos obrigatórios, máscaras, validação de CPF, limpeza dos campos, diálogos, navegação, layout móvel e comparação do regulamento com a minuta V4. Animações respeitam `prefers-reduced-motion`.

## Refinamento de 29/09

O presente destaca os 10% de desconto da Trok em todos os serviços e a oxi-sanitização grátis exclusivamente para mulheres. O botão da oferta leva às orientações da Trok; WhatsApp oficial ainda não informado. A minuta V4 inclui esse benefício e mantém as condições operacionais abertas para definição.

Paleta extraída do SVG: creme `#fce6e6`, rosa `#fad6d8`, coral `#ee737f` e rosa antigo `#a15061`. O cartaz usa Fractul; a página usa Montserrat variável como aproximação geométrica de uso aberto, com maior peso nos títulos. Os elementos gráficos originais foram reaproveitados sem redesenho.
