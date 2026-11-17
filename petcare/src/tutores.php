<?php
declare(strict_types=1);

/* Rotas de tutores. Consultas são públicas; criar, alterar e excluir exigem token. */

/** Busca um tutor pelo id (ou false). */
function buscarTutor(int $id): array|false
{
    $stmt = conectar()->prepare('SELECT id, nome, telefone, email FROM tutores WHERE id = ?');
    $stmt->execute([$id]);
    $t = $stmt->fetch();
    return $t ? formatarTutor($t) : false;
}

function formatarTutor(array $t): array
{
    return ['id' => (int)$t['id'], 'nome' => $t['nome'], 'telefone' => $t['telefone'], 'email' => $t['email']];
}

/** Outro tutor (que não seja $ignorarId) já usa esse e-mail? */
function emailTutorEmUso(string $email, int $ignorarId = 0): bool
{
    $stmt = conectar()->prepare('SELECT id FROM tutores WHERE email = ? AND id <> ?');
    $stmt->execute([$email, $ignorarId]);
    return (bool)$stmt->fetch();
}

function tutorOu404(int $id): array
{
    return buscarTutor($id) ?: erro(404, 'Tutor não encontrado');
}

/** GET /tutores */
function listarTutores(): never
{
    $linhas = conectar()->query('SELECT id, nome, telefone, email FROM tutores ORDER BY nome')->fetchAll();
    responder(200, ['total' => count($linhas), 'tutores' => array_map('formatarTutor', $linhas)]);
}

/** GET /tutores/{id} */
function mostrarTutor(int $id): never
{
    responder(200, tutorOu404($id));
}

/** GET /tutores/{id}/pets */
function listarPetsDoTutor(int $id): never
{
    tutorOu404($id);
    $stmt = conectar()->prepare(
        'SELECT id, nome, especie, raca, data_nascimento, peso_kg, tutor_id
           FROM pets WHERE tutor_id = ? ORDER BY nome'
    );
    $stmt->execute([$id]);
    $pets = array_map('formatarPet', $stmt->fetchAll());
    responder(200, ['tutor_id' => $id, 'total' => count($pets), 'pets' => $pets]);
}

/** POST /tutores */
function criarTutor(): never
{
    exigirToken();
    [$t, $erros] = validarTutor(lerJson());
    if ($erros) erroValidacao($erros);
    if (emailTutorEmUso($t['email'])) erro(409, 'Já existe um tutor com esse e-mail');

    try {
        conectar()->prepare('INSERT INTO tutores (nome, telefone, email) VALUES (?, ?, ?)')
            ->execute([$t['nome'], $t['telefone'], $t['email']]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) erro(409, 'Já existe um tutor com esse e-mail');   // corrida entre dois pedidos
        throw $e;
    }
    $id = (int)conectar()->lastInsertId();
    header("Location: tutores/$id");
    responder(201, ['id' => $id] + $t);
}

/** PUT /tutores/{id} */
function alterarTutor(int $id): never
{
    exigirToken();
    tutorOu404($id);
    [$t, $erros] = validarTutor(lerJson());
    if ($erros) erroValidacao($erros);
    if (emailTutorEmUso($t['email'], $id)) erro(409, 'Já existe outro tutor com esse e-mail');

    try {
        conectar()->prepare('UPDATE tutores SET nome = ?, telefone = ?, email = ? WHERE id = ?')
            ->execute([$t['nome'], $t['telefone'], $t['email'], $id]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1062) erro(409, 'Já existe outro tutor com esse e-mail');
        throw $e;
    }
    responder(200, ['id' => $id] + $t);
}

/**
 * DELETE /tutores/{id}
 * Regra escolhida: IMPEDIR a exclusão enquanto o tutor tiver pets (ON DELETE RESTRICT) -> 409.
 */
function excluirTutor(int $id): never
{
    exigirToken();
    tutorOu404($id);

    $stmt = conectar()->prepare('SELECT COUNT(*) FROM pets WHERE tutor_id = ?');
    $stmt->execute([$id]);
    $qtd = (int)$stmt->fetchColumn();
    if ($qtd > 0) {
        erro(409, "Não é possível excluir o tutor: ele ainda tem $qtd pet(s) cadastrado(s). Exclua os pets antes.");
    }

    try {
        conectar()->prepare('DELETE FROM tutores WHERE id = ?')->execute([$id]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1451) erro(409, 'Não é possível excluir o tutor: ele ainda tem pets cadastrados.');   // rede de segurança do banco
        throw $e;
    }
    responder(204);
}
