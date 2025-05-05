<?php
function loadDatabaseSttings($pathjs){
	$string = file_get_contents($pathjs);
	$json_a = json_decode($string, true);
	return $json_a;
}

function getToken(){
	//creamos el objeto fecha y obtivimos la cantidad de segundos desde 1 enero 1970
	$fecha = date_create();
	$tiempo = date_timestamp_get($fecha);
	$numero = mt_rand();
	$cadena = ''.$numero.$tiempo;
	$numero2 = mt_rand();
	$cadena2 = ''.$numero.$tiempo.$numero2;
	$hash_sha1 = sha1($cadena);
	$hash_md5 = md5($cadena2);
	return substr($hash_sha1,0,20).$hash_md5.substr($hash_sha1,20);
}

require 'vendor/autoload.php';
$f3 = \Base::instance();

$f3->route('GET /',
    function() {
        echo 'Hello, world!';
    }
);
$f3->route('GET /saludo/@nombre',
    function($f3) {
        echo 'Hola '.$f3->get('PARAMS.nombre');
    }
);
//Registro
/*
 * Este registro recibe un JSON con el siguiente formato:
 * 
 * {
 *  	"uname":"XXX",
 * 		"email": "XXX",
 * 		"password": "XXX"
 * }
 * */
$f3->route('POST /Registro',
    function($f3) {
		$dbcnf = loadDatabaseSttings('db.json');
		$db=new DB\SQL(
			'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
			$dbcnf['user'],
			$dbcnf['password']
	);
	$db->setAttribute(\PDO:: ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
	//obtener el cuerpo de la peticion
	$Cuerpo = $f3->get('BODY');
	$jsB = json_decode($Cuerpo,true);
	///////////////////////////////
	$R = array_key_exists('uname',$jsB) && array_key_exists('email',$jsB) && array_key_exists('password',$jsB);
	// TODO checar si estan vacios los elemento del json
	// TODO control de error
	if (!$R){
		echo '{"R":-1}';
		return;
	}
		try {
			$R = $db->exec('insert into Usuarios values(null,"'.$jsB['uname'].'","'.$jsB['email'].'",md5("'.$jsB['password'].'"))');
		}catch (Exception $e){
			echo '{"R":-2}';
			return;
		}
		echo "{\"R\":0,\"D\":".var_export($R,TRUE)."}";

    }
);

//login
/*
 * Este registro recibe un JSON con el siguiente formato:
 * 
 * {
 *  	"uname":"XXX",
 * 		"password": "XXX"
 * }
 * 
 * Debe retornar un token
 * */
$f3->route('POST /Login',
		function($f3) {
		$dbcnf = loadDatabaseSttings('db.json');
		$db=new DB\SQL(
			'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
			$dbcnf['user'],
			$dbcnf['password']
		);
		$db->setAttribute(\PDO:: ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		//obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		///////////////////////////////
		$R = array_key_exists('uname',$jsB) && array_key_exists('password',$jsB);
		// TODO checar si estan vacios los elemento del json
		// TODO control de error
		if (!$R){
			echo '{"R":-1}';
			return;
		}

		try {
			$R = $db->exec('Select id from Usuarios where uname = 
							"'.$jsB['uname'].'" and password = md5 
							("'.$jsB['password'].'");');
		}
		
		catch (Exception $e){
			echo '{"R":-2}';
			return;
		}
		if (empty ($R)){
			echo '{"R":-3}';
			return;
		}
		$T = getToken();
		$db->exec('Delete from AccesoToken where id_Usuario = "'.$R[0]['id'].'";');
		$R = $db->exec('insert into AccesoToken values ('.$R[0]['id'].',"'.$T.'",now())');
		echo "{\"R\":0,\"D\":\"".$T."\"}";

    }
);

//Imagen
/*
 * Este subir imagen recibe un JSON con el siguiente formato:
 * 
 * {
 * 		"token": "XXX",
 *  	"name":"XXX",
 * 		"data": "XXX"
 * 		"ext": "PNG"
 * }
 * 
 * Debe retornar un token
 * */

$f3->route('POST /Imagen',
		function($f3) {
		// Directorio
		if (!file_exists('tmp')){
			mkdir('tmp');
		}

		if (!file_exists('img')){
			mkdir('img');
		}
		//obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		///////////////////////////////
		$R = array_key_exists('name',$jsB) && array_key_exists('data',$jsB) && array_key_exists('ext',$jsB) && array_key_exists('token',$jsB);
		// TODO checar si estan vacios los elemento del json
		// TODO control de error
		if (!$R){
			echo '{"R":-1}';
			return;
		}

			$dbcnf = loadDatabaseSttings('db.json');
			$db=new DB\SQL(
				'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
				$dbcnf['user'],
				$dbcnf['password']
		);
		$db->setAttribute(\PDO:: ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		//Validar si el usuario esta en la base de datos
		$TKN = $jsB['token'];

		try {
			$R = $db->exec('select id_Usuario from AccesoToken where token = "'.$TKN.'"');
		}catch (Exception $e){
			echo '{"R":-2}';
			return;
		}

		$id_Usuario = $R[0]['id_Usuario'];
		file_put_contents('tmp/'.$id_Usuario,base64_decode($jsB['data']));
		$jsB['data'] = '';
		
		// Guardar info del archivo en la base de datos
		$R = $db->exec('insert into Imagen value(null,"'.$jsB['name'].'","img/",'.$id_Usuario.' );');
		$R = $db->exec('select max(id) as idImagen from Imagen where id_Usuario = '.$id_Usuario);
		$idImagen = $R[0]['idImagen'];
		$R = $db->exec('update Imagen set ruta = "img/'.$idImagen.'.'.$jsB['ext'].'" where id = '.$idImagen);
		
		//Mover archivo a su nueva locacion
		rename('tmp/'.$id_Usuario,'img/'.$idImagen.'.'.$jsB['ext']);

		echo "{\"R\":0,\"D\":".$idImagen."}";

    }
    
);

//Descargar
/*
 * Este registro recibe un JSON con el siguiente formato:
 * 
 * {
 *  	"token":"XXX",
 * 		"id": "XXX"
 * }
 * 
 * Debe retornar un token
 * */
$f3->route('POST /Descargar',
		function($f3) {
		$dbcnf = loadDatabaseSttings('db.json');
		$db=new DB\SQL(
			'mysql:host=localhost;port='.$dbcnf['port'].';dbname='.$dbcnf['dbname'],
			$dbcnf['user'],
			$dbcnf['password']
		);
		$db->setAttribute(\PDO:: ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		//obtener el cuerpo de la peticion
		$Cuerpo = $f3->get('BODY');
		$jsB = json_decode($Cuerpo,true);
		///////////////////////////////
		$R = array_key_exists('token',$jsB) && array_key_exists('id',$jsB);
		// TODO checar si estan vacios los elemento del json
		// TODO control de error
		if (!$R){
			echo '{"R":-1}';
			return;
		}

		//TODO VALIDAR CORREO EN JSON
		// Comprobar que el usuario sea valido
		$TKN = $jsB['token'];
		$idImagen = $jsB['id'];
		try {
			$R = $db->exec('select id_Usuario from AccesoToken where token = "'.$TKN.'"');
		}catch (Exception $e){
			echo '{"R":-2}';
			return;
		}
		// Buscar imagen y enviarla
		try {
			$R = $db->exec('Select name,ruta from Imagen where id = '.$idImagen);  
		}
		catch (Exception $e){
			echo '{"R":-3}';
			return;
		}
		$web = \Web::instance();
		ob_start();
		//send the file without any download dialog
		$info = pathinfo($R[0]['ruta']);
		$web->send($R[0]['ruta'],NULL,0,TRUE,$R[0]['name'].'.'.$info['extension']);
		$out=ob_get_clean();
		//echo "{\"R\":0,\"D\":\"".$T."\"}";

    }
);

$f3->run();
?>

