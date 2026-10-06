const pptxgen = require('pptxgenjs');
const path = require('path');
const fs = require('fs');

async function createAcuerdosPresentation() {
    const pres = new pptxgen();
    pres.layout = 'LAYOUT_WIDE';
    pres.author = 'Grupo Lucky & Alicorp';
    pres.company = 'Grupo Lucky';
    pres.title = 'Acuerdos Comerciales (ADN) - Presentación Ejecutiva';

    // Corporate Color Palette
    const C_RED = 'E11931';
    const C_BLUE = '002F87';
    const C_TEXT_MAIN = '0F172A';
    const C_TEXT_MUTED = '475569';
    const C_BORDER = 'E2E8F0';

    const baseImgDir = path.resolve(__dirname, '../Acuerdos_Comerciales/assets/img/presentacion');
    const imgLogin = path.join(baseImgDir, 'app_login.png');
    const imgRegistro = path.join(baseImgDir, 'app_registro_acuerdo.png');
    const imgHistorial = path.join(baseImgDir, 'app_historial_acuerdos.png');
    const imgEquipo = path.join(baseImgDir, 'app_seguimiento_equipo.png');
    const imgResumen = path.join(baseImgDir, 'app_resumen_negociacion.png');
    const imgCuota = path.join(baseImgDir, 'app_cumplimiento_cuota.png');
    const imgRepo = path.join(baseImgDir, 'app_repositorios.png');
    const imgAlicorp = path.join(baseImgDir, 'logo_alicorp.png');
    const imgLucky = path.resolve(__dirname, '../Acuerdos_Comerciales/lucky_logo.png');

    // ==========================================
    // SLIDE 1: PORTADA
    // ==========================================
    {
        const s1 = pres.addSlide();
        s1.background = { color: 'FFFFFF' };

        if (fs.existsSync(imgAlicorp)) {
            s1.addImage({ path: imgAlicorp, x: 0.8, y: 0.65, w: 1.8, h: 0.6 });
        }
        s1.addText('PROYECTO 2 · ADN ALICORP', {
            x: 2.8, y: 0.8, w: 4.0, h: 0.3,
            fontSize: 10, bold: true, color: C_RED, fontFace: 'Calibri'
        });

        s1.addText('Jabonería Wilson / Alicorp Ecuador', {
            x: 7.0, y: 0.8, w: 3.8, h: 0.3,
            fontSize: 9.5, color: C_TEXT_MUTED, align: 'right', fontFace: 'Calibri'
        });
        if (fs.existsSync(imgLucky)) {
            s1.addImage({ path: imgLucky, x: 10.8, y: 0.65, w: 1.7, h: 0.65 });
        }

        s1.addText('Acuerdos Comerciales', {
            x: 0.8, y: 2.0, w: 11.5, h: 1.2,
            fontSize: 52, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        s1.addText('Control total del Acta de Compromiso: se crea, se firma y se sigue en un solo lugar, con visibilidad para todo el equipo comercial.', {
            x: 0.8, y: 3.3, w: 10.5, h: 1.2,
            fontSize: 20, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        s1.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 5.3, w: 3.0, h: 0.4,
            rectRadius: 0.2, fill: { color: 'FFF1F2' }, line: { color: 'FECDD3', width: 1 }
        });
        s1.addText('Canal Directo y Distribuidores', {
            x: 0.8, y: 5.3, w: 3.0, h: 0.4,
            fontSize: 10, bold: true, color: C_RED, align: 'center', valign: 'middle'
        });

        s1.addShape(pres.ShapeType.roundRect, {
            x: 4.0, y: 5.3, w: 2.8, h: 0.4,
            rectRadius: 0.2, fill: { color: 'F5F3FF' }, line: { color: 'DDD6FE', width: 1 }
        });
        s1.addText('Seguimiento del Equipo', {
            x: 4.0, y: 5.3, w: 2.8, h: 0.4,
            fontSize: 10, bold: true, color: '6D28D9', align: 'center', valign: 'middle'
        });

        s1.addShape(pres.ShapeType.roundRect, {
            x: 7.0, y: 5.3, w: 2.2, h: 0.4,
            rectRadius: 0.2, fill: { color: 'ECFDF5' }, line: { color: 'A7F3D0', width: 1 }
        });
        s1.addText('Firma Física, Control Digital', {
            x: 7.0, y: 5.3, w: 2.2, h: 0.4,
            fontSize: 10, bold: true, color: '047857', align: 'center', valign: 'middle'
        });
    }

    // ==========================================
    // SLIDE 2: EL PROBLEMA
    // ==========================================
    {
        const s2 = pres.addSlide();
        s2.background = { color: 'FFFFFF' };

        s2.addText('ACUERDOS COMERCIALES  ·  CONTEXTO Y DESAFÍO OPERATIVO', {
            x: 0.8, y: 0.6, w: 8.0, h: 0.3,
            fontSize: 9.5, bold: true, color: C_RED, fontFace: 'Calibri'
        });
        s2.addText('Así funcionaba el proceso de acuerdos antes', {
            x: 0.8, y: 0.95, w: 10.0, h: 0.6,
            fontSize: 24, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        const items = [
            { t: 'Redactar Cada Acuerdo Tomaba Tiempo', d: 'Preparar cada acuerdo comercial consumía bastante tiempo, tanto para Directo como para Distribuidores.' },
            { t: 'Cuotas y Rebate Llenados en Cada Acta', d: 'El asesor o el jefe de agencia tenía que llenar las cuotas, el rebate y la participación en cada acuerdo.' },
            { t: 'Un Excel para Cada Cierre', d: 'Las cuotas del acuerdo estaban en un Excel y la venta real se cargaba desde los cubos para ver el cumplimiento.' }
        ];

        items.forEach((it, idx) => {
            const y = 1.9 + (idx * 1.5);
            s2.addText(it.t, {
                x: 0.8, y: y, w: 4.8, h: 0.35,
                fontSize: 13, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
            });
            s2.addText(it.d, {
                x: 0.8, y: y + 0.35, w: 4.8, h: 0.8,
                fontSize: 10, color: C_TEXT_MUTED, fontFace: 'Calibri'
            });
        });

        s2.addShape(pres.ShapeType.roundRect, {
            x: 6.2, y: 1.8, w: 6.2, h: 4.6,
            rectRadius: 0.15, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
        });

        const boxItems = [
            { mark: '✕', color: 'DC2626', t: 'Firmas sin Dónde Guardarse', d: 'El Acta se compartía por correo o WhatsApp y no había un lugar donde el asesor subiera la versión firmada.' },
            { mark: '✕', color: 'DC2626', t: 'Seguimiento Pasando Archivos', d: 'Ventas y cartera se completaban en el Excel y el archivo se pasaba a otra persona para dar seguimiento a la visibilidad.' },
            { mark: '✓', color: '059669', t: 'Con el Sistema ADN', d: 'Un solo flujo: las cuotas llegan cargadas, el Acta sale lista para firmar y el seguimiento se arma solo.' }
        ];

        boxItems.forEach((bi, i) => {
            const by = 2.2 + (i * 1.3);
            s2.addText(bi.mark, {
                x: 6.6, y: by, w: 0.5, h: 0.5,
                fontSize: 18, bold: true, color: bi.color
            });
            s2.addText(bi.t, {
                x: 7.2, y: by, w: 4.8, h: 0.3,
                fontSize: 12, bold: true, color: C_TEXT_MAIN
            });
            s2.addText(bi.d, {
                x: 7.2, y: by + 0.32, w: 4.8, h: 0.6,
                fontSize: 9.5, color: C_TEXT_MUTED
            });
        });
    }

    // ==========================================
    // SLIDE 3: FOTOS 1 Y 2 (LOGIN + REGISTRAR ACUERDO)
    // ==========================================
    {
        const s3 = pres.addSlide();
        s3.background = { color: 'FFFFFF' };

        s3.addText('ACUERDOS COMERCIALES  ·  INGRESO Y CREACIÓN DE ACUERDOS (FOTOS 1 Y 2)', {
            x: 0.8, y: 0.5, w: 8.5, h: 0.25,
            fontSize: 9.5, bold: true, color: C_RED, fontFace: 'Calibri'
        });

        // Columna Izquierda (30%) - Comentarios
        s3.addText('Ingreso y Registro de Metas', {
            x: 0.8, y: 0.85, w: 4.0, h: 0.5,
            fontSize: 22, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        // Card 1
        s3.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 1.5, w: 4.0, h: 2.2,
            rectRadius: 0.12, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
        });
        s3.addText('Foto 1 · Acceso Seguro', {
            x: 1.0, y: 1.65, w: 3.6, h: 0.3,
            fontSize: 11, bold: true, color: C_BLUE
        });
        s3.addText('Panel de Gestión Comercial', {
            x: 1.0, y: 1.95, w: 3.6, h: 0.3,
            fontSize: 12, bold: true, color: C_TEXT_MAIN
        });
        s3.addText('Cada asesor y supervisor entra con su cuenta personal para ver solo sus clientes y tiendas asignadas.', {
            x: 1.0, y: 2.3, w: 3.6, h: 1.1,
            fontSize: 10, color: C_TEXT_MUTED
        });

        // Card 2
        s3.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 3.9, w: 4.0, h: 2.5,
            rectRadius: 0.12, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
        });
        s3.addText('Foto 2 · Registro Guiado', {
            x: 1.0, y: 4.05, w: 3.6, h: 0.3,
            fontSize: 11, bold: true, color: C_RED
        });
        s3.addText('Metas y Espacios en Tienda', {
            x: 1.0, y: 4.35, w: 3.6, h: 0.3,
            fontSize: 12, bold: true, color: C_TEXT_MAIN
        });
        s3.addText('Selección de cliente, trimestre (Q1 a Q4) y metas de compra por categoría. El % de rebate aparece bloqueado automáticamente para evitar errores.', {
            x: 1.0, y: 4.7, w: 3.6, h: 1.4,
            fontSize: 10, color: C_TEXT_MUTED
        });

        // Columna Derecha (70%) - Fotos ampliadas manteniendo posición
        if (fs.existsSync(imgLogin)) {
            s3.addImage({ path: imgLogin, x: 5.0, y: 1.15, w: 7.6, h: 2.8, sizing: { type: 'contain' } });
        }
        if (fs.existsSync(imgRegistro)) {
            s3.addImage({ path: imgRegistro, x: 5.0, y: 4.05, w: 7.6, h: 2.9, sizing: { type: 'contain' } });
        }
    }

    // ==========================================
    // SLIDE 4: FOTOS 3 Y 4 (HISTORIAL + SEGUIMIENTO DE EQUIPO)
    // ==========================================
    {
        const s4 = pres.addSlide();
        s4.background = { color: 'FFFFFF' };

        s4.addText('ACUERDOS COMERCIALES  ·  CONTROL DE FIRMAS Y EQUIPO (FOTOS 3 Y 4)', {
            x: 0.8, y: 0.5, w: 8.5, h: 0.25,
            fontSize: 9.5, bold: true, color: C_RED, fontFace: 'Calibri'
        });

        s4.addText('Control de Firmas y Asesores', {
            x: 0.8, y: 0.85, w: 4.0, h: 0.5,
            fontSize: 22, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        // Card 1
        s4.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 1.5, w: 4.0, h: 2.2,
            rectRadius: 0.12, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
        });
        s4.addText('Foto 3 · Historial Oficial', {
            x: 1.0, y: 1.65, w: 3.6, h: 0.3,
            fontSize: 11, bold: true, color: 'B45309'
        });
        s4.addText('Código Único y Subida de Firma', {
            x: 1.0, y: 1.95, w: 3.6, h: 0.3,
            fontSize: 12, bold: true, color: C_TEXT_MAIN
        });
        s4.addText('Cada acuerdo genera su código (#ADN-2026-0001). Muestra si la firma está pendiente o firmada, con botón para subir la foto del documento.', {
            x: 1.0, y: 2.3, w: 3.6, h: 1.1,
            fontSize: 10, color: C_TEXT_MUTED
        });

        // Card 2
        s4.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 3.9, w: 4.0, h: 2.5,
            rectRadius: 0.12, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
        });
        s4.addText('Foto 4 · Tablero de Equipo', {
            x: 1.0, y: 4.05, w: 3.6, h: 0.3,
            fontSize: 11, bold: true, color: C_BLUE
        });
        s4.addText('Avance por Miembro Comercial', {
            x: 1.0, y: 4.35, w: 3.6, h: 0.3,
            fontSize: 12, bold: true, color: C_TEXT_MAIN
        });
        s4.addText('El supervisor ve en una sola pantalla cuántas actas ha creado cada asesor, cuántas ya están firmadas y cuántas faltan por gestionar.', {
            x: 1.0, y: 4.7, w: 3.6, h: 1.4,
            fontSize: 10, color: C_TEXT_MUTED
        });

        // Columna Derecha (70%) - Fotos ampliadas manteniendo posición
        if (fs.existsSync(imgHistorial)) {
            s4.addImage({ path: imgHistorial, x: 5.0, y: 1.15, w: 7.6, h: 2.8, sizing: { type: 'contain' } });
        }
        if (fs.existsSync(imgEquipo)) {
            s4.addImage({ path: imgEquipo, x: 5.0, y: 4.05, w: 7.6, h: 2.9, sizing: { type: 'contain' } });
        }
    }

    // ==========================================
    // SLIDE 5: FOTOS 5 Y 6 (RESUMEN + CUMPLIMIENTO DE CUOTA)
    // ==========================================
    {
        const s5 = pres.addSlide();
        s5.background = { color: 'FFFFFF' };

        s5.addText('ACUERDOS COMERCIALES  ·  ESPACIOS Y EVALUACIÓN DE CUOTAS (FOTOS 5 Y 6)', {
            x: 0.8, y: 0.5, w: 8.5, h: 0.25,
            fontSize: 9.5, bold: true, color: C_RED, fontFace: 'Calibri'
        });

        s5.addText('Espacios y Quién Gana la Cuota', {
            x: 0.8, y: 0.85, w: 4.0, h: 0.5,
            fontSize: 22, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        // Card 1
        s5.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 1.5, w: 4.0, h: 2.2,
            rectRadius: 0.12, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
        });
        s5.addText('Foto 5 · Resumen Negociado', {
            x: 1.0, y: 1.65, w: 3.6, h: 0.3,
            fontSize: 11, bold: true, color: '6D28D9'
        });
        s5.addText('Control de Espacios en Tienda', {
            x: 1.0, y: 1.95, w: 3.6, h: 0.3,
            fontSize: 12, bold: true, color: C_TEXT_MAIN
        });
        s5.addText('Resumen de cabeceras, rumas y perchas negociadas por asesor, con el valor estimado de ganancia para cada categoría (Crema, Líquido).', {
            x: 1.0, y: 2.3, w: 3.6, h: 1.1,
            fontSize: 10, color: C_TEXT_MUTED
        });

        // Card 2
        s5.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 3.9, w: 4.0, h: 2.5,
            rectRadius: 0.12, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
        });
        s5.addText('Foto 6 · Evaluación de Cuota', {
            x: 1.0, y: 4.05, w: 3.6, h: 0.3,
            fontSize: 11, bold: true, color: '059669'
        });
        s5.addText('Cálculo Automático: Gana o No Gana', {
            x: 1.0, y: 4.35, w: 3.6, h: 0.3,
            fontSize: 12, bold: true, color: C_TEXT_MAIN
        });
        s5.addText('Se sube el archivo de ventas reales del trimestre y el sistema calcula de inmediato: marca con verde GANA o con rojo NO GANA según el cumplimiento.', {
            x: 1.0, y: 4.7, w: 3.6, h: 1.4,
            fontSize: 10, color: C_TEXT_MUTED
        });

        // Columna Derecha (70%) - Fotos ampliadas manteniendo posición
        if (fs.existsSync(imgResumen)) {
            s5.addImage({ path: imgResumen, x: 5.0, y: 1.15, w: 7.6, h: 2.8, sizing: { type: 'contain' } });
        }
        if (fs.existsSync(imgCuota)) {
            s5.addImage({ path: imgCuota, x: 5.0, y: 4.05, w: 7.6, h: 2.9, sizing: { type: 'contain' } });
        }
    }

    // ==========================================
    // SLIDE 6: FOTO 7 (REPOSITORIOS) + BENEFICIOS
    // ==========================================
    {
        const s6 = pres.addSlide();
        s6.background = { color: 'FFFFFF' };

        s6.addText('ACUERDOS COMERCIALES  ·  CATÁLOGO MAESTRO Y BENEFICIOS', {
            x: 0.8, y: 0.4, w: 8.0, h: 0.25,
            fontSize: 9.5, bold: true, color: C_RED, fontFace: 'Calibri'
        });
        s6.addText('Control del Acta de Compromiso de Principio a Fin', {
            x: 0.8, y: 0.68, w: 10.0, h: 0.4,
            fontSize: 20, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        // Left Foto 7 (Grande)
        s6.addText('Foto 7 · Repositorios (Catálogo Oficial de Rebates)', {
            x: 0.8, y: 1.3, w: 6.2, h: 0.3, fontSize: 11, bold: true, color: C_BLUE
        });
        if (fs.existsSync(imgRepo)) {
            s6.addImage({ path: imgRepo, x: 0.8, y: 1.6, w: 6.2, h: 5.1, sizing: { type: 'contain' } });
        }

        // Right Beneficios
        const benefits = [
            { v: 'CONTROL', c: C_RED, t: 'Firmas siempre a la vista', d: 'Estado de cada Acta a la vista: firmada, pendiente o vencida, con foto de respaldo.' },
            { v: 'RAPIDEZ', c: C_BLUE, t: 'Actas listas para firmar', d: 'El Acta sale en PDF directo desde el sistema, sin armar documentos a mano.' },
            { v: 'EQUIPO', c: '059669', t: 'Seguimiento de cada asesor', d: 'El supervisor ve cuántas Actas generó cada asesor y cuáles faltan por firmar.' },
            { v: 'AVISOS', c: C_RED, t: 'Nada se queda sin firmar', d: 'El sistema avisa al asesor antes de que venza el plazo de la firma.' },
            { v: 'AHORRO', c: C_BLUE, t: 'Menos digitación', d: 'Las cuotas del trimestre llegan cargadas al asesor; solo completa lo que falta.' },
            { v: 'CUMPLIMIENTO', c: '059669', t: 'Cumplimiento sin cruzar Excel', d: 'Al cargar la venta real se calcula quién cumple su meta.' },
            { v: 'LIQUIDACIÓN', c: C_RED, t: 'Pagos con respaldo', d: 'Excel con metas y resultados por cliente, listo para revisar y liquidar.' },
            { v: '2 CANALES', c: C_BLUE, t: 'Directo y Distribuidor', d: 'Dólares y cajas en el mismo sistema, cada uno con su Acta y su Excel.' }
        ];

        benefits.forEach((b, i) => {
            const bx = 7.3 + (i % 2) * 2.7;
            const by = 1.25 + Math.floor(i / 2) * 1.45;
            s6.addShape(pres.ShapeType.roundRect, {
                x: bx, y: by, w: 2.5, h: 1.35,
                rectRadius: 0.12, fill: { color: 'FFFFFF' }, line: { color: C_BORDER, width: 1 }
            });
            s6.addText(b.v, { x: bx + 0.15, y: by + 0.1, w: 2.2, h: 0.3, fontSize: 11, bold: true, charSpacing: 1, color: b.c, valign: 'middle' });
            s6.addText(b.t, { x: bx + 0.15, y: by + 0.45, w: 2.2, h: 0.3, fontSize: 11.5, bold: true, color: C_TEXT_MAIN, valign: 'top' });
            s6.addText(b.d, { x: bx + 0.15, y: by + 0.78, w: 2.2, h: 0.52, fontSize: 8.5, color: C_TEXT_MUTED, valign: 'top' });
        });
    }

    const outEpson = path.resolve(__dirname, 'Acuerdos_Comerciales_Presentacion_Ejecutiva.pptx');
    const outAcuerdos = path.resolve(__dirname, '../Acuerdos_Comerciales/Acuerdos_Comerciales_Presentacion_Ejecutiva.pptx');

    await pres.writeFile({ fileName: outEpson });
    console.log(`PPTX generado exitosamente en: ${outEpson}`);
    fs.copyFileSync(outEpson, outAcuerdos);
    console.log(`PPTX copiado exitosamente a: ${outAcuerdos}`);
}

createAcuerdosPresentation().catch(err => {
    console.error('Error generando PPTX:', err);
});
