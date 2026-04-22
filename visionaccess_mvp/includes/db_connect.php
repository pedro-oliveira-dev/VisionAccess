<?php
// Configurações do Banco de Dados
$host = 'localhost';
$dbname = 'visionaccess';
$username = 'root';
$password = ''; // Senha padrão do MySQL no sandbox

try {
    // Conexão PDO para maior segurança e facilidade de uso
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Em produção, deve-se logar o erro e não exibir detalhes ao usuário
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>
