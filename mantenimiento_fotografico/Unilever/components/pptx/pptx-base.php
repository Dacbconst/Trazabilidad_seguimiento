/**
 * COMPONENTE: pptx-base.php
 * Funciones compartidas de PowerPoint: toast de progreso, init del objeto PPT,
 * slide de portada y guardado del archivo.
 *
 * Expone:
 *   showToast(msg, type)                     — toast esquina superior derecha
 *   pptxCreateBase(config) → pptx            — crea el objeto PptxGenJS + portada + masters
 *   pptxSave(pptx, filename) → Promise       — guarda el archivo
 *   pptxPreloadImages(urls) → Promise<map>   — precarga imágenes remotas como base64
 *
 * config = { author, company, title, subject,
 *            finiciofinal, ffinalfinal,
 *            supervisor, mercaderista, ciudad }
 */
<script>
// ── Toast de progreso ─────────────────────────────────────────────────────────
var _toastInterval = null;

function showToast(msg, type) {
    if (_toastInterval) { clearInterval(_toastInterval); _toastInterval = null; }

    var t = document.getElementById('pptx-toast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'pptx-toast';
        t.style.cssText = [
            'position:fixed', 'top:18px', 'right:18px',
            'z-index:2147483647', 'background:#2a6496', 'color:#fff',
            'padding:14px 18px', 'border-radius:10px', 'font-size:13px',
            'font-family:Poppins,sans-serif', 'box-shadow:0 6px 24px rgba(0,0,0,.4)',
            'min-width:270px', 'max-width:340px',
            'transition:opacity .25s ease', 'opacity:0'
        ].join(';');
        document.body.appendChild(t);
        setTimeout(function() { t.style.opacity = '1'; }, 20);
    }

    if (type === 'loading') {
        t.style.background = '#2a6496';
        t.innerHTML =
            '<div style="font-weight:600;margin-bottom:8px;">' + msg + '</div>' +
            '<div style="background:rgba(255,255,255,0.25);border-radius:6px;height:8px;overflow:hidden;">' +
                '<div id="pptx-bar" style="height:100%;background:#fff;border-radius:6px;width:0%;transition:width .2s ease;"></div>' +
            '</div>' +
            '<div id="pptx-pct" style="font-size:12px;margin-top:6px;opacity:0.9;font-weight:600;">0%</div>';

    } else if (type === 'ok') {
        t.style.background = '#27ae60';
        t.innerHTML =
            '<div style="font-weight:600;">✅ Descarga completada</div>' +
            '<div style="background:rgba(255,255,255,0.3);border-radius:4px;height:6px;margin-top:8px;">' +
                '<div style="height:100%;background:#fff;border-radius:4px;width:100%;"></div>' +
            '</div>';
        setTimeout(function() {
            t.style.opacity = '0';
            setTimeout(function() { if (t.parentNode) t.remove(); }, 300);
        }, 3500);

    } else if (type === 'error') {
        t.style.background = '#8b0000';
        t.innerHTML = '<div style="font-weight:600;">⚠️ ' + msg + '</div>';
        setTimeout(function() {
            t.style.opacity = '0';
            setTimeout(function() { if (t.parentNode) t.remove(); }, 300);
        }, 4000);
    }
}

