# Viano

Aplicação PHP/MySQL para gerir entregas, atribuir rotas e acompanhar estafetas. O projeto não usa Composer nem um framework PHP.

## Requisitos

- PHP 8.1 ou superior, com `pdo_mysql`, `curl` e `json` ativos.
- MySQL 8.0 ou superior, com suporte a índices espaciais InnoDB.
- Apache/WampServer com o `DocumentRoot` a apontar para esta pasta.
- Projeto Google Cloud com Maps JavaScript API, Geocoding API e Routes API ativos e faturação configurada.

## Instalação local

1. Importe `viano_db.sql` no phpMyAdmin.
2. Configure as variáveis de ambiente do Apache/PHP no VirtualHost. Não guarde chaves reais no repositório.

```apache
SetEnv VIANO_DB_HOST 127.0.0.1
SetEnv VIANO_DB_PORT 3306
SetEnv VIANO_DB_NAME viano_db
SetEnv VIANO_DB_USER root
SetEnv VIANO_DB_PASSWORD ""
SetEnv GOOGLE_MAPS_SERVER_KEY "chave-servidor"
SetEnv GOOGLE_MAPS_BROWSER_KEY "chave-browser"
```

Use uma chave Google de servidor restrita por IP e apenas às APIs Geocoding/Routes. Restrinja a chave de browser por HTTP referrer e apenas à Maps JavaScript API. Em produção, use utilizador MySQL próprio, password, HTTPS e chaves geridas fora da pasta pública.

3. Crie as contas iniciais. Gere cada hash no terminal PHP com `php -r "echo password_hash('uma-palavra-passe-forte', PASSWORD_DEFAULT), PHP_EOL;"` e use o resultado no `INSERT` correspondente.

```sql
INSERT INTO utilizadores (nome, email, password_hash, perfil)
VALUES ('Gestor Viano', 'gestor@exemplo.pt', '<HASH_GERADO_NO_PHP>', 'gestor');

INSERT INTO utilizadores (nome, email, password_hash, telefone, perfil)
VALUES ('Estafeta Viano', 'estafeta@exemplo.pt', '<HASH_GERADO_NO_PHP>', NULL, 'entregador');

SET @entregador_id = LAST_INSERT_ID();
INSERT INTO entregadores_perfil
  (utilizador_id, localizacao_inicial_lat, localizacao_inicial_lng, localizacao, morada_base)
VALUES
  (@entregador_id, 38.7223, -9.1393, ST_PointFromText('POINT(-9.1393 38.7223)'), 'Lisboa');
```

4. Abra a aplicação no endereço configurado no VirtualHost, por exemplo `http://localhost/`. O acesso inicial redireciona cada perfil para o respetivo portal.

## Funcionalidades

- `manager.php`: registo manual ou em lote (uma linha por entrega, campos separados por `;`), mapa de pendentes/localizações e atribuição de rotas.
- `driver.php`: sequência de paragens, navegação Google Maps, chegada, entrega/falha e partilha de GPS durante a rota.
- `api/otimizar_rota.php`: consulta a matriz Routes API, aplica vizinho mais próximo com melhoria 2-opt e grava rota/paragens numa transação. Aceita até 24 entregas por rota; é uma heurística prática, não uma garantia de ótimo global.
- `api/estado_rotas.php`: dados para polling; o gestor atualiza a cada 15 segundos e o estafeta a cada 12 segundos.
- Os horários e as estimativas usam o fuso `Europe/Lisbon`. A distância calculada termina na última entrega e não inclui regresso à base.

## Segurança e operação

Os endpoints de escrita exigem sessão, perfil autorizado e token CSRF. As consultas usam PDO com prepared statements; palavras-passe são verificadas com `password_verify`. O acompanhamento GPS exige permissão do browser e contexto seguro (HTTPS; `localhost` é aceite pelos browsers). A aplicação não inclui criação pública de contas.

Para testar a sintaxe no ambiente Wamp, execute `php -l` em cada ficheiro `.php`. Confirme também que as extensões PHP, as variáveis de ambiente do Apache e as restrições das duas chaves Google estão ativas.

Vou primeiro confirmar a pasta de trabalho e se já há algum código ou convenção local; depois implemento a estrutura Viano de forma compatível com o WampServer, protegendo credenciais e validando os endpoints.

Read memory

Read 

Ran terminal command: Get-Location; Get-ChildItem -Force | Select-Object Mode,Name

A pasta está praticamente vazia, por isso vou tratar isto como um projeto novo. A hipótese de implementação é uma aplicação PHP simples, sem framework, em que a sessão controla permissões e uma transação impede atribuições duplicadas; a verificação mais direta será validar a sintaxe PHP e executar os testes disponíveis depois de criar os ficheiros.

