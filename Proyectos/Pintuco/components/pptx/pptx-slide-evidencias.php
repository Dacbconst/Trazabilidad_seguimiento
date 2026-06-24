/**
 * COMPONENTE: pptx-slide-evidencias.php
 * Genera slides de contenido para reportes de tipo Antes / Después.
 * Agrupa por local (igual que Exhibiciones): 1 slide divisor con el logo
 * del local + 1 slide de contenido por card.
 *
 * Expone:
 *   pptxBuildEvidencias(pptx, grupos, gruposOrder, repVal) → Promise
 *     - grupos      : { local: [jQuery wrapper de .card, ...] }
 *     - gruposOrder : string[] — orden de aparición de los locales
 *     - repVal      : string — valor del select de reporte (ej: "vi_evidencias")
 *
 * Requiere: pptx-base.php (showToast, pptxPreloadImages)
 *           pptx-slide-exhibiciones.php (LOGO_MAP, multiphotoFindLogo)
 */
<script>
function pptxEvidenciaSrc(card) {
    return {
        antes:   card.find('.foto_antes img').attr('src')   || card.find('.foto_antes img').data('src')   || '',
        despues: card.find('.foto_despues img').attr('src') || card.find('.foto_despues img').data('src') || ''
    };
}

// Slide divisor por local, con el logo del local
function pptxAddEvidenciaDivider(pptx, local, repVal) {
    var logo = multiphotoFindLogo(local);
    var sl = pptx.addSlide({ masterName: 'MASTER_SLIDE' });
    if (repVal === 'vi_evidencias') {
        sl.background = { path: 'images/fondo_base_unilever_Antes_y_Despues.webp' };
    }
    if (logo) {
        sl.addImage({ path: logo, x:4.17, y:2.4, w:5.0, h:3.5,
            sizing: { type:'contain', w:5.0, h:3.5 } });
    } else {
        sl.addText(local, { x:0.5, y:2.9, w:12.34, h:2.5,
            fontSize:40, bold:true, align:'center', color:'222222', fontFace:'Calibri' });
    }
}

