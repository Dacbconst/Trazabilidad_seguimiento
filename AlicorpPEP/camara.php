<?php
// Procesar la imagen si viene por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['image'])) {
    $imgData = $_POST['image'];
    $imgData = str_replace('data:image/png;base64,', '', $imgData);
    $imgData = str_replace(' ', '+', $imgData);
    $data = base64_decode($imgData);
    $filename = 'foto_' . time() . '.png';
    file_put_contents($filename, $data);
    echo 'Imagen guardada como: ' . $filename;
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Capturar foto con cámara</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">

  <h2>Captura de cámara</h2>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cameraModal">Abrir cámara</button>

  <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Cámara en vivo</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body text-center">
          <video id="video" autoplay playsinline width="100%"></video>
          <canvas id="canvas" class="d-none"></canvas>
          <br>
          <button id="takePhoto" class="btn btn-success mt-3">Tomar foto</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const cameraModal = document.getElementById('cameraModal');

    let stream;

    // Abrir cámara al mostrar el modal
    cameraModal.addEventListener('shown.bs.modal', async () => {
      try {
        stream = await navigator.mediaDevices.getUserMedia({ video: true });
        video.srcObject = stream;
      } catch (err) {
        console.error('Error al acceder a la cámara:', err);
        alert('No se pudo acceder a la cámara.');
      }
    });

    // Cerrar cámara al cerrar el modal
    cameraModal.addEventListener('hidden.bs.modal', () => {
      if (stream) {
        stream.getTracks().forEach(track => track.stop());
        video.srcObject = null;
      }
    });

    // Capturar foto
    document.getElementById('takePhoto').addEventListener('click', () => {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      canvas.getContext('2d').drawImage(video, 0, 0);
      const imageData = canvas.toDataURL('image/png');

      // Enviar al servidor
      fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'image=' + encodeURIComponent(imageData)
      })
      .then(response => response.text())
      .then(result => alert(result))
      .catch(error => console.error('Error al guardar la imagen:', error));
    });
  </script>
</body>
</html>
