/**
 * COMPONENTE: pptx-slide-multiphoto.php
 * Genera slides de contenido para reportes con múltiples fotos por local
 * (Exhibiciones, etc.). Agrupa por brand/cadena, una slide de divisor
 * por grupo y slides de 2 fotos por fila.
 *
 * Expone:
 *   pptxBuildMultiphoto(pptx, grupos, gruposOrder) → Promise
 *     - grupos      : { brand: [{src, tipo, local, gestor, fecha, hora}] }
 *     - gruposOrder : string[] — orden de aparición de brands
 *
 * Requiere: pptx-base.php (showToast, updatePct via pptx-bar / pptx-pct)
 */
<script>
// ── Mapa de logos por brand ───────────────────────────────────────────────────
var LOGO_MAP = {
    'cruz azul':             'images/logos_unilever_webp/cruz_azul.webp',
    'farmacias economicas':  'images/logos_unilever_webp/Farmacias_economicas.webp',
    'farmacias mia':         'images/logos_unilever_webp/Farmacias_Mia.webp',
    'fybeca':                'images/logos_unilever_webp/Fybeca.webp',
    'medicity':              'images/logos_unilever_webp/medicity.webp',
    'pharmacys':             'images/logos_unilever_webp/pharmacys.webp',
    'sana sana':             'images/logos_unilever_webp/Sana_Sana.webp',
    'coral':                 'images/logos_unilever_webp/Coral.webp',
    'megassi':               'images/logos_unilever_webp/megassi.webp',
    'distribuidora molina':  'images/logos_unilever_webp/Distribuidora_molina.webp',
    'lemanos':               'images/logos_unilever_webp/lemanos-sas.webp',
    'riveras':               'images/logos_unilever_webp/riveras-market.webp'
};

// Normaliza el nombre del local y busca en el mapa
function multiphotoFindLogo(localName) {
    var brand = (localName.split(' - ')[0] || localName).trim()
        .toLowerCase()
        .normalize('NFD').replace(/[̀-ͯ]/g, '');
    return LOGO_MAP[brand] || null;
}

// Construye el array de texto descriptivo para un item de foto
function multiphotoDesc(f) {
    var rows = [
        { text: 'Local: ',      options: { bold:true, color:'2a6496', breakLine:false } },
        { text: f.local,        options: { color:'333333', breakLine:true } },
        { text: 'Gestor: ',     options: { bold:true, color:'2a6496', breakLine:false } },
        { text: f.gestor,       options: { color:'333333', breakLine:true } }
    ];
    if (f.categoria) {
        rows.push(
            { text: 'Categoría: ', options: { bold:true, color:'2a6496', breakLine:false } },
            { text: f.categoria + (f.subcategoria ? ' / ' + f.subcategoria : ''), options: { color:'333333', breakLine:true } }
        );
    }
    if (f.tipo) {
        rows.push(
            { text: 'Exhibición: ', options: { bold:true, color:'2a6496', breakLine:false } },
            { text: f.tipo,          options: { color:'333333', breakLine:true } }
        );
    }
    rows.push(
        { text: 'Fecha/Hora: ', options: { bold:true, color:'2a6496', breakLine:false } },
        { text: f.fecha + '  ' + f.hora, options: { color:'333333', breakLine:false } }
    );
    return rows;
}

