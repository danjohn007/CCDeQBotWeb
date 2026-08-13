<?php

declare(strict_types=1);

define('APP_PATH', __DIR__ . '/app');
require APP_PATH . '/bootstrap.php';

use App\Core\Database;

$message = '';
$error = '';
$pdo = null;
$count = 0;

try {
    $pdo = Database::connection();
    $count = (int) $pdo->query('SELECT COUNT(*) FROM usuarios_sistema')->fetchColumn();
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo && $count === 0) {
    $key = (string) ($_POST['setup_key'] ?? '');
    $name = trim((string) ($_POST['nombre'] ?? ''));
    $lastName = trim((string) ($_POST['apellidos'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['correo'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (!hash_equals((string) $GLOBALS['app_config']['setup_key'], $key)) {
        $error = 'La clave de instalación no es correcta.';
    } elseif ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        $error = 'Revisa los datos. La contraseña debe tener al menos 8 caracteres.';
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios_sistema (nombre, apellidos, correo, password_hash, rol)
             VALUES (:nombre, :apellidos, :correo, :password, \'superadministrador\')'
        );
        $stmt->execute([
            'nombre' => $name,
            'apellidos' => $lastName !== '' ? $lastName : null,
            'correo' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $message = 'Superadministrador creado correctamente. Elimina este archivo del servidor y entra al CRM.';
        $count = 1;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Crear superadministrador | AllunayBOT CRM</title>
    <style>
        *{box-sizing:border-box}body{margin:0;font-family:Arial,sans-serif;background:#f0f7f6;color:#1A322F;display:grid;min-height:100vh;place-items:center;padding:24px}.card{width:min(520px,100%);background:#fff;border-radius:18px;padding:32px;box-shadow:0 18px 60px rgba(0,141,133,.14);border-top:6px solid #008D85}h1{margin:0 0 8px;color:#008D85}p{line-height:1.5}.field{margin:14px 0}label{display:block;font-weight:700;margin-bottom:6px}input{width:100%;padding:12px 14px;border:1px solid #bfd8d5;border-radius:10px;font:inherit}button{width:100%;border:0;border-radius:10px;padding:13px;background:#008D85;color:#fff;font-weight:700;cursor:pointer}.alert{padding:12px 14px;border-radius:10px;margin:16px 0}.ok{background:#e0f2ef;color:#006E68}.error{background:#fff0ef;color:#9b2c24}.small{font-size:13px;color:#5C7A77}
    </style>
</head>
<body>
<main class="card">
    <h1>AllunayBOT CRM</h1>
    <p>Crea el primer usuario superadministrador. Esta cuenta tendrá acceso completo al sistema.</p>
    <?php if ($message): ?><div class="alert ok"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($count > 0): ?>
        <div class="alert ok">Ya existe al menos un usuario. Por seguridad, este instalador quedó deshabilitado.</div>
    <?php else: ?>
    <form method="post" autocomplete="off">
        <div class="field"><label>Clave de instalación</label><input type="password" name="setup_key" required></div>
        <div class="field"><label>Nombre</label><input name="nombre" required maxlength="100"></div>
        <div class="field"><label>Apellidos</label><input name="apellidos" maxlength="120"></div>
        <div class="field"><label>Correo</label><input type="email" name="correo" required maxlength="190"></div>
        <div class="field"><label>Contraseña</label><input type="password" name="password" required minlength="8"></div>
        <button type="submit">Crear superadministrador</button>
    </form>
    <?php endif; ?>
    <p class="small">Elimina <strong>crear-admin.php</strong> después de crear la cuenta.</p>
</main>
</body>
</html>