Ran terminal command: git status --short; where php; where composer; where node; dir /a

Vou começar pelo núcleo comum de segurança e configuração: PDO com credenciais por ambiente, sessão endurecida, proteção CSRF e respostas JSON consistentes. Isso fixa a fronteira de acesso que os restantes endpoints e páginas vão reutilizar; a primeira validação será a sintaxe deste ficheiro.

Apply Patch

Generating patch (113 lines) in 


```

```


Ran terminal command: php -l config.php

A configuração já está criada; a validação local confirmou que o PHP não está no `PATH`, pelo que não consigo executar `php -l` neste ambiente. Vou manter a implementação sem dependências de framework e avançar com o esquema relacional e a autenticação de sessão; depois valido o que estiver acessível e deixo explícitos os passos de arranque no WampServer.

Apply Patch

Generating patch (104 lines) in 


```

```


Generating patch (161 lines) in , , 


```

```

```

```

```

```
O esquema e os primeiros endpoints já cobrem perfis, entregas georreferenciadas, sessões e registo em lote. Estou a fechar agora o percurso mais sensível: calcular uma ordem de visitas com a matriz do Google e só gravar a rota depois de bloquear/revalidar entregas e estafeta, para evitar atribuições concorrentes.

Generating patch (238 lines) in 


```

```


Generating patch (134 lines) in , , 


```

```

```

```

```

```
A distribuição, o polling de progresso e as ações do condutor já estão ligados ao modelo transacional; também acrescentei a localização GPS atual, necessária para o mapa em tempo real. Agora estou a construir as duas interfaces e o fluxo de entrada, mantendo os segredos Google no servidor sempre que possível e deixando no browser apenas a chave pública restrita por domínio.

Generating patch (187 lines) in 


```

```


Generating patch (150 lines) in , 


```

```

```

```


Generating patch (291 lines) in , 


```

```

```

```


Generating patch (213 lines) in , 


```

```

```

```
As duas interfaces já estão ligadas: o gestor regista uma ou várias moradas e atribui rotas; o estafeta vê a próxima paragem, abre navegação e comunica chegada/resultado, com GPS partilhado durante uma rota ativa. Estou a rever agora compatibilidade SQL/Google e a acrescentar instruções de instalação e criação inicial de contas, que são necessárias para tornar o projeto executável no Wamp.

Generating patch (66 lines) in , , , , , , 


```

```

```

```

```

```

```

```

```

```

```

```

```

```


Generating patch (68 lines) in , 


```

```

```

```
A instalação está documentada e o SQL/documentação ficam bloqueados por HTTP; também corrigi a geocodificação e a serialização de pontos para não depender da configuração regional do PHP. Vou verificar agora diagnósticos do workspace e procurar PHP/Node no ambiente para executar as validações que estiverem disponíveis.

Ran terminal command: where node & dir C:\wamp64\bin\php

Checked workspace, no problems found

Generating patch (24 lines) in , , 


```

```

```

```

```

```
A revisão acrescentou um limite de frescura para posições GPS, data de rota no fuso de Lisboa e proteção adicional ao servir a pasta do projeto. O workspace não tem PHP/Wamp nem Node disponíveis, por isso não consigo executar lint nem abrir a aplicação aqui; vou fazer a última verificação de diagnósticos e confirmar a estrutura final antes de lhe passar os passos de arranque.

Checked workspace, no problems found

Read 

Read 

Read 

Os diagnósticos do editor estão limpos e a estrutura contém os endpoints e páginas previstos. Antes de fechar, vou retirar dois detalhes visuais que não ajudam neste dashboard operacional e confirmar novamente os diagnósticos; a validação runtime continua dependente do PHP/Node/Wamp, ausentes neste ambiente.

Generating patch (17 lines) in 


```

```


Checked workspace, no problems found

Implementei o Viano com entrada por perfil, dashboard de gestão, interface móvel do estafeta e endpoints protegidos por sessão, perfil e CSRF.

- Base de dados e configuração: `viano_db.sql`, `config.php`
- APIs: `otimizar_rota.php`, `atualizar_estado.php`, `estado_rotas.php`, além dos endpoints de autenticação, entregas e localização.
- Interfaces: `manager.php`, `driver.php` e `index.php`
- Instalação, configuração Google e criação das contas iniciais: `README.md`

A otimização usa a matriz Google Routes com vizinho mais próximo e melhoria 2-opt; aceita até 24 entregas por rota e não garante o ótimo global. O acompanhamento atualiza por polling.

**Verificação:** o editor não reporta erros. Não consegui executar o lint PHP nem iniciar a aplicação: PHP/WampServer e Node não estão disponíveis neste ambiente. Para testar, importe o SQL, configure as variáveis e chaves Google e siga o README no WampServer.

Made changes.