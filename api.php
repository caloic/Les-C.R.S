<?php
header("Content-Type: application/json");
include 'db.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        handleGet($pdo);
        break;
    case 'POST':
        handlePost($pdo, $input);
        break;
    case 'PUT':
        handlePut($pdo, $input);
        break;
    case 'DELETE':
        handleDelete($pdo, $input);
        break;
    default:
        echo json_encode(['message' => 'Invalid request method']);
        break;
}

function handleGet($pdo) {
    $sql = "SELECT * FROM users";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($result);
}


function handlePost($pdo, $input) {
    $sql = "INSERT INTO api_requests (name, email, age, gender) VALUES (:name, :email, :age, :gender)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['name' => $input['name'], 'email' => $input['email'], 'age' => $input['age'], 'gender' => $input['gender']]);
    echo json_encode(['message' => 'User created successfully']);
}

function handlePut($pdo, $input) {
    $fields = [];
    $params = ['id' => $input['id']];

    if (isset($input['name'])) {
        $fields[] = "name = :name";
        $params['name'] = $input['name'];
    }
    if (isset($input['email'])) {
        $fields[] = "email = :email";
        $params['email'] = $input['email'];
    }
    if (isset($input['password'])) {
        $fields[] = "password = :password";
        $params['password'] = $input['password'];
    }
    if (isset($input['adress'])) {
        $fields[] = "adress = :adress";
        $params['adress'] = $input['adress'];
    }

    if (empty($fields)) {
        echo json_encode(['message' => 'No fields to update']);
        return;
    }

    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['message' => 'User updated sucessfully']);
}

function handleDelete($pdo, $input) {
    $sql = "DELETE FROM users WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $input['id']]);
    echo json_encode(['message' => 'User deleted successfully']);
}
?>