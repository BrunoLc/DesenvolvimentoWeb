# PetCare · API REST em PHP

API da clínica veterinária PetCare para cadastrar **tutores** (donos) e **pets**.
Qualquer pessoa consulta; só funcionários autenticados cadastram, alteram e excluem.
Feita em **PHP puro** (sem framework), **MySQL** com PDO e autenticação por **token**.

## Integrantes

- Nome do aluno 1
- Nome do aluno 2
- Nome do aluno 3

## Como rodar

Requisitos: **XAMPP** (Apache + MySQL) com **PHP 8.1 ou superior**.

1. Ligue o **Apache** e o **MySQL** no painel do XAMPP.
2. Clone o repositório dentro da pasta `htdocs` (ex.: `C:\xampp\htdocs`).
3. Crie o banco: abra o **phpMyAdmin** (`http://localhost/phpmyadmin`), vá em **Importar** e escolha o arquivo `petcare/banco.sql`.
   Pelo terminal: `mysql -u root < petcare/banco.sql`.
   O script apaga e recria o banco `petcare` do zero, já com os dados de exemplo.
4. A conexão está em `petcare/src/conexao.php` (usuário `root`, sem senha, que é o padrão do XAMPP). Se o seu MySQL tem senha, troque a constante `DB_SENHA`.
5. Acesse a API. O endereço depende de onde a pasta `petcare` ficou:
   - copiando a pasta `petcare` direto para `htdocs`: **http://localhost/petcare/**
   - clonando o repositório inteiro: `http://localhost/<nome-do-repositorio>/petcare/`

   Teste abrindo `/tutores` no navegador: deve aparecer a lista em JSON.
6. Abra `petcare/requisicoes.http` no VS Code (extensão **REST Client**) e rode as requisições de cima para baixo. Se o endereço do passo 5 for diferente de `http://localhost/petcare`, ajuste a variável `@base` no topo do arquivo.

> O Apache precisa ler o `.htaccess` (no XAMPP já vem habilitado, com `mod_rewrite` e `AllowOverride All`). Ele tem a linha que repassa o cabeçalho `Authorization` para o PHP e manda toda requisição para o `index.php`.

## Usuário de teste (professor)

| E-mail | Senha |
|---|---|
| `professor@petcare.com` | `petcare123` |

## Estrutura

```
petcare/
├── index.php          roteador (método + caminho -> função da rota)
├── .htaccess          repasse do Authorization e reescrita para o index.php
├── banco.sql          cria o banco petcare e os dados de exemplo
├── requisicoes.http   uma requisição para cada rota, com os casos de erro
├── README.md
├── docs/der.png       diagrama do banco
└── src/
    ├── conexao.php    conectar() com PDO
    ├── ajuda.php      responder(), erro(), lerJson()
    ├── auth.php       login, logout e exigirToken()
    ├── validacao.php  validações de tutor e de pet
    ├── tutores.php    rotas de tutores
    └── pets.php       rotas de pets
```

## Rotas

Todas respondem JSON. Erros seguem `{"detail": "mensagem"}`; no 422 vem também `"erros": [ ... ]` com a lista de problemas.
Rotas com **token** exigem o cabeçalho `Authorization: Bearer <token>`.

| Método | Rota | Token | Sucesso | Erros |
|---|---|---|---|---|
| POST | `/login` | não | 200 `{token, tipo, expira_em}` | 400, 401, 422 |
| POST | `/logout` | sim | 204 | 401 |
| GET | `/tutores` | não | 200 `{total, tutores}` | |
| GET | `/tutores/{id}` | não | 200 | 404 |
| GET | `/tutores/{id}/pets` | não | 200 `{tutor_id, total, pets}` | 404 |
| POST | `/tutores` | sim | 201 com o tutor e o `id` criado | 400, 401, 409, 422 |
| PUT | `/tutores/{id}` | sim | 200 com o tutor alterado | 400, 401, 404, 409, 422 |
| DELETE | `/tutores/{id}` | sim | 204 | 401, 404, 409 |
| GET | `/pets` (`?especie=gato`) | não | 200 `{total, pets}` | |
| GET | `/pets/{id}` | não | 200 com `tutor_nome` (JOIN) | 404 |
| POST | `/pets` | sim | 201 com o pet e o `id` criado | 400, 401, 422 |
| PUT | `/pets/{id}` | sim | 200 com o pet alterado | 400, 401, 404, 422 |
| DELETE | `/pets/{id}` | sim | 204 | 401, 404 |
| qualquer outra | | | | 404 `Rota não encontrada` |