function pptxAddEvidenciaSlide(pptx, card, repVal, imgCache) {
    var srcs      = pptxEvidenciaSrc(card);
    var fecha     = card.find("p[name='fecha']").text();
    var user      = card.find("p[name='user']").text();
    var ciudad_c  = card.find("p[name='ciudad']").text();
    var local_c   = card.find("p[name='local']").text();
    var direccion = card.find("p[name='direccion']").text();
    var comentario = card.find("p[name='comentario']").text();

    var usersplit   = user.split('Mercaderista:');
    var commsplit   = comentario.split('Comentario:');
    var fechasplit  = fecha.split(' ');

    var textArray = [
        { text: 'Ciudad: ',    options: { bold:true, color:'000000', breakLine:false } },
        { text: ciudad_c,       options: { color:'000000', breakLine:true } },
        { text: '',            options: { color:'000000', breakLine:true } },
        { text: 'Local: ',     options: { bold:true, color:'000000', breakLine:false } },
        { text: local_c,        options: { color:'000000', breakLine:true } },
        { text: '',            options: { color:'000000', breakLine:true } },
        { text: 'Dirección: ', options: { bold:true, color:'000000', breakLine:false } },
        { text: direccion,      options: { color:'000000', breakLine:true } },
        { text: '',            options: { color:'000000', breakLine:true } },
        { text: 'Mercaderista: ', options: { bold:true, color:'000000', breakLine:false } },
        { text: safe(usersplit[1]),  options: { color:'000000', breakLine:true } },
        { text: '',            options: { color:'000000', breakLine:true } },
        { text: 'Fecha: ',     options: { bold:true, color:'000000', breakLine:false } },
        { text: safe(fechasplit[1]), options: { color:'000000', breakLine:true } },
        { text: '',            options: { color:'000000', breakLine:true } },
        { text: 'Hora: ',      options: { bold:true, color:'000000', breakLine:false } },
        { text: safe(fechasplit[2]), options: { color:'000000', breakLine:true } },
        { text: '',            options: { color:'000000', breakLine:true } }
    ];

    if (safe(commsplit[1]).trim() !== '') {
        textArray.push(
            { text: 'Comentario: ', options: { bold:true, color:'000000', breakLine:false } },
            { text: safe(commsplit[1]), options: { color:'000000', breakLine:true } }
        );
    }

    if (repVal === 'vi_evidencias') {
        var sl = pptx.addSlide({ masterName: 'MASTER_SLIDE' });
        sl.background = { path: 'images/Fondo_Base_Antes_y_Despues.webp' };

        var antes   = imgCache[srcs.antes]   || srcs.antes;
        var despues = imgCache[srcs.despues] || srcs.despues;

        // Fotos lado a lado (las etiquetas ANTES/DESPUÉS ya vienen en la plantilla)
        pptxAddEvidenciaImage(sl, antes,   { x:0.6, y:1.85, w:3.2, h:4.0, sizing:{ type:'contain', w:3.2, h:4.0 } });
        pptxAddEvidenciaImage(sl, despues, { x:4.05, y:1.85, w:3.2, h:4.0, sizing:{ type:'contain', w:3.2, h:4.0 } });

        // Mercaderista / PDV (el cuadrito ya viene en la plantilla, solo el texto)
        sl.addText([
            { text: 'MERCADERISTA: ', options: { bold:true, breakLine:false } },
            { text: safe(usersplit[1]).trim().toUpperCase(), options: { breakLine:true } },
            { text: 'PDV: ', options: { bold:true, breakLine:false } },
            { text: local_c.toUpperCase(), options: { breakLine:false } }
        ], { x:8.3, y:1.66, w:4.2, h:0.67, align:'center', valign:'middle', color:'1F3864', fontSize:11, fontFace:'Calibri' });

        // Datos del registro
        sl.addText(textArray, { x:8.3, y:2.6, w:4.3, h:4.3, fontSize:11, fontFace:'Calibri', margin:1, lineSpacingMultiple:1.15 });

    } else {
        var sl = pptx.addSlide({ masterName: 'MASTER_SLIDE' });
        sl.background = { path: 'images/slide_secundaria.png' };

        sl.addText(textArray, { x:7.5, y:2.4, w:5, h:1.4, color:'ABABAB', margin:1 });

        var unica = imgCache[srcs.antes] || srcs.antes;
        pptxAddEvidenciaImage(sl, unica, { x:2, y:1.9, w:3.5, h:3.5 });
    }
}

// data:URI precargado -> 'data', URL remota (no se pudo precargar) -> 'path'
function pptxAddEvidenciaImage(sl, src, options) {
    if (!src) return;
    var key = (typeof src === 'string' && src.indexOf('data:') === 0) ? 'data' : 'path';
    options[key] = src;
    sl.addImage(options);
}

// ── Función principal ─────────────────────────────────────────────────────────
// Precarga las imágenes de las cards seleccionadas como base64, agrega un slide
// divisor por local (con su logo) y un slide de contenido por card. Retorna una Promise.
function pptxBuildEvidencias(pptx, grupos, gruposOrder, repVal) {
    var allUrls = [];
    gruposOrder.forEach(function(local) {
        grupos[local].forEach(function(card) {
            var srcs = pptxEvidenciaSrc(card);
            [srcs.antes, srcs.despues].forEach(function(url) {
                if (url && allUrls.indexOf(url) === -1) allUrls.push(url);
            });
        });
    });

    showToast('⏳ Cargando imágenes...', 'loading');

    return pptxPreloadImages(allUrls).then(function(imgCache) {
        var bar   = document.getElementById('pptx-bar');
        var pctEl = document.getElementById('pptx-pct');
        if (bar)   bar.style.width = '92%';
        if (pctEl) pctEl.textContent = '92%  Generando slides...';

        gruposOrder.forEach(function(local) {
            pptxAddEvidenciaDivider(pptx, local, repVal);
            grupos[local].forEach(function(card) {
                pptxAddEvidenciaSlide(pptx, card, repVal, imgCache);
            });
        });

        if (bar)   bar.style.width = '98%';
        if (pctEl) pctEl.textContent = '98%  Escribiendo archivo...';
    });
}
</script>
