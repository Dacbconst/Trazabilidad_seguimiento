<?php
$pageTitle = "Evidencias de Tácticos";
ob_start();
?>

<div class="container my-5">
    <!-- Título principal -->
    <h1 class="text-center mb-4">Evidencias de Tácticos por Mercaderista</h1>
    
    <!-- Campo de búsqueda -->
    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
            <input 
                type="text" 
                id="buscarMercaderista" 
                class="form-control shadow-sm" 
                placeholder="Buscar mercaderista..." 
                onkeyup="filtrarMercaderistas()">
        </div>
    </div>
    
    <!-- Contenedor de Cards -->
    <div class="row" id="mercaderistasContainer">
        <!-- Las cards se generan dinámicamente aquí -->
        <div class="col-12 text-center">
            <div id="loading" class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const container = document.getElementById('mercaderistasContainer');
    const loading   = document.getElementById('loading');

    fetch('../get_evidencias.php')
        .then(response => {
            if (!response.ok) {
                throw new Error("Error en la respuesta del servidor");
            }
            return response.json();
        })
        .then(data => {
            console.log("Datos del Web Service:", data);

            if (!data.success || !data.data || !data.data.length) {
                loading.style.display = 'none';
                container.innerHTML = "<p class='text-center'>No hay evidencias disponibles.</p>";
                return;
            }

            loading.style.display = 'none'; // Ocultar spinner
            renderCards(data.data);
        })
        .catch(error => {
            console.error("Error al cargar las evidencias:", error);
            loading.innerHTML = "<p class='text-danger'>Error al cargar los datos. Por favor, inténtelo nuevamente más tarde.</p>";
        });
});

// Generar las cards de cada mercaderista
function renderCards(evidencias) {
    const container = document.getElementById('mercaderistasContainer');
    container.innerHTML = ''; // Limpia el contenedor

    evidencias.forEach(evidencia => {
        const cardHTML = `
            <div 
                class="col-md-4 col-sm-6 mb-4 mercaderista-card"
                data-nombre="${(evidencia.mercaderista || '').toLowerCase()}">
                
                <div class="card shadow-lg h-100 border-0">
                    <div class="card-header bg-${evidencia.es_recibido ? 'primary' : 'warning'} text-white">
                        <h5 class="card-title mb-0">
                            ${evidencia.mercaderista}
                        </h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Fecha:</strong> ${evidencia.fecha}</p>
                        <p><strong>Hora:</strong> ${evidencia.hora}</p>
                        <p><strong>Estado:</strong> ${evidencia.es_recibido ? "Recibido" : "Pendiente"}</p>
                        <p><strong>Observación:</strong> ${evidencia.observacion || 'Sin observaciones'}</p>
                        <p><strong>Cantidad Recibida:</strong> ${evidencia.cantidad_recibida || 'N/A'}</p>
                    </div>
                    <div class="card-footer bg-light text-center">
                        <button 
                            class="btn btn-sm btn-info" 
                            onclick="showImageModal('${evidencia.foto_guia}', 'Guía')">
                            Ver Guía
                        </button>
                        <button 
                            class="btn btn-sm btn-primary" 
                            onclick="showImageModal('${evidencia.foto_recibido}', 'Recibido')">
                            Ver Recibido
                        </button>
                    </div>
                </div>
            </div>
        `;
        container.innerHTML += cardHTML;
    });
}

// Mostrar el modal con la imagen
function showImageModal(imageUrl, title) {
    const modal = new bootstrap.Modal(document.getElementById('imageModal'));
    document.getElementById('imageModalTitle').textContent = title;
    document.getElementById('imageModalBody').innerHTML = `
        <img 
            src="${imageUrl}" 
            alt="${title}" 
            class="img-fluid rounded shadow-sm">
    `;
    modal.show();
}

// Filtrar por nombre de mercaderista
function filtrarMercaderistas() {
    const searchInput = document.getElementById('buscarMercaderista').value.toLowerCase();
    const mercaderistaCards = document.querySelectorAll('.mercaderista-card');
    
    mercaderistaCards.forEach(card => {
        const nombre = card.getAttribute('data-nombre') || '';
        card.style.display = nombre.includes(searchInput) ? '' : 'none';
    });
}
</script>

<!-- Modal para visualizar imágenes -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title" id="imageModalTitle"></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center" id="imageModalBody"></div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
include '../layout.php';
?>