Validações (sempre 422 com a lista de erros):

- **tutor:** `nome` de 2 a 100 caracteres, `telefone` obrigatório, `email` válido e único (repetido dá 409).
- **pet:** `nome` de 2 a 60 caracteres; `especie` entre `cachorro`, `gato`, `ave`, `roedor`, `outro`; `peso_kg` maior que 0 e até 200; `data_nascimento` no formato `AAAA-MM-DD` e não futura; `tutor_id` de um tutor que existe; `raca` é opcional.

## Segurança com token

1. **Login:** `POST /login` com `{"email": "...", "senha": "..."}`. A senha é conferida com `password_verify`. Se estiver certa, a API gera o token com `bin2hex(random_bytes(32))` (64 caracteres aleatórios), grava na tabela `tokens` com validade de **2 horas** e responde `{"token": "...", "tipo": "Bearer", "expira_em": "..."}`.
2. **Uso:** as rotas protegidas recebem `Authorization: Bearer <token>`.
3. **Verificação:** a função `exigirToken()` é a primeira coisa que cada rota protegida chama. Sem o cabeçalho: 401 `Token não informado`. Token que não existe ou expirou: 401 `Token inválido ou expirado`.
4. **Logout:** `POST /logout` apaga o token da tabela; depois disso o mesmo token dá 401.

Cuidados adotados:

- a senha é gravada com `password_hash` e nunca aparece em nenhuma resposta;
- o token é sempre aleatório (nunca o id, o e-mail ou a senha);
- login com e-mail ou senha errados responde a mesma mensagem (`E-mail ou senha inválidos`), sem revelar quais e-mails existem;
- todo SQL usa **prepared statements** (`?`); nenhum valor do cliente é colado no SQL;
- tokens vencidos são apagados da tabela a cada novo login.

## Regra ao excluir um tutor: impedir a exclusão

Escolhemos **impedir**: `ON DELETE RESTRICT` na chave estrangeira `pets.tutor_id`. Se o tutor ainda tem pets, `DELETE /tutores/{id}` responde **409** com a mensagem explicando o motivo. Para excluir, primeiro apague (ou mova) os pets.

Justificativa: numa clínica, o pet carrega histórico que não pode sumir por engano. Com `ON DELETE CASCADE`, um clique errado apagaria o tutor e todos os pets dele em silêncio, sem volta. Com `RESTRICT`, a recepção é obrigada a decidir o que fazer com cada pet. A API confere antes de excluir e o banco garante a regra mesmo se alguém tentar excluir direto no MySQL.

## Banco de dados

O `banco.sql` cria 4 tabelas com `PRIMARY KEY`, `FOREIGN KEY`, `UNIQUE` e `NOT NULL`, e insere **6 tutores**, **12 pets** (Mariana tem 3, Carlos, Fernanda, Roberto e Juliana têm 2 ou 3) e o usuário de teste. O diagrama está em `docs/der.png`.

| Tabela | Colunas | Regras |
|---|---|---|
| `tutores` | id, nome, telefone, email, criado_em | e-mail único; nome e telefone obrigatórios |
| `pets` | id, nome, especie, raca, data_nascimento, peso_kg, tutor_id, criado_em | `tutor_id` -> `tutores` (RESTRICT); raça pode ser nula; peso entre 0 e 200 |
| `usuarios` | id, nome, email, senha_hash, criado_em | e-mail único; senha só como hash |
| `tokens` | id, usuario_id, token, expira_em, criado_em | token único; `usuario_id` -> `usuarios` (CASCADE) |

O mesmo banco pode ser reaproveitado no Trabalho 2 (FastAPI).

## Observações

- O e-mail do tutor é guardado em minúsculas, para `Ana@x.com` e `ana@x.com` contarem como o mesmo e-mail.
- A API envia cabeçalhos CORS e responde `OPTIONS` com 204, para o site da clínica (em outra origem) conseguir consumi-la.
- Erros inesperados viram `500 Erro interno do servidor`, sem expor detalhes do banco.
