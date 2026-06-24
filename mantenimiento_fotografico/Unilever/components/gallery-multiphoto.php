<!-- =========================================================
     GALERÍA EXHIBICIONES — UX 2.0
     ========================================================= -->

<div id="exhZoomModal" onclick="exhCloseZoom(event)"
     style="position:fixed;top:0;left:0;width:100%;height:100%;
            background:rgba(0,0,0,0.9);display:flex;justify-content:center;
            align-items:center;z-index:9999;opacity:0;pointer-events:none;
            transition:opacity .3s;backdrop-filter:blur(5px);">
    <span onclick="document.getElementById('exhZoomModal').classList.remove('active');document.body.style.overflow='';"
          style="position:absolute;top:20px;right:30px;color:#fff;font-size:40px;
                 font-weight:bold;cursor:pointer;user-select:none;">&times;</span>
    <img id="exhZoomImg" src=""
         style="max-width:90vw;max-height:90vh;object-fit:contain;
                border:4px solid #fff;border-radius:8px;
                box-shadow:0 20px 50px rgba(0,0,0,.5);
                transform:scale(0.85);transition:transform .3s;">
</div>

<style>
/* Modal */
#exhZoomModal.active { opacity:1 !important; pointer-events:auto !important; }
#exhZoomModal.active #exhZoomImg { transform:scale(1) !important; }

/* Card seleccionada */
.exh-card {
    transition: box-shadow .25s ease, transform .2s ease;
}
.exh-card.exh-active {
    box-shadow: 0 0 0 3px #337ab7, 0 6px 20px rgba(51,122,183,.2) !important;
    transform: translateY(-2px);
}

