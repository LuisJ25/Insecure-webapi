<?php
// =============== FUNCIONES ==================== //
function loadDatabaseSettings($path) {  
    $string = file_get_contents($path);
    $json_a = json_decode($string, true);
    return $json_a;
}

// ========== CORRECCIÓN 1  (Token) ===============//
function getToken() {
    $part1 = bin2hex(random_bytes(10)); 
    $part2 = bin2hex(random_bytes(16)); 
    $part3 = bin2hex(random_bytes(10)); 
    return $part1 . $part2 . $part3; 
}

// ========== CONFIGURACIÓN INICIAL ============== //
require 'vendor/autoload.php';
$f3 = \Base::instance();

// ====== Cabeceras de seguridad añadidas  ========//
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
$f3->set('DEBUG', 0); 

// - - - - - - - - - FUNCIONES - - - - - - - - - - - - - - -//

// ====== HELLO WORLD EN EL NAVEGADOR  ========//
$f3->route('GET /', function() {
    echo 'Hello, world!';
});
// ====== saludo/josue = (Hola josue)  ========//
$f3->route('GET /saludo/@nombre', function($f3) {
    echo 'Hola ' . $f3->encode($f3->get('PARAMS.nombre')); 
});

// ----------------- REGISTRO (Corregido) ----------------- //
$f3->route('POST /Registro', function($f3) {
	
// ===== CORRECCIÓN: A05:2021 - Security Misconfiguration (Archivos sensibles) ==== //
    $dbcnf = loadDatabaseSettings('config/db.json');
// ================================================================================ //
    $db = new DB\SQL(
        'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
        $dbcnf['user'],
        $dbcnf['password']
    );
    // Procesa datos limpios y completos
    $data = json_decode($f3->get('BODY'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !$data || empty($data['uname']) || empty($data['email']) || empty($data['password'])) {
        die('{"R":-1}');
    }

// ======== CORRECCIÓN: A04:2021 - Insecure Design (Validación de email) =============== //
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        die('{"R":-6}');
    }
// ===================================================================================== //


// =============== CORRECCIÓN: A02:2021 - Cryptographic Failures(Uso de MD5) =========== //
    try {
        
        $hash = password_hash($data['password'], PASSWORD_BCRYPT);
        // ============== CORRECCIÓN: A03:2021 - Injection (SQL REGISTRAR) ============= //
        $stmt = $db->prepare('INSERT INTO Usuarios VALUES (null, ?, ?, ?)');
        $stmt->execute([$data['uname'], $data['email'], $hash]);
         // ============================================================================ //
        echo '{"R":0}';
    }  
// ===================================================================================== //
    catch (Exception $e){
			echo '{"R":-2}';
			return;
	}
});

