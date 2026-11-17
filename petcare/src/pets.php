<?php
declare(strict_types=1);

/* Rotas de pets. Consultas são públicas; criar, alterar e excluir exigem token. */

const SQL_PET = 'SELECT p.id, p.nome, p.especie, p.raca, p.data_nascimento, p.peso_kg, p.tutor_id, t.nome AS tutor_nome
                   FROM pets p
                   JOIN tutores t ON t.id = p.tutor_id';

function formatarPet(array $p): array
{
    $pet = [
        'id'              => (int)$p['id'],
        'nome'            => $p['nome'],
        'especie'         => $p['especie'],
        'raca'            => $p['raca'],
        'data_nascimento' => $p['data_nascimento'],
        'peso_kg'         => (float)$p['peso_kg'],
        'tutor_id'        => (int)$p['tutor_id'],
    ];
    if (array_key_exists('tutor_nome', $p)) {
        $pet['tutor_nome'] = $p['tutor_nome'];
    }
    return $pet;
}

/** Busca um pet com o nome do tutor (JOIN). */
function buscarPet(int $id): array|false
{
    $stmt = conectar()->prepare(SQL_PET . ' WHERE p.id = ?');
    $stmt->execute([$id]);
    $p = $stmt->fetch();
    return $p ? formatarPet($p) : false;
}

function petOu404(int $id): array
{
    return buscarPet($id) ?: erro(404, 'Pet não encontrado');
}

/** GET /pets   (filtro opcional ?especie=gato) */
function listarPets(): never
{
    $sql = SQL_PET;
    $params = [];
    $especie = isset($_GET['especie']) && is_string($_GET['especie']) ? mb_strtolower(trim($_GET['especie'])) : '';
    if ($especie !== '') {
        $sql .= ' WHERE p.especie = ?';
        $params[] = $especie;
    }
    $stmt = conectar()->prepare($sql . ' ORDER BY p.nome');
    $stmt->execute($params);
    $pets = array_map('formatarPet', $stmt->fetchAll());
    responder(200, ['total' => count($pets), 'pets' => $pets]);
}

/** GET /pets/{id} */
function mostrarPet(int $id): never
{
    responder(200, petOu404($id));
}

/** POST /pets */
function criarPet(): never
{
    exigirToken();
    [$p, $erros] = validarPet(lerJson());
    if ($erros) erroValidacao($erros);

    try {
        conectar()->prepare(
            'INSERT INTO pets (nome, especie, raca, data_nascimento, peso_kg, tutor_id) VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$p['nome'], $p['especie'], $p['raca'], $p['data_nascimento'], $p['peso_kg'], $p['tutor_id']]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1452) erroValidacao(['tutor_id não corresponde a nenhum tutor cadastrado']);
        throw $e;
    }
    $id = (int)conectar()->lastInsertId();
    header("Location: pets/$id");
    responder(201, petOu404($id));
}

/** PUT /pets/{id} */
function alterarPet(int $id): never
{
    exigirToken();
    petOu404($id);
    [$p, $erros] = validarPet(lerJson());
    if ($erros) erroValidacao($erros);

    try {
        conectar()->prepare(
            'UPDATE pets SET nome = ?, especie = ?, raca = ?, data_nascimento = ?, peso_kg = ?, tutor_id = ? WHERE id = ?'
        )->execute([$p['nome'], $p['especie'], $p['raca'], $p['data_nascimento'], $p['peso_kg'], $p['tutor_id'], $id]);
    } catch (PDOException $e) {
        if (($e->errorInfo[1] ?? 0) === 1452) erroValidacao(['tutor_id não corresponde a nenhum tutor cadastrado']);
        throw $e;
    }
    responder(200, petOu404($id));
}

/** DELETE /pets/{id} */
function excluirPet(int $id): never
{
    exigirToken();
    petOu404($id);
    conectar()->prepare('DELETE FROM pets WHERE id = ?')->execute([$id]);
    responder(204);
}
