<?php
	require_once(realpath($_SERVER["DOCUMENT_ROOT"]) . '/App/XploraEcuador/assets/pluginsV4/vendor/autoload.php');

	use MicrosoftAzure\Storage\Blob\BlobRestProxy;
	use MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions;
	use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;
	
	$connectionString = '';
	$blobClient = BlobRestProxy::createBlobService($connectionString);

	// uploadBlobSample($blobClient, $container, $image, $path);

	function uploadBlobSample($blobClient, $container, $image, $path)
	{
		file_put_contents($path,base64_decode($image));

		$content = fopen($path, "r");

		try {
			//Upload blob
			$contentType = 'image/png';
			$options = new CreateBlockBlobOptions();
			$options->setContentType($contentType);
			$blobClient->createBlockBlob($container, $path, $content, $options);
		} catch (ServiceException $e) {
			$code = $e->getCode();
			$error_message = $e->getMessage();
			echo $code . ": " . $error_message . PHP_EOL;
		}
		unlink($path);
	}
