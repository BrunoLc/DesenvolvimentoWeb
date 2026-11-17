<?php
declare(strict_types=1);

/*
 * PetCare · API REST em PHP puro
 * Roteador manual: descobre método e caminho e chama a função da rota.
 * O código de cada parte está em src/.
 */

require __DIR__ . '/src/ajuda.php';
require __DIR__ . '/src/conexao.php';
require __DIR__ . '/src/auth.php';
require __DIR__ . '/src/validacao.php';
require __DIR__ . '/src/tutores.php';
require __DIR__ . '/src/pets.php';

// Qualquer falha inesperada vira 500 em JSON, sem expor detalhes do banco.
set_exception_handler(function (Throwable $e): void {
    error_log((string)$e);
    erro(500, 'Erro interno do servidor');
});

// CORS: o site da clínica (outra origem) precisa conseguir consumir a API.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'OPTIONS') {          // pré-verificação do navegador
    responder(204);
}

// Caminho da requisição sem a pasta onde o projeto está (ex.: /petcare/tutores/3 -> /tutores/3)
$caminho = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$base    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
if ($base !== '' && str_starts_with($caminho, $base)) {
    $caminho = substr($caminho, strlen($base));
}
$caminho = '/' . trim($caminho, '/');

// ---- Rotas ----
if     ($metodo === 'POST'   && $caminho === '/login')   rotaLogin();
elseif ($metodo === 'POST'   && $caminho === '/logout')  rotaLogout();

elseif ($metodo === 'GET'    && $caminho === '/tutores') listarTutores();
elseif ($metodo === 'POST'   && $caminho === '/tutores') criarTutor();
elseif ($metodo === 'GET'    && preg_match('#^/tutores/(\d+)/pets$#', $caminho, $m)) listarPetsDoTutor((int)$m[1]);
elseif ($metodo === 'GET'    && preg_match('#^/tutores/(\d+)$#', $caminho, $m))      mostrarTutor((int)$m[1]);
elseif ($metodo === 'PUT'    && preg_match('#^/tutores/(\d+)$#', $caminho, $m))      alterarTutor((int)$m[1]);
elseif ($metodo === 'DELETE' && preg_match('#^/tutores/(\d+)$#', $caminho, $m))      excluirTutor((int)$m[1]);

elseif ($metodo === 'GET'    && $caminho === '/pets')    listarPets();
elseif ($metodo === 'POST'   && $caminho === '/pets')    criarPet();
elseif ($metodo === 'GET'    && preg_match('#^/pets/(\d+)$#', $caminho, $m))         mostrarPet((int)$m[1]);
elseif ($metodo === 'PUT'    && preg_match('#^/pets/(\d+)$#', $caminho, $m))         alterarPet((int)$m[1]);
elseif ($metodo === 'DELETE' && preg_match('#^/pets/(\d+)$#', $caminho, $m))         excluirPet((int)$m[1]);

else erro(404, 'Rota não encontrada');
