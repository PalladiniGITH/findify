<?php
// Handle brief submissions and store them in a local SQLite database.

declare(strict_types=1);

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Método não permitido.'], 405);
}

$input = [
    'nome'    => trim((string)($_POST['nome'] ?? '')),
    'email'   => trim((string)($_POST['email'] ?? '')),
    'product' => trim((string)($_POST['projeto'] ?? '')),
    'budget'  => trim((string)($_POST['orcamento'] ?? '')),
    'style'   => trim((string)($_POST['mensagem'] ?? '')),
    'brand'   => trim((string)($_POST['brand'] ?? '')),
    'options' => trim((string)($_POST['options'] ?? '3')),
];

if ($input['nome'] === '' || $input['email'] === '' || $input['product'] === '' || $input['style'] === '') {
    json_response([
        'success' => false,
        'message' => 'Preencha nome, e-mail, o que deseja comprar e estilo/uso pretendido.',
    ], 400);
}

if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    json_response([
        'success' => false,
        'message' => 'Informe um e-mail válido para continuar.',
    ], 400);
}

$budgetValue = null;
if ($input['budget'] !== '') {
    $normalizedBudget = str_replace(',', '.', $input['budget']);
    $budgetValue = is_numeric($normalizedBudget) ? (float) $normalizedBudget : null;
}

$optionsCount = ctype_digit($input['options']) ? (int) $input['options'] : 3;

$databaseDir = __DIR__ . '/data';
if (!is_dir($databaseDir) && !mkdir($databaseDir, 0775, true) && !is_dir($databaseDir)) {
    json_response([
        'success' => false,
        'message' => 'Não foi possível preparar o armazenamento local.',
    ], 500);
}

$databasePath = $databaseDir . '/briefings.sqlite';

try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE IF NOT EXISTS briefings (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      nome TEXT NOT NULL,
      email TEXT NOT NULL,
      product TEXT NOT NULL,
      budget REAL,
      style TEXT NOT NULL,
      brand TEXT,
      options INTEGER DEFAULT 3,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )');

    $insert = $pdo->prepare('INSERT INTO briefings (nome, email, product, budget, style, brand, options) VALUES (:nome, :email, :product, :budget, :style, :brand, :options)');
    $insert->bindValue(':nome', $input['nome']);
    $insert->bindValue(':email', $input['email']);
    $insert->bindValue(':product', $input['product']);
    $insert->bindValue(':budget', $budgetValue, $budgetValue === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
    $insert->bindValue(':style', $input['style']);
    $insert->bindValue(':brand', $input['brand'] === '' ? null : $input['brand']);
    $insert->bindValue(':options', $optionsCount, PDO::PARAM_INT);
    $insert->execute();
} catch (Throwable $error) {
    json_response([
        'success' => false,
        'message' => 'Erro ao salvar seu brief. Tente novamente em instantes.',
        'details' => getenv('APP_DEBUG') ? $error->getMessage() : null,
    ], 500);
}

json_response([
    'success' => true,
    'message' => 'Brief recebido! Em até 24h retornamos com sua curadoria.',
]);
