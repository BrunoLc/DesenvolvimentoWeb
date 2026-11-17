<?php
declare(strict_types=1);

/* Funções de apoio: resposta JSON, erros e leitura do corpo da requisição. */

date_default_timezone_set('America/Sao_Paulo');

/** Envia a resposta em JSON e encerra. Status 204 não tem corpo. */
function responder(int $status, mixed $dados = null): never
{
    http_response_code($status);
    if ($status !== 204 && $dados !== null) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }
    exit;
}

/** Erro no formato {"detail": "mensagem"} (campos extras opcionais). */
function erro(int $status, string $detail, array $extra = []): never
{
    responder($status, ['detail' => $detail] + $extra);
}

/** 422 com a lista de problemas encontrados na validação. */
function erroValidacao(array $erros): never
{
    erro(422, 'Dados inválidos', ['erros' => $erros]);
}

/** Lê o corpo da requisição. Se não for um objeto JSON, responde 400. */
function lerJson(): array
{
    $texto = file_get_contents('php://input');
    $dados = json_decode($texto === false ? '' : $texto, true);

    if (!is_array($dados) || ($dados !== [] && array_is_list($dados))) {
        erro(400, 'O corpo da requisição precisa ser um objeto JSON válido');
    }
    return $dados;
}
