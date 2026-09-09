<?php
// Sube/descarga a Azure Blob Storage vía API REST directa (firma Shared Key a mano), sin SDK de Composer — mismo criterio que xlsx_reader/writer. A diferencia de otras apps de la misma cuenta, acá SIEMPRE se autentica también para LEER (Shared Key en el GET): las Actas llevan precios/rebates reales.

define('AZURE_STORAGE_ACCOUNT', 'luckyecuadorweb');
define('AZURE_STORAGE_KEY', '1NR1OHQjEVkwUmFTCtktU9j0/iMbVq7szdh41DOSac4icyhIzStRfyD0sAMha0ZSRWT+ZRGucKeksMR0iEaFzQ==');
define('AZURE_STORAGE_CONTAINER', 'app');
define('AZURE_STORAGE_PREFIX', 'AcuerdosComerciales/');
define('AZURE_STORAGE_API_VERSION', '2021-08-06');

// Firma Shared Key (no Lite) para Blob Service. $headersFirma debe traer SOLO los headers x-ms-* que se van a mandar en la request real.
function azure_storage_firmar($metodo, $blobPath, $headersFirma, $contentType, $contentLength) {
	ksort($headersFirma);
	$canonHeaders = '';
	foreach ($headersFirma as $k => $v) {
		$canonHeaders .= strtolower($k) . ':' . $v . "\n";
	}
	$canonResource = '/' . AZURE_STORAGE_ACCOUNT . '/' . AZURE_STORAGE_CONTAINER . '/' . $blobPath;

	$stringToSign = $metodo . "\n"
		. "\n"           // Content-Encoding
		. "\n"           // Content-Language
		. $contentLength . "\n"
		. "\n"           // Content-MD5
		. $contentType . "\n"
		. "\n"           // Date (se usa x-ms-date en su lugar)
		. "\n"           // If-Modified-Since
		. "\n"           // If-Match
		. "\n"           // If-None-Match
		. "\n"           // If-Unmodified-Since
		. "\n"           // Range
		. $canonHeaders
		. $canonResource;

	$clave = base64_decode(AZURE_STORAGE_KEY);
	$firma = base64_encode(hash_hmac('sha256', $stringToSign, $clave, true));
	return 'SharedKey ' . AZURE_STORAGE_ACCOUNT . ':' . $firma;
}

// Sube $contenido (bytes crudos) al blob "AcuerdosComerciales/$nombreRelativo". Devuelve la ruta guardada (para persistir en la base) o false si falló.
function azure_storage_subir($nombreRelativo, $contenido, $contentType) {
	$blobPath = AZURE_STORAGE_PREFIX . ltrim($nombreRelativo, '/');
	$fecha = gmdate('D, d M Y H:i:s \G\M\T');
	$contentLength = (string) strlen($contenido);

	$headersFirma = [
		'x-ms-blob-type' => 'BlockBlob',
		'x-ms-date'      => $fecha,
		'x-ms-version'   => AZURE_STORAGE_API_VERSION,
	];
	$auth = azure_storage_firmar('PUT', $blobPath, $headersFirma, $contentType, $contentLength);

	$url = 'https://' . AZURE_STORAGE_ACCOUNT . '.blob.core.windows.net/' . AZURE_STORAGE_CONTAINER . '/' . $blobPath;
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_CUSTOMREQUEST => 'PUT',
		CURLOPT_POSTFIELDS => $contenido,
		CURLOPT_HTTPHEADER => [
			'x-ms-blob-type: BlockBlob',
			'x-ms-date: ' . $fecha,
			'x-ms-version: ' . AZURE_STORAGE_API_VERSION,
			'Content-Type: ' . $contentType,
			'Content-Length: ' . $contentLength,
			'Authorization: ' . $auth,
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
		error_log('azure_storage_subir: HTTP ' . $codigo . ' blob=' . $blobPath . ' resp=' . $respuesta . ' curl=' . $errorCurl);
		return false;
	}
	return $blobPath;
}

// Descarga bytes crudos de un blob (ruta completa tal cual se persistió, ya incluye el prefijo). false si no existe o falló.
function azure_storage_descargar($blobPath) {
	if (!$blobPath) {
		return false;
	}
	$fecha = gmdate('D, d M Y H:i:s \G\M\T');
	$headersFirma = [
		'x-ms-date'    => $fecha,
		'x-ms-version' => AZURE_STORAGE_API_VERSION,
	];
	$auth = azure_storage_firmar('GET', $blobPath, $headersFirma, '', '');

	$url = 'https://' . AZURE_STORAGE_ACCOUNT . '.blob.core.windows.net/' . AZURE_STORAGE_CONTAINER . '/' . $blobPath;
	$ch = curl_init($url);
	curl_setopt_array($ch, [
		CURLOPT_HTTPHEADER => [
			'x-ms-date: ' . $fecha,
			'x-ms-version: ' . AZURE_STORAGE_API_VERSION,
			'Authorization: ' . $auth,
		],
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_SSL_VERIFYPEER => true,
		CURLOPT_TIMEOUT => 60,
	]);
	$respuesta = curl_exec($ch);
	$codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	if ($codigo !== 200) {
		error_log('azure_storage_descargar: HTTP ' . $codigo . ' blob=' . $blobPath);
		return false;
	}
	return $respuesta;
}