/* Header del card seleccionado */
.exh-card.exh-active .exh-card-header {
    background: #2a6496 !important;
}
.exh-card.exh-active .exh-card-header strong,
.exh-card.exh-active .exh-card-header span { color: #fff !important; }

/* Checkbox principal del card */
.exh-main-cb {
    width: 20px; height: 20px;
    cursor: pointer; flex-shrink: 0; margin-top: 2px;
    accent-color: #27ae60;
    transition: transform .15s ease;
}
.exh-main-cb:hover { transform: scale(1.2); }

/* Checkbox sobre thumbnail */
.exh-photo-cb {
    position: absolute; top: 3px; left: 3px;
    width: 15px; height: 15px;
    cursor: pointer; z-index: 4;
    accent-color: #27ae60;
    opacity: 0;
    transition: opacity .2s ease, transform .15s ease;
    transform: scale(0.7);
}
/* Visible al hover del wrapper o cuando está checked */
.exh-thumb-wrap:hover .exh-photo-cb,
.exh-photo-cb:checked { opacity: 1; transform: scale(1); }

/* Thumbnail wrapper */
.exh-thumb-wrap {
    position: relative; width: 72px; height: 72px; flex-shrink: 0;
    border-radius: 4px; overflow: hidden;
    cursor: pointer;
    transition: transform .15s ease;
}
.exh-thumb-wrap:hover { transform: scale(1.05); }

/* Overlay de selección sobre thumbnail */
.exh-thumb-overlay {
    position: absolute; inset: 0;
    background: rgba(0,0,0,0.65);
    opacity: 0; transition: opacity .2s ease;
    pointer-events: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 20px; color: #fff;
}
.exh-photo-cb:checked ~ .exh-thumb-overlay { pointer-events: auto; }
.exh-photo-cb:checked ~ .exh-thumb-overlay { opacity: 1; }

/* Animación de check en cascada */
@keyframes cbPop {
    0%   { transform: scale(0.4); opacity: 0; }
    60%  { transform: scale(1.25); }
    100% { transform: scale(1); opacity: 1; }
}
.exh-photo-cb.cb-pop {
    animation: cbPop .28s cubic-bezier(.34,1.56,.64,1) forwards;
}

/* Thumbnail activo (navegación) */
.exh-thumb-wrap.exh-thumb-active img {
    border: 2px solid #337ab7 !important;
    transform: scale(0.93);
}
</style>

<script>
var exhState = {};

function exhInitAll() {
    document.querySelectorAll('.exh-card').forEach(function(card) {
        var cardId = card.id;
        var images = JSON.parse(card.dataset.images || '[]');
        if (!images.length) return;

        exhState[cardId] = { idx: 0, selected: new Set() };

        var thumbsEl = document.getElementById('thumbs_' + cardId);
        thumbsEl.innerHTML = '';

        images.forEach(function(src, i) {
            var wrap = document.createElement('div');
            wrap.className = 'exh-thumb-wrap';
            wrap.id = 'wrap_' + cardId + '_' + i;

            var img = document.createElement('img');
            img.src = src;
            img.style.cssText = 'width:72px;height:72px;object-fit:cover;display:block;border:2px solid transparent;transition:border-color .15s,transform .15s;';
            // Clic en imagen del thumbnail = navegar + SELECCIONAR si no está seleccionada
            img.onclick = (function(id, idx) {
                return function(e) {
                    e.stopPropagation();
                    exhSelect(id, idx);
                    var state = exhState[id];
                    if (state && !state.selected.has(idx)) {
                        var pcb = document.getElementById('photo_cb_' + id + '_' + idx);
                        if (pcb) { pcb.checked = true; pcb.dispatchEvent(new Event('change')); }
                    }
                };
            })(cardId, i);

            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.className = 'exh-photo-cb';
            cb.id = 'photo_cb_' + cardId + '_' + i;
            cb.dataset.cardId = cardId;
            cb.dataset.idx = i;
            cb.dataset.src = src;
            cb.addEventListener('change', (function(cid, idx2, total) {
                return function() {
                    if (this.checked) {
                        exhState[cid].selected.add(idx2);
                    } else {
                        exhState[cid].selected.delete(idx2);
                    }
                    exhSyncCardState(cid, total);
                };
            })(cardId, i, images.length));

            // Overlay ✓ — clickeable para toggle de selección
            var overlay = document.createElement('div');
            overlay.className = 'exh-thumb-overlay';
            overlay.innerHTML = '✓';
            overlay.style.cursor = 'pointer';
            overlay.addEventListener('click', (function(cid, idx2) {
                return function(e) {
                    e.stopPropagation();
                    var state = exhState[cid];
                    if (!state) return;
                    var pcb = document.getElementById('photo_cb_' + cid + '_' + idx2);
                    if (pcb) {
                        pcb.checked = !state.selected.has(idx2);
                        pcb.dispatchEvent(new Event('change'));
                    }
                };
            })(cardId, i));

            wrap.appendChild(img);
            wrap.appendChild(cb);
            wrap.appendChild(overlay);
            thumbsEl.appendChild(wrap);
        });

        // Card-level checkbox: selecciona/deselecciona TODAS con animación en cascada
        var cardCb = card.querySelector('.exh-main-cb');
        if (cardCb) {
            cardCb.addEventListener('change', (function(cid, imgs) {
                return function() {
                    var checking = this.checked;
                    if (checking) {
                        exhState[cid].selected = new Set();
                    } else {
                        exhState[cid].selected.clear();
                    }
                    imgs.forEach(function(_, i) {
                        setTimeout(function() {
                            var pcb = document.getElementById('photo_cb_' + cid + '_' + i);
                            if (!pcb) return;
                            pcb.checked = checking;
                            if (checking) {
                                exhState[cid].selected.add(i);
                                pcb.classList.remove('cb-pop');
                                void pcb.offsetWidth;
                                pcb.classList.add('cb-pop');
                            }
                        }, i * 55); // stagger 55ms por foto
                    });
                    // Aplicar estado visual al card con pequeño delay
                    setTimeout(function() {
                        exhSyncCardState(cid, imgs.length);
                    }, imgs.length * 55 + 50);
                };
            })(cardId, images));
        }

        exhUpdateMain(cardId);
    });

    exhSetupPagination();
}

// Sincroniza visual del card y el botón global "Deseleccionar todo"
function exhSyncCardState(cardId, total) {
    var card   = document.getElementById(cardId);
    var cardCb = card ? card.querySelector('.exh-main-cb') : null;
    var sel    = exhState[cardId] ? exhState[cardId].selected.size : 0;

    if (cardCb) {
        cardCb.checked       = sel > 0;
        cardCb.indeterminate = sel > 0 && sel < total;
    }

    // Glow en el card si tiene algo seleccionado
    if (card) {
        if (sel > 0) {
            card.classList.add('exh-active');
        } else {
            card.classList.remove('exh-active');
        }
    }

    // Actualizar botón "Deseleccionar todo"
    if (typeof window.exhUpdateClearBtn === 'function') {
        window.exhUpdateClearBtn();
    }

    // Actualizar badge contador en el header
    exhActualizarContador();
}

function exhActualizarContador() {
    var totalFotos = 0;
    var localesSet = new Set();
    Object.keys(exhState).forEach(function(id) {
        if (exhState[id] && exhState[id].selected && exhState[id].selected.size > 0) {
            totalFotos += exhState[id].selected.size;
            var card = document.getElementById(id);
            localesSet.add(card && card.dataset.local ? card.dataset.local : id);
        }
    });
    var totalCards = localesSet.size;

    var texto = totalFotos > 0
        ? totalFotos + ' foto' + (totalFotos !== 1 ? 's' : '') +
          ' · ' + totalCards + ' local' + (totalCards !== 1 ? 'es' : '')
        : '';

    // Badge en el header
    var badge = document.getElementById('selection-count');
    if (badge) {
        badge.textContent  = texto;
        badge.style.display = totalFotos > 0 ? 'inline-flex' : 'none';
    }

    // Contador en el sidebar
    var sideCount = document.getElementById('sidebar-count');
    if (sideCount) {
        sideCount.textContent  = texto;
        sideCount.style.display = totalFotos > 0 ? 'block' : 'none';
    }

    // Botón "Ninguno" en sidebar — solo visible con selección activa
    var clearSidebar = document.getElementById('clearAll-sidebar');
    if (clearSidebar) {
        clearSidebar.style.display = totalFotos > 0 ? '' : 'none';
    }
}

// ── PAGINACIÓN ────────────────────────────────────────────────────────────────
var exhPage = 1;

function exhMakePagination(suffix) {
    var c    = document.createElement('div');
    c.id     = 'exh-pag-' + suffix;
    c.style.cssText = 'display:flex;justify-content:center;align-items:center;gap:12px;margin:10px 0;';

    var prev = document.createElement('button');
    prev.type = 'button'; prev.id = 'exh-prev-' + suffix;
    prev.innerHTML = '&#9664; Anterior'; prev.className = 'btn btn-default';
    prev.onclick = function() { exhGoToPage(exhPage - 1); };

    var info = document.createElement('span');
    info.id  = 'exh-info-' + suffix;
    info.style.cssText = 'font-size:13px;font-weight:600;color:#444;min-width:110px;text-align:center;';

    var next = document.createElement('button');
    next.type = 'button'; next.id = 'exh-next-' + suffix;
    next.innerHTML = 'Siguiente &#9654;'; next.className = 'btn btn-default';
    next.onclick = function() { exhGoToPage(exhPage + 1); };

    c.appendChild(prev); c.appendChild(info); c.appendChild(next);
    return c;
}

function exhSetupPagination() {
    ['top','bot'].forEach(function(s) {
        var old = document.getElementById('exh-pag-' + s);
        if (old) old.remove();
    });
    var rows = document.querySelectorAll('#data tr');
    if (rows.length <= 4) return;
    exhPage = 1;
    exhShowPage(1);
    var tabla = document.querySelector('#data').closest('table');
    var tablaParent = tabla.parentNode;
    tablaParent.insertBefore(exhMakePagination('top'), tabla);
    tablaParent.insertBefore(exhMakePagination('bot'), tabla.nextSibling);
    exhUpdatePaginationControls();
}

function exhShowPage(page) {
    var rows  = document.querySelectorAll('#data tr');
    var start = (page - 1) * 4;
    rows.forEach(function(row, i) {
        row.style.display = (i >= start && i < start + 4) ? '' : 'none';
    });
    exhPage = page;
    exhUpdatePaginationControls();
}

function exhGoToPage(page) {
    var rows       = document.querySelectorAll('#data tr');
    var totalPages = Math.ceil(rows.length / 4);
    if (page < 1 || page > totalPages) return;
    exhShowPage(page);
    var cont = document.getElementById('cards-container');
    if (cont) cont.scrollTo({ top: 0, behavior: 'smooth' });
}

function exhUpdatePaginationControls() {
    var rows       = document.querySelectorAll('#data tr');
    var totalPages = Math.ceil(rows.length / 4);
    ['top','bot'].forEach(function(s) {
        var info = document.getElementById('exh-info-' + s);
        var prev = document.getElementById('exh-prev-' + s);
        var next = document.getElementById('exh-next-' + s);
        if (!info) return;
        info.textContent   = 'Página ' + exhPage + ' de ' + totalPages;
        prev.disabled      = (exhPage === 1);
        next.disabled      = (exhPage === totalPages);
        prev.style.opacity = prev.disabled ? '0.4' : '1';
        next.style.opacity = next.disabled ? '0.4' : '1';
    });
}

// Selección de thumbnail
function exhSelect(cardId, index) {
    exhState[cardId].idx = index;
    exhUpdateMain(cardId);
}

// Actualiza imagen, contador, botones y highlight de thumbnails
function exhUpdateMain(cardId) {
    var card       = document.getElementById(cardId);
    var images     = JSON.parse(card.dataset.images || '[]');
    var idx        = (exhState[cardId] || { idx: 0 }).idx;
    var total      = images.length;
    var mainImg    = document.getElementById('main_' + cardId);
    var counter    = document.getElementById('counter_' + cardId);
    var limitBadge = document.getElementById('limit_' + cardId);
    var btnPrev    = document.getElementById('btn_prev_' + cardId);
    var btnNext    = document.getElementById('btn_next_' + cardId);

    // Fade imagen
    mainImg.style.opacity = 0;
    setTimeout(function() {
        mainImg.src = images[idx];
        mainImg.style.opacity = 1;
    }, 130);

    // Contador
    if (counter) {
        counter.textContent = (idx + 1) + ' / ' + total;
        counter.style.background = (idx === total - 1 && total > 1)
            ? 'rgba(106,13,173,0.8)'   // morado al llegar al final
            : 'rgba(0,0,0,0.55)';
    }

    // Badge "Última foto"
    if (limitBadge) limitBadge.style.display = (idx === total - 1 && total > 1) ? 'block' : 'none';

    // Flechas desactivadas en extremos
    if (btnPrev) {
        btnPrev.style.opacity = idx === 0 ? '0.2' : '0.85';
        btnPrev.style.cursor  = idx === 0 ? 'default' : 'pointer';
        btnPrev.disabled = idx === 0;
    }
    if (btnNext) {
        btnNext.style.opacity = idx === total - 1 ? '0.2' : '0.85';
        btnNext.style.cursor  = idx === total - 1 ? 'default' : 'pointer';
        btnNext.disabled = idx === total - 1;
    }

    // Actualizar categoría/subcategoría según la foto actual
    var catEl = document.getElementById('cat_display_' + cardId);
    if (catEl) {
        var cats  = (card.dataset.categorias  || '').split('|');
        var scats = (card.dataset.subcategorias || '').split('|');
        var cat   = cats[idx]  || '';
        var scat  = scats[idx] || '';
        catEl.innerHTML = '<b style="color:#333;">Categoría:</b> ' + cat +
            (scat ? '<span style="color:#999;"> / </span>' + scat : '');
    }

    // Highlight thumbnail activo
    var wraps = document.getElementById('thumbs_' + cardId).querySelectorAll('.exh-thumb-wrap');
    wraps.forEach(function(w, i) {
        if (i === idx) {
            w.classList.add('exh-thumb-active');
        } else {
            w.classList.remove('exh-thumb-active');
        }
    });
}

// Navegación sin loop — respeta límite
function exhNav(cardId, dir) {
    var card   = document.getElementById(cardId);
    var images = JSON.parse(card.dataset.images || '[]');
    var state  = exhState[cardId] || { idx: 0, selected: new Set() };
    var newIdx = state.idx + dir;
    if (newIdx < 0 || newIdx >= images.length) return;
    exhState[cardId].idx = newIdx;
    exhUpdateMain(cardId);
    var activeWrap = document.getElementById('thumbs_' + cardId)
                              .querySelectorAll('.exh-thumb-wrap')[newIdx];
    if (activeWrap) activeWrap.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Clic en imagen principal → toggle selección del card
// Si está seleccionado: deselecciona todo directamente
// Si no está seleccionado: selecciona todo con animación en cascada
function exhToggleCard(cardId) {
    var card   = document.getElementById(cardId);
    if (!card) return;
    var images = JSON.parse(card.dataset.images || '[]');
    var state  = exhState[cardId];
    if (!state) return;

    var yaSeleccionado = state.selected.size > 0;

    if (yaSeleccionado) {
        // — DESELECCIONAR: limpiar estado y UI de inmediato —
        state.selected.clear();
        images.forEach(function(_, i) {
            var pcb = document.getElementById('photo_cb_' + cardId + '_' + i);
            if (pcb) pcb.checked = false;
        });
        var cardCb = card.querySelector('.exh-main-cb');
        if (cardCb) { cardCb.checked = false; cardCb.indeterminate = false; }
        card.classList.remove('exh-active');
    } else {
        // — SELECCIONAR: disparar el checkbox del card para aprovechar la animación en cascada —
        var cardCb = card.querySelector('.exh-main-cb');
        if (cardCb) {
            cardCb.checked = true;
            cardCb.dispatchEvent(new Event('change'));
        }
    }
}

function exhZoom(cardId) {
    var card   = document.getElementById(cardId);
    var images = JSON.parse(card.dataset.images || '[]');
    var idx    = (exhState[cardId] || { idx: 0 }).idx;
    document.getElementById('exhZoomImg').src = images[idx];
    document.getElementById('exhZoomModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function exhCloseZoom(e) {
    if (e.target.id !== 'exhZoomImg') {
        document.getElementById('exhZoomModal').classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Retorna URLs de las fotos seleccionadas (o todas si no hay selección)
function exhGetSelectedImages(cardId) {
    var card   = document.getElementById(cardId);
    var images = JSON.parse(card.dataset.images || '[]');
    var state  = exhState[cardId];
    if (!state || state.selected.size === 0) return images;
    return images.filter(function(_, i) { return state.selected.has(i); });
}
</script>
