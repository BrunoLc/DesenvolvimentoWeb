<?php
declare(strict_types=1);

/*
 * Conexão com o MySQL via PDO.
 * No XAMPP o usuário root vem sem senha. Se o seu tiver senha, troque a constante DB_SENHA.
 */
const DB_HOST    = 'localhost';
const DB_PORTA   = 3306;
const DB_NOME    = 'petcare';
const DB_USUARIO = 'root';
const DB_SENHA   = '';

function conectar(): PDO
{
    static $pdo = null;   // reaproveita a mesma conexão durante a requisição

    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';port=' . DB_PORTA . ';dbname=' . DB_NOME . ';charset=utf8mb4',
            DB_USUARIO,
            DB_SENHA,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,   // prepared statements de verdade
            ]
        );
    }
    return $pdo;
}
