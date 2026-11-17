<?php
declare(strict_types=1);

/*
 * Autenticação por token.
 *   1. POST /login   confere a senha (password_verify), cria um token aleatório e grava na tabela tokens
 *   2. rotas protegidas chamam exigirToken() logo no início
 *   3. POST /logout  apaga o token
 */

const TOKEN_VALIDADE_SEGUNDOS = 2 * 60 * 60;   // 2 horas

/** POST /login */
function rotaLogin(): never
{
    $d = lerJson();

    $erros = [];
    $email = isset($d['email']) && is_string($d['email']) ? strtolower(trim($d['email'])) : '';
    $senha = isset($d['senha']) && is_string($d['senha']) ? $d['senha'] : '';
    if ($email === '') $erros[] = 'email é obrigatório';
    if ($senha === '') $erros[] = 'senha é obrigatória';
    if ($erros) erroValidacao($erros);

    $stmt = conectar()->prepare('SELECT id, nome, senha_hash FROM usuarios WHERE email = ?');
    $stmt->execute([$email]);
    $usuario = $stmt->fetch();

    // Mesmo quando o e-mail não existe fazemos uma conferência de senha (contra um hash falso),
    // para a resposta demorar parecido e não revelar quais e-mails estão cadastrados.
    $hash = $usuario['senha_hash'] ?? '$2y$10$y4Tq57hWsfM0w8.f.GjapeyKFlP9Ykxa8f00qchzcF1.hAINAvyyG';
    $senhaOk = password_verify($senha, $hash);

    if (!$usuario || !$senhaOk) {
        erro(401, 'E-mail ou senha inválidos');   // a mesma mensagem nos dois casos
    }

    $pdo = conectar();
    $agora = date('Y-m-d H:i:s');
    $pdo->prepare('DELETE FROM tokens WHERE expira_em <= ?')->execute([$agora]);   // limpeza de tokens vencidos

    $token    = bin2hex(random_bytes(32));                       // 64 caracteres aleatórios
    $expiraEm = date('Y-m-d H:i:s', time() + TOKEN_VALIDADE_SEGUNDOS);
    $pdo->prepare('INSERT INTO tokens (usuario_id, token, expira_em) VALUES (?, ?, ?)')
        ->execute([(int)$usuario['id'], $token, $expiraEm]);

    responder(200, ['token' => $token, 'tipo' => 'Bearer', 'expira_em' => $expiraEm]);
}

/** Pega o cabeçalho Authorization, qualquer que seja a forma como o Apache o entregou. */
function cabecalhoAuthorization(): string
{
    if (!empty($_SERVER['HTTP_AUTHORIZATION']))          return (string)$_SERVER['HTTP_AUTHORIZATION'];
    if (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) return (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $nome => $valor) {
            if (strtolower($nome) === 'authorization') return (string)$valor;
        }
    }
    return '';
}

/**
 * Confere o token do cabeçalho "Authorization: Bearer <token>".
 * Chamada no início de cada rota protegida. Responde 401 se faltar ou for inválido;
 * se estiver tudo certo, devolve os dados do usuário dono do token.
 */
function exigirToken(): array
{
    header('WWW-Authenticate: Bearer');
    $cab = trim(cabecalhoAuthorization());

    if ($cab === '') {
        erro(401, 'Token não informado');
    }
    if (!preg_match('/^Bearer\s+(\S+)$/i', $cab, $m)) {
        erro(401, 'Token inválido ou expirado');
    }

    $stmt = conectar()->prepare(
        'SELECT u.id, u.nome, u.email, t.token
           FROM tokens t
           JOIN usuarios u ON u.id = t.usuario_id
          WHERE t.token = ? AND t.expira_em > ?'
    );
    $stmt->execute([$m[1], date('Y-m-d H:i:s')]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        erro(401, 'Token inválido ou expirado');
    }
    header_remove('WWW-Authenticate');
    return $usuario;
}

/** POST /logout */
function rotaLogout(): never
{
    $usuario = exigirToken();
    conectar()->prepare('DELETE FROM tokens WHERE token = ?')->execute([$usuario['token']]);
    responder(204);
}
