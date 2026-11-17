<?php
declare(strict_types=1);

/* Validações de tutor e de pet. Cada função devolve [dados limpos, lista de erros]. */

const ESPECIES = ['cachorro', 'gato', 'ave', 'roedor', 'outro'];

/** Texto com tamanho entre $min e $max (sem contar espaços nas pontas). */
function textoValido(mixed $v, int $min, int $max, string $campo, array &$erros): string
{
    if (!is_string($v) || mb_strlen(trim($v)) < $min || mb_strlen(trim($v)) > $max) {
        $erros[] = "$campo deve ter entre $min e $max caracteres";
        return '';
    }
    return trim($v);
}

/** Tutor: nome 2–100, telefone obrigatório, e-mail válido. */
function validarTutor(array $d): array
{
    $erros = [];

    $nome = textoValido($d['nome'] ?? null, 2, 100, 'nome', $erros);

    $telefone = '';
    if (!isset($d['telefone']) || !is_string($d['telefone']) || trim($d['telefone']) === '') {
        $erros[] = 'telefone é obrigatório';
    } elseif (mb_strlen(trim($d['telefone'])) > 20) {
        $erros[] = 'telefone deve ter no máximo 20 caracteres';
    } else {
        $telefone = trim($d['telefone']);
    }

    $email = '';
    if (!isset($d['email']) || !is_string($d['email']) || trim($d['email']) === '') {
        $erros[] = 'email é obrigatório';
    } elseif (mb_strlen(trim($d['email'])) > 150 || !filter_var(trim($d['email']), FILTER_VALIDATE_EMAIL)) {
        $erros[] = 'email inválido';
    } else {
        $email = strtolower(trim($d['email']));   // minúsculas, para o "único" valer também no MySQL e no SQLite
    }

    return [['nome' => $nome, 'telefone' => $telefone, 'email' => $email], $erros];
}

/** Pet: nome 2–60, especie da lista, peso > 0 e <= 200, data AAAA-MM-DD não futura, tutor existente. */
function validarPet(array $d): array
{
    $erros = [];

    $nome = textoValido($d['nome'] ?? null, 2, 60, 'nome', $erros);

    $especie = '';
    $e = isset($d['especie']) && is_string($d['especie']) ? mb_strtolower(trim($d['especie'])) : '';
    if (!in_array($e, ESPECIES, true)) {
        $erros[] = 'especie deve ser uma entre: ' . implode(', ', ESPECIES);
    } else {
        $especie = $e;
    }

    // raça é opcional: ausente, null ou vazia viram NULL
    $raca = null;
    if (isset($d['raca']) && $d['raca'] !== '') {
        if (!is_string($d['raca']) || mb_strlen(trim($d['raca'])) > 60) {
            $erros[] = 'raca deve ter no máximo 60 caracteres';
        } else {
            $raca = trim($d['raca']);
        }
    }

    $peso = 0.0;
    $p = $d['peso_kg'] ?? null;
    $ehNumero = is_int($p) || is_float($p) || (is_string($p) && preg_match('/^\d+(\.\d+)?$/', $p) === 1);
    if (!$ehNumero || (float)$p <= 0 || (float)$p > 200) {
        $erros[] = 'peso_kg deve ser um número maior que 0 e até 200';
    } else {
        $peso = round((float)$p, 2);
    }

    $data = '';
    $dn = $d['data_nascimento'] ?? null;
    if (!is_string($dn) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dn, $m) || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        $erros[] = 'data_nascimento deve estar no formato AAAA-MM-DD e ser uma data válida';
    } elseif ($dn > date('Y-m-d')) {
        $erros[] = 'data_nascimento não pode ser no futuro';
    } else {
        $data = $dn;
    }

    $tutorId = 0;
    $t = $d['tutor_id'] ?? null;
    if (is_int($t) || (is_string($t) && preg_match('/^\d+$/', $t))) {
        $tutorId = (int)$t;
    }
    if ($tutorId <= 0) {
        $erros[] = 'tutor_id deve ser o id de um tutor';
    } elseif (!buscarTutor($tutorId)) {
        $erros[] = 'tutor_id não corresponde a nenhum tutor cadastrado';
    }

    return [[
        'nome' => $nome, 'especie' => $especie, 'raca' => $raca,
        'data_nascimento' => $data, 'peso_kg' => $peso, 'tutor_id' => $tutorId,
    ], $erros];
}
