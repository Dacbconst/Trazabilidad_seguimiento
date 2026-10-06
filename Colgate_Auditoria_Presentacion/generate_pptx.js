const pptxgen = require('../EpsonReport/node_modules/pptxgenjs');
const path = require('path');

const INK = '0F172A', DARK = 'C4161C', ON_DARK = 'FFE3E3', LIGHT = 'FBF5F4', RED = 'D52B24', BLUE = '1F2937', GREEN = '6B7280', GRAY = '475569';
const img = (n) => path.resolve(__dirname, 'assets/img', n);
const F = 'Calibri';

function dot(s, x, y, color) {
    s.addShape('ellipse', { x, y, w: 0.42, h: 0.42, fill: { color }, line: { color, width: 0 } });
}
function item(s, x, y, w, color, title, desc, dark, size = {}) {
    dot(s, x, y + 0.03, color);
    s.addText(title, { x: x + 0.6, y, w: w - 0.6, h: 0.38, fontFace: F, fontSize: size.t || 15, bold: true, color: dark ? 'FFFFFF' : INK, valign: 'middle' });
    s.addText(desc, { x: x + 0.6, y: y + 0.4, w: w - 0.6, h: size.h || 0.85, fontFace: F, fontSize: size.d || 11, color: dark ? ON_DARK : GRAY, valign: 'top' });
}
function pill(s, x, y, w, text) {
    s.addShape('roundRect', { x, y, w, h: 0.4, rectRadius: 0.2, fill: { color: '9E1017' }, line: { color: 'E58A8E', width: 1 } });
    s.addText(text, { x, y, w, h: 0.4, fontFace: F, fontSize: 10.5, bold: true, color: 'FFFFFF', align: 'center', valign: 'middle' });
}
function luckyBadge(s, x, y) {
    s.addShape('roundRect', { x, y, w: 1.55, h: 0.62, rectRadius: 0.12, fill: { color: 'FFFFFF' }, line: { color: 'FFFFFF', width: 0 } });
    s.addImage({ path: img('lucky_logo.png'), x: x + 0.1, y: y + 0.06, w: 1.35, h: 0.5, sizing: { type: 'contain', w: 1.35, h: 0.5 } });
}
function colgateBadge(s, x, y) {
    s.addShape('roundRect', { x, y, w: 2.1, h: 0.62, rectRadius: 0.12, fill: { color: 'FFFFFF' }, line: { color: 'E4D6D4', width: 0.75 } });
    s.addImage({ path: img('colgate_logo.png'), x: x + 0.12, y: y + 0.08, w: 1.86, h: 0.46, sizing: { type: 'contain', w: 1.86, h: 0.46 } });
}
function frame(s, p, x, y, w, h) {
    s.addShape('roundRect', { x: x + 0.05, y: y + 0.12, w, h, rectRadius: 0.1, fill: { color: 'E4D6D4' }, line: { color: 'E4D6D4', width: 0 } });
    s.addImage({ path: img(p), x, y, w, h });
}