// Agrega slides de divisor + contenido para un brand
function multiphotoAddGroup(pptx, brand, fotosArr, imgCache) {
    var logo = multiphotoFindLogo(brand);

    // Slide divisor — usa el mismo fondo base, el logo se ubica debajo de la banda/título
    pptx.defineSlideMaster({ title: 'MASTER_DIVIDER', background: { path: 'images/fondo_base_unilever.webp' } });
    var divSlide = pptx.addSlide({ masterName: 'MASTER_DIVIDER' });
    if (logo) {
        divSlide.addImage({ path: logo, x:4.17, y:2.4, w:5.0, h:3.5,
            sizing: { type:'contain', w:5.0, h:3.5 } });
    } else {
        divSlide.addText(brand, { x:0.5, y:2.9, w:12.34, h:2.5,
            fontSize:40, bold:true, align:'center', color:'222222', fontFace:'Calibri' });
    }

    // Ordenar por gestor
    fotosArr.sort(function(a, b) { return a.gestor.localeCompare(b.gestor); });

    // Las fotos arrancan en y:1.7 para dejar visible la banda y el título del fondo
    for (var i = 0; i < fotosArr.length; i += 2) {
        var f1 = fotosArr[i];
        var f2 = (fotosArr[i + 1] && fotosArr[i + 1].gestor === f1.gestor) ? fotosArr[i + 1] : null;
        if (!f2 && fotosArr[i + 1]) { i--; }

        var sl = pptx.addSlide({ masterName: 'MASTER_SLIDE' });

        var d1 = imgCache[f1.src] || f1.src;
        if (f2) {
            var d2 = imgCache[f2.src] || f2.src;
            sl.addImage({ data:d1, x:1.8,  y:1.7, w:4.0, h:3.85, sizing:{ type:'contain', w:4.0, h:3.85 } });
            sl.addImage({ data:d2, x:7.53, y:1.7, w:4.0, h:3.85, sizing:{ type:'contain', w:4.0, h:3.85 } });
            sl.addShape(pptx.shapes.LINE, { x:6.67, y:1.85, w:0, h:3.55, line:{ color:'DDDDDD', width:0.5 } });
            sl.addText(multiphotoDesc(f1), { x:1.8,  y:5.7, w:4.0, h:1.55, fontSize:13, fontFace:'Calibri', lineSpacingMultiple:1.25, valign:'top' });
            sl.addText(multiphotoDesc(f2), { x:7.53, y:5.7, w:4.0, h:1.55, fontSize:13, fontFace:'Calibri', lineSpacingMultiple:1.25, valign:'top' });
        } else {
            sl.addImage({ data:d1, x:4.67, y:1.7, w:4.0, h:3.85, sizing:{ type:'contain', w:4.0, h:3.85 } });
            sl.addText(multiphotoDesc(f1), { x:4.67, y:5.7, w:4.0, h:1.55, fontSize:13, fontFace:'Calibri', lineSpacingMultiple:1.25, align:'left', valign:'top' });
        }
    }
}

// ── Función principal ─────────────────────────────────────────────────────────
// Precarga todas las imágenes, construye los slides y retorna una Promise.
function pptxBuildExhibiciones(pptx, grupos, gruposOrder) {

    // 1. Recolectar URLs únicas
    var allUrls = [];
    gruposOrder.forEach(function(brand) {
        grupos[brand].forEach(function(f) {
            if (f.src && allUrls.indexOf(f.src) === -1) allUrls.push(f.src);
        });
    });

    var totalImgs = allUrls.length;
    var loadedImgs = 0;
    var imgCache = {};

    function updatePct(loaded, total) {
        var pct = total > 0 ? Math.round((loaded / total) * 85) : 0;
        var bar   = document.getElementById('pptx-bar');
        var pctEl = document.getElementById('pptx-pct');
        if (bar)   bar.style.width = pct + '%';
        if (pctEl) pctEl.textContent = pct + '%  (' + loaded + '/' + total + ' fotos)';
    }

    showToast('⏳ Cargando imágenes...', 'loading');

    // 2. Precargar todas las imágenes como base64
    var fetchAll = allUrls.map(function(url) {
        return fetch(url, { mode:'cors' })
            .then(function(r) { return r.blob(); })
            .then(function(blob) {
                return new Promise(function(resolve) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        imgCache[url] = e.target.result;
                        loadedImgs++;
                        updatePct(loadedImgs, totalImgs);
                        resolve();
                    };
                    reader.readAsDataURL(blob);
                });
            })
            .catch(function() {
                // CORS o 404: usar URL directa
                loadedImgs++;
                updatePct(loadedImgs, totalImgs);
            });
    });

    // 3. Construir slides cuando todas las imágenes estén listas
    return Promise.all(fetchAll).then(function() {
        var bar   = document.getElementById('pptx-bar');
        var pctEl = document.getElementById('pptx-pct');
        if (bar)   bar.style.width = '92%';
        if (pctEl) pctEl.textContent = '92%  Generando slides...';

        gruposOrder.forEach(function(brand) {
            multiphotoAddGroup(pptx, brand, grupos[brand], imgCache);
        });

        if (bar)   bar.style.width = '98%';
        if (pctEl) pctEl.textContent = '98%  Escribiendo archivo...';
    });
}
</script>
