<?php
// Sube fotos de evidencia a Azure Blob (REST + Shared Key a mano, mismo patrón que Acuerdos_Comerciales) en app/AppEpson/EpsonReport/.

// Cuenta y clave: se toman de la config ya existente de Acuerdos_Comerciales, sin duplicar el secreto acá.
if (!defined('AZURE_STORAGE_KEY')) {
	$epAzureBase = __DIR__.'/../../Acuerdos_Comerciales/includes/azure_storage.php';
	if (file_exists($epAzureBase)) {
		require_once $epAzureBase;
	}
}

define('EP_AZURE_CONTAINER', 'app');
define('EP_AZURE_PREFIX', 'AppEpson/EpsonReport/');

// URL pública de lectura (mismo criterio que Jabonería/Pintuco: el contenedor 'app' permite lectura anónima).
function ep_azure_url($blobPath) {
	return 'https://'.AZURE_STORAGE_ACCOUNT.'.blob.core.windows.net/'.EP_AZURE_CONTAINER.'/'.$blobPath;
}

// Sube bytes crudos a EP_AZURE_PREFIX + $nombreRelativo. Devuelve la ruta del blob o false si falla.
function ep_azure_subir($nombreRelativo, $contenido, $contentType) {
	if (!defined('AZURE_STORAGE_KEY')) {
		error_log('ep_azure_subir: falta AZURE_STORAGE_KEY');
		return false;
	}
	$blobPath = EP_AZURE_PREFIX.ltrim($nombreRelativo, '/');
	$fecha = gmdate('D, d M Y H:i:s \G\M\T');
	$version = '2021-08-06';
	$largo = (string) strlen($contenido);

	$canonHeaders = "x-ms-blob-type:BlockBlob\nx-ms-date:".$fecha."\nx-ms-version:".$version."\n";
	$canonRecurso = '/'.AZURE_STORAGE_ACCOUNT.'/'.EP_AZURE_CONTAINER.'/'.$blobPath;
	$aFirmar = "PUT\n\n\n".$largo."\n\n".$contentType."\n\n\n\n\n\n\n".$canonHeaders.$canonRecurso;
	$firma = base64_encode(hash_hmac('sha256', $aFirmar, base64_decode(AZURE_STORAGE_KEY), true));

	$ch = curl_init(ep_azure_url($blobPath));
	curl_setopt_array($ch, [
		CURLOPT_CUSTOMREQUEST => 'PUT',
		CURLOPT_POSTFIELDS => $contenido,
		CURLOPT_HTTPHEADER => [
			'x-ms-blob-type: BlockBlob',
			'x-ms-date: '.$fecha,
			'x-ms-version: '.$version,
			'Content-Type: '.$contentType,
			'Content-Length: '.$largo,
			'Authorization: SharedKey '.AZURE_STORAGE_ACCOUNT.':'.$firma,
		],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_TIMEOUT => 60,
	]);
	$respuesta = curl_exec($ch);
	$codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$errorCurl = curl_error($ch);
	curl_close($ch);

	if ($codigo !== 201) {
		error_log('ep_azure_subir: HTTP '.$codigo.' blob='.$blobPath.' resp='.$respuesta.' curl='.$errorCurl);
		return false;
	}
	return $blobPath;
}
