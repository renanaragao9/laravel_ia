# Laravel IA: blog diário de estatística da Lotece (Loteria Estadual do Ceará)

Sistema que guarda o **histórico de resultados da Lotece (Loteria Estadual do Ceará)** e, todo dia, usa um **agente de IA** (LLM + MCP + RAG) para escrever um post de blog com a análise estatística do dia e uma curiosidade de "palpite". O post nasce como **rascunho**: você revisa no painel e só então publica.

- **Stack:** Laravel 13, PHP 8.3+, Filament 5 (painel), `laravel/mcp` (servidor e cliente MCP), OpenRouter (LLM), fila e agendador do Laravel.
- **Blog público:** `/` · **Painel:** `/admin`

## Leia isto primeiro

- **Sorteio é aleatório.** Nenhum modelo prevê o que vai cair. O sistema faz estatística **descritiva** do passado (frequência, atraso, perfil por dia da semana, mesma data em outros anos) e os "palpites" são apresentados como **curiosidade**, nunca como previsão. Todo post leva um aviso fixo e o blog mostra o aviso no topo e no rodapé.
- **Aspecto legal.** Os resultados são da Lotece, divulgados em lotece.com.br, e este projeto **não é um canal oficial** dela. Publicar estatística de resultados já divulgados é comum, mas **divulgar palpites** pode ser interpretado como incentivo ao jogo. O conteúdo é para maiores de 18 anos, não cita casas de apostas, links ou valores. **Confira com alguém da área jurídica antes de publicar.**
- **A IA não inventa números:** as regras do agente exigem que todo número venha de uma ferramenta MCP, e o painel mostra, em cada rascunho, quais ferramentas foram consultadas. Mesmo assim, **revise sempre** antes de publicar.

## Como funciona

```
planilha CSV/XLSX ─┐
coleta automática ─┴─► draws / draw_results (histórico)
                              │
                              ▼
                  Servidor MCP "bicho" (8 ferramentas)  ◄──── buscar_conhecimento ◄── RAG (índice TF-IDF local)
                              ▲                                                         ▲
                              │ cliente MCP                                             │ guia de estilo, animais,
                              │                                                         │ significados, regras, posts anteriores
                  Agente de IA (OpenRouter) ── laço de ferramentas ──► rascunho do post
                                                                              │
                                              revisão no painel ─► Publicar ─► blog público (/)
                                                                              └► o post entra na base de conhecimento (RAG)
```

Todo dia, o agendador faz: **06:30** coleta os resultados → **06:50** treina o índice RAG → **07:00** gera o rascunho do dia.

### Os três pilares

| Pilar | O que é aqui |
|---|---|
| **LLM** | OpenRouter (API compatível com OpenAI): `app/Services/Llm/OpenRouterClient.php`. O modelo vem de `OPENROUTER_MODEL`. |
| **MCP** | Um servidor MCP no próprio Laravel (`app/Mcp/Servers/BichoServer.php`) com ferramentas sobre o histórico e a base de conhecimento. O agente se conecta a ele como **cliente MCP** (`McpToolGateway`), por padrão iniciando `php artisan mcp:start bicho` (stdio). Qualquer outro cliente MCP (como o Claude Desktop) também pode usá-lo. |
| **RAG** | Índice **treinado localmente no banco**, sem API de embeddings: os documentos viram trechos, o treino calcula o vocabulário e o peso **TF-IDF** de cada trecho, e a busca usa **similaridade de cosseno**. Fica em `knowledge_documents`, `knowledge_chunks` e `knowledge_terms`. |

### Ferramentas MCP

| Ferramenta | Para que serve |
|---|---|
| `resumo_da_base` | Data de hoje, dia da semana e intervalo coberto pela base. |
| `resultados_do_dia` | Todas as extrações de uma data (prêmio, milhar, dezena e animal). |
| `frequencia` | Quantas vezes cada animal ou dezena saiu nos últimos N dias, com porcentagem. |
| `atrasados` | Quem está há mais tempo sem sair (em dias e em extrações). |
| `perfil_dia_da_semana` | Frequência só nos dias da mesma semana (por exemplo, só segundas). |
| `historico_mesma_data` | O que saiu no mesmo dia e mês em anos anteriores. |
| `consultar_animal` | Dezenas de um grupo, por número, nome ou dezena. |
| `buscar_conhecimento` | Busca RAG: tabela dos animais, significados populares, regras, guia de estilo e posts anteriores. |