// ----------------- LOGIN (Corregido) ----------------- //
$f3->route('POST /Login', function($f3) {

// ===== CORRECCIÓN: A05:2021 - Security Misconfiguration (Archivos sensibles) ==== //
    $dbcnf = loadDatabaseSettings('config/db.json');
// ================================================================================ //

    $db = new DB\SQL(
		'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
        $dbcnf['user'],
        $dbcnf['password']
    );

    $data = json_decode($f3->get('BODY'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !$data || empty($data['uname']) || empty($data['password'])) {
        die('{"R":-1}');
    }
// ============== CORRECCIÓN: A03:2021 - Injection (SQL LOGIN) ================= //
    try {
        $stmt = $db->prepare('SELECT id, password FROM Usuarios WHERE uname = ?');
        $stmt->execute([$data['uname']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($data['password'], $user['password'])) {
            die('{"R":-3}');
        }
// ============================================================================ //
 
 
//  =============== CORRECCIÓN: Token seguro (pero mismo formato) ============= //
        $T = getToken();
        $db->exec('DELETE FROM AccesoToken WHERE id_Usuario = ?', [$user['id']]);
        $db->exec('INSERT INTO AccesoToken VALUES (?, ?, NOW())', [$user['id'], $T]);
        echo '{"R":0,"D":"'.$T.'"}';
// ========================================================================== //
    } catch (Exception $e){
			echo '{"R":-2}';
			return;
	}
});

// ----------------- SUBIDA DE IMAGEN (Corregido) ----------------- //
$f3->route('POST /Imagen', function($f3) {
	
// ===== CORRECCIÓN: A05:2021 - Security Misconfiguration (Archivos sensibles) ==== //
    $dbcnf = loadDatabaseSettings('config/db.json');
// ================================================================================ //
    $db = new DB\SQL(
        'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
        $dbcnf['user'],
        $dbcnf['password']
    );
    
    // Configuración inicial y verificación de directorios
    if (!file_exists('img')) {
        mkdir('img', 0755, true);
    }

    // Validación del JSON recibido
    $data = json_decode($f3->get('BODY'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !$data || 
        empty($data['token']) || empty($data['name']) || 
        empty($data['data']) || empty($data['ext'])) {
        die('{"R":-1}');
    }

// ================ CORRECCIÓN: A04:2021 - Insecure Design (Validar Extencion) ================= //
    $allowed_extensions = ['png', 'jpg', 'jpeg']; // Extensiones permitidas
    $ext = strtolower(pathinfo($data['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions) || $ext !== strtolower($data['ext'])) {
        die('{"R":-4}');
    }
// ============================================================================================= //

// ========================== CORRECCIÓN: A03:2021 - Injection (SQL) =========================== //
    $user = $db->exec('SELECT id_Usuario FROM AccesoToken WHERE token = ?', [$data['token']]);
    if (empty($user)) {
        die('{"R":-3}');
    }
// ============================================================================================= //

// = CORRECCIÓN: A04:2021 - Insecure Desing - (Verificar que la informacion sea base64 valida) = //
    $base64_str = $data['data'];
    if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $base64_str)) {
        die('{"R":-5}');
    }
// ============================================================================================= //
    $decoded_data = base64_decode($base64_str, true);
    if ($decoded_data === false) {
        die('{"R":-5}');
    }

    // Verificación adicional para imágenes
    if (!@imagecreatefromstring($decoded_data)) {
        die('{"R":-5}');
    }

    // Generación de nombre único para el archivo (con extensión)
    $id_Usuario = $user[0]['id_Usuario'];
    $unique_name = uniqid('img_', true) . '.' . $ext;
    $file_path = 'img/' . $unique_name;

    // Guardado de la imagen
    if (!file_put_contents($file_path, $decoded_data)) {
        die('{"R":-6}');
    }

    // Obtener solo el nombre sin extensión del nombre original
    $nombre_sin_extension = pathinfo($data['name'], PATHINFO_FILENAME);

    // Registro en la base de datos (solo nombre sin extensión)
    try {
        $db->exec('INSERT INTO Imagen (name, ruta, id_Usuario) VALUES (?, ?, ?)', 
            [$nombre_sin_extension, $file_path, $id_Usuario]);
         
    } catch (Exception $e){
			echo '{"R":-2}';
			return;
	}
// ===== CORRECCIÓN: A04:2021 - Insecure Design (Id imagen) ==== //
    echo '{"R":0,"D":"Imagen subida correctamente"}';
// ============================================================= //
});

// ----------------- DESCARGA (Versión corregida) ----------------- //
$f3->route('POST /Descargar', function($f3) {
	
// ===== CORRECCIÓN: A05:2021 - Security Misconfiguration (Archivos sensibles) ==== //
    $dbcnf = loadDatabaseSettings('config/db.json');
// ================================================================================ //
    
    $db = new DB\SQL(
        'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
        $dbcnf['user'],
        $dbcnf['password']
    );
    
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // Validar entrada JSON
    $data = json_decode($f3->get('BODY'), true);
    if (json_last_error() !== JSON_ERROR_NONE || !$data || empty($data['token']) || empty($data['id'])) {
        echo '{"R":-1,"error":"Datos inválidos"}';
        return;
    }

    // Validar token y obtener ID de usuario
    try {
        $userToken = $db->exec('SELECT id_Usuario FROM AccesoToken WHERE token = ?', [$data['token']]);
        if (empty($userToken)) {
            echo '{"R":-3,"error":"Token inválido"}';
            return;
        }
        $userId = $userToken[0]['id_Usuario'];
    } catch (Exception $e) {
        echo '{"R":-2,"error":"Error en base de datos"}';
        return;
    }

// ===== CORRECCIÓN: A01:2021 - Broken Access Control (Verificación de usuarios) ======== //
    try {
        $image = $db->exec('SELECT i.name, i.ruta 
                           FROM Imagen i
                           JOIN AccesoToken at ON i.id_Usuario = at.id_Usuario
                           WHERE i.id = ? AND at.token = ?', 
                          [$data['id'], $data['token']]);
        
        if (empty($image)) {
            echo '{"R":-4,"error":"Imagen no encontrada o no tienes permisos"}';
            return;
        }
// ===================================================================================== //
    } catch (Exception $e) {
        echo '{"R":-3,"error":"Error al verificar imagen"}';
        return;
    }
    
    if (!file_exists($image[0]['ruta'])) {
        echo '{"R":-5,"error":"Archivo no encontrado en el servidor"}'; 
        return;
    }

    $web = \Web::instance();
    $info = pathinfo($image[0]['ruta']);
    ob_clean();
    $web->send($image[0]['ruta'], null, 0, true, $image[0]['name'].'.'.$info['extension']);
});

$f3->run();