async function main() {
    const pres = new pptxgen();
    pres.layout = 'LAYOUT_WIDE';
    pres.author = 'Grupo Lucky';
    pres.title = 'Auditoría de PDV y Rutas Colgate - Presentación Ejecutiva';

    // 1. Portada
    {
        const s = pres.addSlide();
        s.background = { color: LIGHT };
        s.addShape('rect', { x: 0, y: 0, w: 5.2, h: 7.5, fill: { color: DARK }, line: { color: DARK, width: 0 } });
        colgateBadge(s, 0.7, 0.45);
        s.addText('Auditoría de PDV y Rutas', { x: 0.7, y: 2.0, w: 4.3, h: 2.0, fontFace: F, fontSize: 40, bold: true, color: 'FFFFFF', valign: 'top' });
        s.addText('Cada carga de la base y cada cambio en una ruta queda registrado: quién lo hizo, cuándo y desde qué archivo.', { x: 0.7, y: 4.1, w: 4.0, h: 1.3, fontFace: F, fontSize: 14, color: ON_DARK, valign: 'top' });
        pill(s, 0.7, 5.6, 1.6, 'Estado en vivo');
        pill(s, 2.45, 5.6, 2.3, 'Trazabilidad por ruta');
        luckyBadge(s, 0.7, 6.45);
        frame(s, 'auditoria_pdv.png', 6.0, 0.4, 6.6, 3.43);
        frame(s, 'auditoria_rutas.png', 5.8, 4.1, 6.2, 2.94);
    }

    // 2. Desafío
    {
        const s = pres.addSlide();
        s.background = { color: 'FFFFFF' };
        s.addShape('rect', { x: 0, y: 0, w: 6.67, h: 7.5, fill: { color: DARK }, line: { color: DARK, width: 0 } });
        colgateBadge(s, 0.7, 0.45);
        s.addText('Una base que mueve a todo el equipo en campo', { x: 0.7, y: 1.3, w: 5.4, h: 1.7, fontFace: F, fontSize: 32, bold: true, color: 'FFFFFF', valign: 'top' });
        item(s, 0.7, 3.0, 5.5, 'FFFFFF', 'Cargas de Miles de Filas', 'Una sola carga de la base de puntos de venta puede superar las 140 mil filas, y no debe interrumpirse.', true);
        item(s, 0.7, 4.3, 5.5, 'FFFFFF', 'Una Sola Carga a la Vez', 'Si dos personas cargan al mismo tiempo, la base puede quedar inconsistente.', true);
        item(s, 0.7, 5.6, 5.5, 'FFFFFF', 'Rutas que Cambian Todo el Tiempo', 'Creaciones, ediciones y eliminaciones, masivas o una por una, hechas por distintos usuarios.', true);
        item(s, 7.4, 1.9, 5.3, GREEN, 'Estado de la Carga en Vivo', 'Quién está cargando, cuánto avanzó y cuánto falta, sin tener que preguntar.', false, { t: 17, d: 12 });
        item(s, 7.4, 3.5, 5.3, GREEN, 'Trazabilidad de Cada Cambio', 'Cada ruta muestra quién la creó, editó o eliminó, cuándo y desde qué archivo.', false, { t: 17, d: 12 });
        item(s, 7.4, 5.1, 5.3, GREEN, 'Datos Íntegros', 'El sistema avisa si el PDV, el usuario o el supervisor de una ruta ya no existen.', false, { t: 17, d: 12 });
    }

    // 3. Auditoría de carga de PDV
    {
        const s = pres.addSlide();
        s.background = { color: LIGHT };
        colgateBadge(s, 0.7, 0.45);
        s.addText('Auditoría de Carga de PDV', { x: 0.5, y: 1.3, w: 3.9, h: 1.1, fontFace: F, fontSize: 26, bold: true, color: INK, valign: 'top' });
        item(s, 0.5, 2.5, 3.8, BLUE, 'Estado en Vivo', 'Libre u ocupada, quién sube, lote actual, avance y tiempo estimado. Se actualiza cada 3 segundos.', false, { h: 1.0, d: 10.5 });
        item(s, 0.5, 3.95, 3.8, RED, 'Carga Protegida', 'Solo una persona carga a la vez. Si algo se traba, se libera a los 30 minutos o con Cancelar.', false, { h: 1.0, d: 10.5 });
        item(s, 0.5, 5.4, 3.8, GREEN, 'Historial de Cargas', 'Usuario, archivo, inicio, fin, duración, filas y resultado, con filtros y búsqueda.', false, { h: 1.0, d: 10.5 });
        frame(s, 'auditoria_pdv.png', 4.6, 1.5, 8.4, 4.36);
    }

    // 4. Auditoría de rutas
    {
        const s = pres.addSlide();
        s.background = { color: LIGHT };
        s.addShape('rect', { x: 9.4, y: 0, w: 3.93, h: 7.5, fill: { color: DARK }, line: { color: DARK, width: 0 } });
        colgateBadge(s, 9.7, 0.45);
        s.addText('Auditoría de Rutas', { x: 9.7, y: 1.25, w: 3.5, h: 1.3, fontFace: F, fontSize: 30, bold: true, color: 'FFFFFF', valign: 'top' });
        item(s, 9.7, 2.5, 3.5, 'FFFFFF', 'Quién, Cuándo y Desde Dónde', 'Cada ruta muestra si fue creada, editada o eliminada, masiva o individual, por qué usuario y desde qué archivo.', true, { h: 1.2, d: 10.5, t: 14 });
        item(s, 9.7, 4.1, 3.5, 'FFFFFF', 'Filtros que se Combinan', 'Ruta, PDV, fecha de visita, usuario, supervisor, estado y evento se cruzan libremente.', true, { h: 1.0, d: 10.5, t: 14 });
        item(s, 9.7, 5.5, 3.5, 'FFFFFF', 'Integridad a la Vista', 'Una marca verde confirma que los datos existen; una alerta señala qué falta.', true, { h: 1.0, d: 10.5, t: 14 });
        frame(s, 'auditoria_rutas.png', 0.3, 1.6, 8.8, 4.18);
    }

    // 5. Beneficios
    {
        const s = pres.addSlide();
        s.background = { color: 'FFFFFF' };
        s.addShape('rect', { x: 0, y: 0, w: 4.53, h: 7.5, fill: { color: DARK }, line: { color: DARK, width: 0 } });
        colgateBadge(s, 0.6, 0.45);
        s.addText('Cada Cambio Queda con Responsable', { x: 0.6, y: 2.4, w: 3.5, h: 2.0, fontFace: F, fontSize: 30, bold: true, color: 'FFFFFF', valign: 'top' });
        s.addText('Cargas y rutas con estado en vivo, historial y datos verificados.', { x: 0.6, y: 4.5, w: 3.5, h: 0.9, fontFace: F, fontSize: 14, color: ON_DARK, valign: 'top' });
        luckyBadge(s, 0.6, 6.0);
        s.addText('Desarrollado por Grupo Lucky · © Grupo Lucky & Colgate 2026', { x: 0.6, y: 6.75, w: 3.8, h: 0.3, fontFace: F, fontSize: 9, color: ON_DARK });
        const b = [
            [BLUE, 'Carga en vivo', 'Quién sube, lote actual, avance y tiempo estimado, cada 3 segundos.'],
            [RED, 'Una carga a la vez', 'Evita que dos cargas se pisen y deja la base consistente.'],
            [GREEN, 'Nunca queda trabado', 'Se libera solo a los 30 minutos, o al instante con Cancelar.'],
            [BLUE, 'Historial de cargas', 'Usuario, archivo, duración, filas y resultado de cada carga.'],
            [RED, 'Errores explicados', 'Último error con su lote y mensaje, para corregir rápido.'],
            [GREEN, 'Cada ruta, con responsable', 'Creación, edición o eliminación, con usuario, fecha y archivo.'],
            [BLUE, 'Filtros combinables', 'Se cruzan ruta, PDV, usuario, supervisor, estado y fechas.'],
            [GREEN, 'Integridad verificada', 'Alerta si el PDV, el usuario o el supervisor ya no existen.'],
        ];
        b.forEach(([c, t, d], i) => {
            const col = i % 2, row = Math.floor(i / 2);
            item(s, 5.0 + col * 4.1, 1.0 + row * 1.5, 3.8, c, t, d, false, { t: 14, d: 10.5, h: 0.8 });
        });
    }

    const out = path.resolve(__dirname, 'Colgate_Auditoria_Presentacion_Ejecutiva.pptx');
    await pres.writeFile({ fileName: out });
    console.log('PPTX generado: ' + out);
}
main().catch((e) => { console.error(e); process.exit(1); });