// ── Init PPT + Portada + Masters ──────────────────────────────────────────────
function pptxCreateBase(config) {
    var pptx = new PptxGenJS();
    pptx.author  = config.author  || 'Unilever';
    pptx.company = config.company || 'Unilever';
    pptx.subject = config.subject || 'Evidencia Fotográfica';
    pptx.title   = config.title   || 'Evidencia Fotográfica';
    pptx.layout  = 'LAYOUT_WIDE';

    // Master para la portada — fondo: plantilla de presentación Unilever
    pptx.defineSlideMaster({
        title: 'MASTER_SLIDEP',
        background: { path: 'images/plantilla_presentacion_unilever.webp' }
    });

    // Slide de portada — el fondo ya trae el diseño, solo se agrega el bloque de info
    // sobre una tarjeta semitransparente para que el texto sea legible
    var slidePortada = pptx.addSlide({ masterName: 'MASTER_SLIDEP' });
    slidePortada.addText('EVIDENCIA FOTOGRÁFICA', {
        x:0.5, y:1.6, w:12.34, h:1.2,
        fontSize:40, bold:true, align:'center', color:'FFFFFF', fontFace:'Calibri'
    });
    slidePortada.addShape(pptx.shapes.ROUNDED_RECTANGLE,
        { x:3.17, y:3.4, w:7.0, h:3.4, fill: { color:'FFFFFF', transparency:15 }, rectRadius:0.12 });
    slidePortada.addText([
        { text: 'Fecha Inicio: ', options: { bold:true, breakLine:false, color:'1F3864' } },
        { text: (config.finiciofinal || '') + '    ', options: { breakLine:false, color:'333333' } },
        { text: 'Fecha Fin: ',    options: { bold:true, breakLine:false, color:'1F3864' } },
        { text: config.ffinalfinal || '', options: { breakLine:true, color:'333333' } },
        { text: 'Tipo de Reporte: ', options: { bold:true, breakLine:false, color:'1F3864' } },
        { text: config.tipoReporte || '', options: { breakLine:true, color:'333333' } },
        { text: 'Supervisor: ',   options: { bold:true, breakLine:false, color:'1F3864' } },
        { text: config.supervisor === '.' ? 'Todos' : (config.supervisor || ''), options: { breakLine:true, color:'333333' } },
        { text: 'Mercaderista: ', options: { bold:true, breakLine:false, color:'1F3864' } },
        { text: config.mercaderista === '.' ? 'Todos' : (config.mercaderista || ''), options: { breakLine:true, color:'333333' } },
        { text: 'Ciudad: ',       options: { bold:true, breakLine:false, color:'1F3864' } },
        { text: config.ciudad === '.' ? 'Todas' : (config.ciudad || ''), options: { breakLine:true, color:'333333' } }
    ], { x:3.57, y:3.7, w:6.2, h:2.8, fontSize:18, fontFace:'Calibri', lineSpacingMultiple:1.35 });

    // Master para slides de contenido — fondo base Unilever (banda superior + título)
    pptx.defineSlideMaster({
        title: 'MASTER_SLIDE',
        background: { path: 'images/fondo_base_unilever.webp' },
        slideNumber: { x:12.8, y:7.1, color:'AAAAAA', fontSize:9 }
    });

    return pptx;
}

// ── Guardar archivo ───────────────────────────────────────────────────────────
function pptxSave(pptx, filename) {
    return pptx.writeFile({ fileName: filename || 'Presentacion.pptx' });
}

// ── Precarga de imágenes remotas como base64 ───────────────────────────────────
// Evita insertar URLs remotas directamente en el PPT (PptxGenJS no puede
// reincrustarlas y PowerPoint termina mostrando "se encontró un problema con
// el contenido"). Devuelve un mapa { url: dataURI } — si una imagen falla
// (CORS/404), simplemente no aparece en el mapa y el caller debe usar la URL
// original como 'path' (fallback de PptxGenJS).
function pptxPreloadImages(urls) {
    var imgCache   = {};
    var totalImgs  = urls.length;
    var loadedImgs = 0;

    function updatePct(loaded, total) {
        var pct   = total > 0 ? Math.round((loaded / total) * 85) : 0;
        var bar   = document.getElementById('pptx-bar');
        var pctEl = document.getElementById('pptx-pct');
        if (bar)   bar.style.width = pct + '%';
        if (pctEl) pctEl.textContent = pct + '%  (' + loaded + '/' + total + ' fotos)';
    }

    var fetchAll = urls.map(function(url) {
        return fetch(url, { mode: 'cors' })
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
                loadedImgs++;
                updatePct(loadedImgs, totalImgs);
            });
    });

    return Promise.all(fetchAll).then(function() { return imgCache; });
}
</script>