Todas devolvem JSON com um aviso de que os dados são históricos e não uma previsão.

## Instalação

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan rag:train
php artisan serve
```

`migrate --seed` cria as tabelas, o admin (de `APP_ADMIN_*`), os **25 animais** e os documentos iniciais da base de conhecimento (tabela dos grupos, significados populares, regras e termos, guia de estilo). Depois acesse http://localhost:8000/admin.

> O `.env.example` usa SQLite. Se usar MySQL/PostgreSQL, lembre que `migrate:fresh` **apaga todas as tabelas** do banco configurado. Confira o `.env` antes.

Para o pipeline diário e a geração pelo painel:

```bash
php artisan queue:work         # processa a geração de posts pedida no painel
php artisan schedule:work      # em desenvolvimento (em produção: cron com schedule:run a cada minuto)
```

Em produção, o cron é: `* * * * * cd /caminho/do/projeto && php artisan schedule:run >> /dev/null 2>&1`.

## Configuração (.env)

| Variável | Para que serve |
|---|---|
| `APP_TIMEZONE` | Fuso do sistema e do agendador (padrão `America/Sao_Paulo`). |
| `APP_ADMIN_NAME` / `APP_ADMIN_MAIL` / `APP_ADMIN_PASSWORD` | Admin criado pelo seeder. **Troque a senha fora do ambiente local.** |
| `OPENROUTER_API_KEY` | Chave do OpenRouter. Sem ela, nenhum post é gerado. |
| `OPENROUTER_MODEL` | Modelo, no formato do OpenRouter. O padrão `openrouter/auto` escolhe um modelo sozinho; para o agente usar ferramentas, prefira um modelo com suporte a *tool calling*. |
| `OPENROUTER_MAX_STEPS` | Máximo de rodadas de ferramentas por post (padrão 8). |
| `MCP_BICHO_URL` / `MCP_BICHO_TOKEN` | Opcionais. Se `MCP_BICHO_URL` estiver vazio, o agente inicia o servidor MCP localmente (stdio). Se definido, usa o servidor HTTP com o token. |
| `RESULTS_DEFAULT_LOTTERY` | Loteria padrão ao importar planilhas sem a coluna `loteria`. |
| `RESULTS_DRIVER` | `lotece` (padrão, Loteria Estadual do Ceará) ou `html` (raspagem genérica). |
| `LOTECE_URL`, `LOTECE_DELAY_MS` | Endereço e pausa entre consultas do histórico (padrão 1000 ms). |
| `ANALYSIS_MAX_POSITION` | Prêmios considerados nas análises (padrão 5; a Lotece sorteia 10). |
| `RESULTS_SCRAPER_*` | Raspagem genérica (só com `RESULTS_DRIVER=html`). |

## Alimentando o histórico

### Base inicial incluída

`resources/data/resultados-2026-01-01_2026-11-03.xlsx` traz o histórico da Loteria Estadual do Ceará (Lotece) de 01/01 a 03/11/2026: 258 dias com sorteio, 1.025 extrações (Manhã, Tarde, Tarde II e Noite) e 10.250 linhas (10 prêmios por extração). Colunas: `data, loteria, horario, premio, milhar`. Domingos e feriados não têm sorteio. Para carregar num banco novo:

```bash
php artisan draws:import resources/data/resultados-2026-01-01_2026-11-03.xlsx
```

Reimportar é seguro: não duplica data, loteria e horário.

Para gerar outro período a partir do site da Lotece: `php artisan draws:backfill 2026-03-01 2026-03-31` (veja a seção da Lotece abaixo).

### Importação de planilha (recomendado)

No painel, **Dados → Sorteios → Importar planilha**, ou pelo terminal:

```bash
php artisan draws:import caminho/historico.xlsx
```

Aceita `.csv` (separador `;` ou `,`) e `.xlsx`. Dois formatos:

- **Uma linha por prêmio:** colunas `data`, `loteria` (opcional), `horario` (opcional), `premio` (1 a 5) e `milhar`.
- **Uma linha por sorteio:** colunas `data`, `horario` e `p1` a `p5`.

Datas em `dd/mm/aaaa` ou `aaaa-mm-dd`. A milhar é completada com zeros (`12` vira `0012`). Reimportar **atualiza** os valores sem duplicar, e linhas inválidas são ignoradas com o número da linha no aviso. Um histórico de mais de 13 mil resultados importa em cerca de 5 segundos.

### Lotece (Ceará): histórico e coleta diária

Fonte padrão: https://lotece.com.br/resultados/ (não tem API JSON; lemos o HTML por data). Extrações: Manhã, Tarde, Tarde II e Noite, 10 prêmios cada.

```bash
php artisan draws:backfill                      # 1º de janeiro até ontem, gera um .xlsx em storage/app/private/exports
php artisan draws:backfill 2026-03-01 2026-03-31 --xlsx=março.xlsx
php artisan draws:backfill --import             # além do xlsx, grava no banco
```

Há 1 s de pausa entre consultas e cache por dia em `storage/app/private/draw-cache` (`--refresh` ignora). Dias sem sorteio (domingos/feriados) ficam vazios. Depois disso o agendador roda `draws:fetch` às 06:30 e 22:30 (ontem e hoje), seguido de `rag:train` e `posts:generate`.

### Coleta por raspagem genérica (opcional)

Lê uma tabela HTML de um site de resultados. É genérica e **precisa ser configurada para o site que você escolher**:

```
RESULTS_SCRAPER_URL=https://exemplo.com/resultados/{Y}/{m}/{d}   # aceita {date}, {d}, {m} e {Y}
RESULTS_SCRAPER_ROWS_XPATH=//table//tr                          # onde estão as linhas
RESULTS_SCRAPER_TIME_COLUMN=0                                    # coluna do horário
RESULTS_SCRAPER_PRIZE_COLUMNS=1,2,3,4,5                          # colunas do 1º ao 5º prêmio
```

Linhas incompletas e cabeçalhos são ignorados. Use `php artisan draws:fetch 2026-11-04` ou o botão **Coletar resultados** (só aparece com a URL configurada). **Respeite os termos de uso e o `robots.txt` do site.** A coleta por raspagem quebra quando o site muda o layout; a planilha é mais estável.

## Base de conhecimento (RAG)

Em **Dados → Base de conhecimento** você edita os documentos que o agente consulta (tabela dos animais, significados, regras, guia de estilo). Separe os assuntos por linhas em branco: cada parágrafo vira um trecho pesquisável. Depois de criar ou editar, clique em **Treinar índice** (ou rode `php artisan rag:train`, que também roda todo dia às 06:50).

Posts **publicados** entram sozinhos na base (tipo "Post publicado"), o que ajuda o agente a manter o estilo e a evitar repetir assuntos. Ao despublicar, saem da base.

O guia de estilo (`Guia de estilo e regras editoriais dos posts`) é recuperado a cada geração e injetado no prompt do agente.

## Gerando e publicando posts

- **Automático:** `posts:generate` roda todo dia às 07:00 e cria o rascunho de hoje. Se já existir um post para a data, não faz nada.
- **Pelo painel:** **Conteúdo → Posts → Gerar post** (escolhe a data; "substituir" regera um rascunho existente). Roda em segundo plano e precisa do `queue:work`.
- **Pelo terminal:** `php artisan posts:generate 2026-11-04` (`--force` regera um rascunho).
- Um post **publicado nunca é sobrescrito**: despublique antes de gerar de novo.

Na página do rascunho você vê o texto renderizado, os palpites, o modelo e os tokens usados, **as ferramentas MCP que a IA consultou** (com os argumentos) e os documentos de estilo recuperados. Edite o texto se precisar e clique em **Publicar**. Só posts publicados (e com data de publicação já passada) aparecem em `/`.

O texto é sempre acrescido do aviso legal se o modelo não o escrever, e o Markdown é renderizado sem HTML nem links inseguros.

## Usando o servidor MCP com outros clientes

- **Local (stdio):** `php artisan mcp:start bicho`
- **HTTP:** `POST /mcp/bicho` com `Authorization: Bearer <MCP_BICHO_TOKEN>`. Sem token configurado o endpoint responde 403, com token errado 401.
- Para inspecionar as ferramentas: `php artisan mcp:inspector bicho`.

## Estrutura

```
app/
├── Contracts/                 LlmClient, ToolGateway, ResultSource
├── Console/Commands/          draws:import, draws:fetch, rag:train, posts:generate
├── Filament/
│   ├── Actions/               Importar, Coletar, Gerar post, Publicar, Despublicar, Treinar índice
│   ├── Filters/<Model>/       Filtros das tabelas
│   ├── Resources/             Draws, Posts, Knowledge, Users
│   └── Widgets/OverviewStats  Resumo do dashboard
├── Http/Controllers/BlogController.php     Blog público (controller fino)
├── Mcp/                       Servidor BichoServer e as 8 ferramentas
├── Models/                    Animal, Draw, DrawResult, KnowledgeDocument/Chunk/Term, Post, User
├── Services/
│   ├── Draws/                 StoreDraw, ImportDraws, HtmlTableResultSource, FetchDraws
│   ├── Stats/DrawStatsService Estatística descritiva (base de tudo que o agente diz)
│   ├── Rag/                   TrainKnowledge, RetrieveKnowledge
│   ├── Llm/                   OpenRouterClient, McpToolGateway
│   ├── Posts/                 PostAgent (laço LLM + ferramentas), GeneratePost, PublishPost
│   └── Blog/                  ListPublishedPosts, ShowPost
└── Support/                   Bicho (regras dos grupos), Rag (Tokenizer, Chunker), Llm, Blog (Disclaimer)
```

### Banco de dados

| Tabela | Conteúdo |
|---|---|
| `animals` | Os 25 grupos e as quatro dezenas de cada um. |
| `draws` | Uma extração: data, loteria, horário, origem (`import` ou `scraper`). Única por data + loteria + horário. |
| `draw_results` | Os cinco prêmios de cada extração: milhar, centena, dezena e grupo (derivados). |
| `knowledge_documents` / `knowledge_chunks` / `knowledge_terms` | Documentos, trechos com vetor TF-IDF e vocabulário com IDF (o índice RAG). |
| `posts` | O post do dia: Markdown, status, palpites, contexto da geração (ferramentas consultadas), modelo e tokens. |

Regra dos grupos: a dezena define o animal, com quatro dezenas por grupo (`01-04` Avestruz ... `97-00` Vaca). A dezena `00` pertence à Vaca.

## Testes

```bash
php artisan test
```

SQLite em memória, sem rede e sem chave de API (o OpenRouter é simulado com `Http::fake`, e o agente é testado com um LLM e um gateway roteirizados). Cobertura: regras dos grupos, importação (CSV, XLSX, idempotência, linhas inválidas), coleta, estatísticas, RAG (treino e busca com o seeder real), as 8 ferramentas MCP e a autenticação do endpoint, o cliente OpenRouter, o laço do agente (ferramentas, JSON com cerca de código, nova tentativa, limite de rodadas, palpites saneados), geração, publicação, comandos, agendamento, blog (Markdown seguro, rascunhos ocultos) e o painel.

O que os testes **não** fazem é chamar o OpenRouter de verdade: a primeira geração com a sua chave é o teste real do modelo escolhido.

## Problemas comuns

| Sintoma | Causa e solução |
|---|---|
| "OPENROUTER_API_KEY não configurada" | Defina a chave no `.env` e rode `php artisan config:clear`. |
| O agente não usa ferramentas / texto genérico | O modelo não suporta *tool calling*. Troque `OPENROUTER_MODEL` por um que suporte. |
| Posts gerados pelo painel não aparecem | O `queue:work` não está rodando. |
| O pipeline diário não roda | Falta o cron com `schedule:run` (ou `schedule:work` em desenvolvimento). |
| Post com números estranhos | Confira em "Como a IA chegou a este texto" quais ferramentas foram chamadas e se a base tem dados do período. |
| "Não há resultados cadastrados" | A base está vazia ou sem aquela data. Importe a planilha. |
| Busca RAG sem resultados | Rode `php artisan rag:train` (o índice precisa ser treinado depois de editar documentos). |
| Importação ignora linhas | Veja o aviso: cabeçalho fora do formato, data inválida, prêmio fora de 1 a 5 ou milhar com mais de 4 dígitos. |
