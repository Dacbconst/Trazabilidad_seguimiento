const video = document.getElementById('video');
const canvas = document.getElementById('canvas');
const takePhotoButton = document.getElementById('takePhoto');
const cameraModal = document.getElementById('cameraModal');
let currentStream = null;
 
document.addEventListener("DOMContentLoaded", ()=> {Tomarfoto();})
 
//FUNCION PARA TOMAR FOTO EN VIVO
function Tomarfoto() {
 
 
  // Función para abrir la cámara
  async function startCamera() {
    try {
      currentStream = await navigator.mediaDevices.getUserMedia({ video: true });
      video.srcObject = currentStream;
    } catch (err) {
      console.error("Error al acceder a la cámara:", err);
    }
  }
 
  // Detener cámara al cerrar modal
  function stopCamera() {
    if (currentStream) {
      currentStream.getTracks().forEach(track => track.stop());
      currentStream = null;
    }  
  }
 
  // Asociar botón de ícono de cámara con apertura de modal
  document.addEventListener('click', function (e) {
    if (e.target.closest('.open-camera')) {
      const button = e.target.closest('.open-camera');
      const preguntaId = button.getAttribute('data-pregunta-id');
      document.getElementById('currentPreguntaId').value = preguntaId;
 
      const modal = new bootstrap.Modal(document.getElementById('cameraModal'));
      modal.show();
    }
  });
 
  // Al abrir modal, iniciar cámara
  cameraModal.addEventListener('shown.bs.modal', startCamera);
  cameraModal.addEventListener('hidden.bs.modal', stopCamera);
 
  // Tomar foto
  takePhotoButton.addEventListener('click', () => {
    const context = canvas.getContext('2d');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    context.drawImage(video, 0, 0, canvas.width, canvas.height);
 
    const imageData = canvas.toDataURL('image/png');
    const preguntaId = document.getElementById('currentPreguntaId').value;
 
 
 
    //version agregada  NUEVA para mostrar la foto toada de la camara:
    const imgPrevia = document.getElementById(`imagenPrevisualizacion_${preguntaId}`);
    if(imgPrevia){
      imgPrevia.src = imageData;
      imgPrevia.style.display = "block";
 
    }
    else {
      console.error( "no se encontro la foto de previsualizacion");
    }
 
/*
    // Puedes mostrar la imagen en el contenedor de la pregunta (si quieres)
    const container = document.getElementById(`input-container_${preguntaId}`);
    container.innerHTML += `<img src="${imageData}" alt="Captura" class="img-thumbnail mt-2" width="200">`;
 
    // Enviar al servidor si lo deseas */
 
 
    // Cerrar modal después de tomar la foto
    const bsModal = bootstrap.Modal.getInstance(cameraModal);
    bsModal.hide();
  });
 
// Enviar al servidor si lo deseas */
 /*  fetch('', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'image=' + encodeURIComponent(imageData)
    }).then(res => res.text())
      .then(data => {
        console.log('Foto guardada:', data);
      }); */
}
 
 