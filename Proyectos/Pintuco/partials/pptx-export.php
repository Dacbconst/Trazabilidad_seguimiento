<?php
// Cargar los 3 componentes PPT
include __DIR__ . '/../components/pptx/pptx-base.php';
include __DIR__ . '/../components/pptx/pptx-slide-exhibiciones.php';
include __DIR__ . '/../components/pptx/pptx-slide-evidencias.php';
?>
<script>
$(document).ready(function() {

    function triggerDownload() {
        var reporteEl    = document.getElementById('reportes');
        var rep_val      = reporteEl.value;
        var finicio      = document.getElementById('fechaInicio').value;
        var ffin         = document.getElementById('fechaFin').value;
        var fi           = finicio.split('-');
        var ff           = ffin.split('-');

        // Configuración base del PPT
        var pptx = pptxCreateBase({
            author:        'Unilever',
            company:       'Unilever',
            subject:       'Evidencia Fotográfica',
            title:         'Evidencia Fotográfica Unilever',
            finiciofinal:  fi[2] + '-' + fi[1] + '-' + fi[0],
            ffinalfinal:   ff[2] + '-' + ff[1] + '-' + ff[0],
            tipoReporte:   reporteEl.options[reporteEl.selectedIndex].text,
            supervisor:    document.getElementById('supervisores').value,
            mercaderista:  document.getElementById('mercaderistas').value,
            ciudad:        document.getElementById('ciudades').value
        });

        // ── EXHIBICIONES ──────────────────────────────────────────────────────
        if (rep_val === 'exhibiciones') {
            var grupos = {};
            var gruposOrder = [];

            $('.exh-main-cb').each(function() {
                var card   = $(this).closest('.card');
                var cardId = card.attr('id');
                if (!cardId || !exhState[cardId]) return;
                if (exhState[cardId].selected.size === 0 && !this.checked) return;

                var images = exhGetSelectedImages(cardId);
                if (!images || images.length === 0) return;

                var local_e = card.find("p[name='local']").text();
                var user_e  = card.find("p[name='user']").text();
                var fecha_e = card.find("p[name='fecha']").text();
                var usrsp   = user_e.split('Mercaderista:');
                var fechasp = fecha_e.replace('Fecha:', '').trim().split(' ');
                var tiposArr = (card.attr('data-tipos') || '').split('|');
                var categoriasArr = (card.attr('data-categorias') || '').split('|');
                var subcategoriasArr = (card.attr('data-subcategorias') || '').split('|');

                var brand = (local_e.split(' - ')[0] || local_e).trim();
                if (!grupos[brand]) { grupos[brand] = []; gruposOrder.push(brand); }

                images.forEach(function(src, idx) {
                    grupos[brand].push({
                        src:    src,
                        tipo:   tiposArr[idx] || '',
                        categoria:    safe(categoriasArr[idx]).trim(),
                        subcategoria: safe(subcategoriasArr[idx]).trim(),
                        local:  local_e,
                        gestor: safe(usrsp[1]).trim(),
                        fecha:  safe(fechasp[0]).trim(),
                        hora:   safe(fechasp[1]).trim()
                    });
                });
            });

            if (gruposOrder.length === 0) {
                showToast('⚠️ Selecciona al menos una foto', 'error');
                return;
            }

            pptxBuildExhibiciones(pptx, grupos, gruposOrder)
                .then(function() { return pptxSave(pptx, 'EvidenciaFotografica_Unilever.pptx'); })
                .then(function() { showToast('', 'ok'); })
                .catch(function(err) { showToast('Error al generar el archivo', 'error'); console.error(err); });
            return;
        }

        // ── OTROS REPORTES (vi_evidencias, etc.) ─────────────────────────────
        var grupos = {};
        var gruposOrder = [];
        $('input[type=checkbox]:checked').each(function() {
            var card    = $(this).closest('.card');
            var local_e = card.find("p[name='local']").text();
            var brand   = (local_e.split(' - ')[0] || local_e).trim();
            if (!grupos[brand]) { grupos[brand] = []; gruposOrder.push(brand); }
            grupos[brand].push(card);
        });

        if (gruposOrder.length === 0) {
            alert('No se ha seleccionado ninguna fotografía');
            return;
        }

        pptxBuildEvidencias(pptx, grupos, gruposOrder, rep_val)
            .then(function() { return pptxSave(pptx, 'EvidenciaFotografica_Unilever.pptx'); })
            .then(function() { showToast('', 'ok'); })
            .catch(function(err) { showToast('Error al generar el archivo', 'error'); console.error(err); });
    }

    // Ambos botones (header + sidebar) disparan la misma función
    $('#download2, #download2-sidebar').click(triggerDownload);
});
</script>
