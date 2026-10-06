const pptxgen = require('pptxgenjs');
const path = require('path');
const fs = require('fs');

async function createExecutivePresentation() {
    const pres = new pptxgen();
    pres.layout = 'LAYOUT_WIDE';
    pres.author = 'Grupo Lucky & Epson';
    pres.company = 'Grupo Lucky';
    pres.title = 'EpsonReport - Presentación Ejecutiva';

    // Corporate Color Palette
    const C_PURPLE = '581C87';
    const C_PRIMARY = '4C1D95';
    const C_LIGHT_BG = 'FFFFFF';
    const C_TEXT_MAIN = '0F172A';
    const C_TEXT_MUTED = '475569';
    const C_BORDER = 'E2E8F0';
    const C_WHITE = 'FFFFFF';

    const imgLogin = path.resolve(__dirname, 'assets/img/presentacion/app_login.png');
    const imgForm = path.resolve(__dirname, 'assets/img/presentacion/app_formulario_estadisticas.png');
    const imgCal = path.resolve(__dirname, 'assets/img/presentacion/app_calendario.png');
    const imgLucky = path.resolve(__dirname, 'lucky_logo.png');

    // ==========================================
    // SLIDE 1: PORTADA
    // ==========================================
    {
        const s1 = pres.addSlide();
        s1.background = { color: 'FFFFFF' };

        s1.addText('PROYECTO 1', {
            x: 0.8, y: 0.8, w: 3.0, h: 0.3,
            fontSize: 10, bold: true, color: '7C3AED', fontFace: 'Calibri'
        });

        if (fs.existsSync(imgLucky)) {
            s1.addImage({ path: imgLucky, x: 10.5, y: 0.7, w: 1.8, h: 0.8 });
        }

        s1.addText('EpsonReport', {
            x: 0.8, y: 2.2, w: 11.5, h: 1.2,
            fontSize: 54, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        s1.addText('De las fotos sueltas al reporte oficial: promotores, supervisores y Epson en una sola plataforma, con la presentación lista en un clic.', {
            x: 0.8, y: 3.6, w: 10.5, h: 1.2,
            fontSize: 22, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        s1.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 5.6, w: 2.8, h: 0.4,
            rectRadius: 0.2, fill: { color: 'F5F3FF' }, line: { color: 'DDD6FE', width: 1 }
        });
        s1.addText('Optimizado para celulares', {
            x: 0.8, y: 5.6, w: 2.8, h: 0.4,
            fontSize: 10, bold: true, color: '6D28D9', align: 'center', valign: 'middle'
        });

        s1.addShape(pres.ShapeType.roundRect, {
            x: 3.8, y: 5.6, w: 2.0, h: 0.4,
            rectRadius: 0.2, fill: { color: 'ECFDF5' }, line: { color: 'A7F3D0', width: 1 }
        });
        s1.addText('Reportes en PowerPoint', {
            x: 3.8, y: 5.6, w: 2.0, h: 0.4,
            fontSize: 10, bold: true, color: '047857', align: 'center', valign: 'middle'
        });
    }

    // ==========================================
    // SLIDE 2: EL PROBLEMA
    // ==========================================
    {
        const s2 = pres.addSlide();
        s2.background = { color: 'FFFFFF' };

        s2.addText('EPSONREPORT  ·  CONTEXTO Y DESAFÍO', {
            x: 0.8, y: 0.6, w: 6.0, h: 0.3,
            fontSize: 9.5, bold: true, color: '7C3AED', fontFace: 'Calibri'
        });
        s2.addText('Cómo se reportaba el trabajo de campo antes', {
            x: 0.8, y: 0.95, w: 10.0, h: 0.6,
            fontSize: 24, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        const items = [
            { t: 'Fotos por Chat, sin Orden', d: 'Las fotos del día llegaban sueltas y había que ordenarlas por actividad, fecha y punto de venta antes de armar cada reporte.' },
            { t: 'Un Formato por Cada Reporte', d: 'Activaciones, capacitaciones, POP, ferias y exhibiciones tienen cada una su formato; son entre 12 y 20 reportes cada mes.' },
            { t: 'Presentaciones Armadas a Mano', d: 'Cada entrega a Epson exigía armar el PowerPoint con fotos, cifras y cumplimiento, diapositiva por diapositiva.' }
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

        // Right box
        s2.addShape(pres.ShapeType.roundRect, {
            x: 6.2, y: 1.8, w: 6.2, h: 4.6,
            rectRadius: 0.15, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
        });

        const boxItems = [
            { mark: '✕', color: 'DC2626', t: 'Cumplimiento Difícil de Comprobar', d: 'Era complejo verificar que cada actividad programada por el supervisor realmente se hizo.' },
            { mark: '✕', color: 'DC2626', t: 'Información Dispersa', d: 'Fotos, estadísticas y programación de actividades vivían en lugares distintos.' },
            { mark: '✓', color: '059669', t: 'Con EpsonReport', d: 'El promotor registra y sube sus fotos desde el celular, el supervisor aprueba y la presentación oficial de Epson se genera sola.' }
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
    // SLIDE 3: LOGIN (FOTO 1)
    // ==========================================
    {
        const s3 = pres.addSlide();
        s3.background = { color: 'FFFFFF' };

        s3.addText('EPSONREPORT  ·  ACCESO AL SISTEMA', {
            x: 0.8, y: 0.6, w: 6.0, h: 0.3,
            fontSize: 9.5, bold: true, color: '7C3AED', fontFace: 'Calibri'
        });
        s3.addText('Ingreso Seguro para el Equipo', {
            x: 0.8, y: 0.95, w: 10.0, h: 0.6,
            fontSize: 24, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        s3.addText('Acceso para Promotores y Supervisores', {
            x: 0.8, y: 2.0, w: 3.8, h: 0.35,
            fontSize: 13, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });
        s3.addText('Cada promotor y supervisor entra con su propio usuario y ve solo lo que le corresponde.', {
            x: 0.8, y: 2.35, w: 3.8, h: 0.8,
            fontSize: 10, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        s3.addText('Seguimiento de Actividades', {
            x: 0.8, y: 3.5, w: 3.8, h: 0.35,
            fontSize: 13, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });
        s3.addText('Cada registro queda con su responsable, su fecha y su punto de venta.', {
            x: 0.8, y: 3.85, w: 3.8, h: 0.8,
            fontSize: 10, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        if (fs.existsSync(imgLogin)) {
            s3.addImage({
                path: imgLogin,
                x: 4.8, y: 1.6, w: 7.7, h: 4.8,
                sizing: { type: 'contain' }
            });
        }
    }

    // ==========================================
    // SLIDE 4: FORMULARIO Y ESTADÍSTICAS (FOTO 2)
    // ==========================================
    {
        const s4 = pres.addSlide();
        s4.background = { color: 'FFFFFF' };

        s4.addText('EPSONREPORT  ·  USO EN TIENDA', {
            x: 0.8, y: 0.6, w: 6.0, h: 0.3,
            fontSize: 9.5, bold: true, color: '7C3AED', fontFace: 'Calibri'
        });
        s4.addText('Llenado Fácil y Datos en Vivo', {
            x: 0.8, y: 0.95, w: 10.0, h: 0.6,
            fontSize: 24, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        s4.addText('Carga Rápida desde la Tienda', {
            x: 0.8, y: 2.0, w: 3.8, h: 0.35,
            fontSize: 13, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });
        s4.addText('El promotor completa 4 pasos claros (Datos de la actividad, Cobertura, Embudo de clientes y Modelos), sube sus fotos y envía.', {
            x: 0.8, y: 2.35, w: 3.8, h: 0.8,
            fontSize: 10, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        s4.addText('Resultados al Instante', {
            x: 0.8, y: 3.5, w: 3.8, h: 0.35,
            fontSize: 13, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });
        s4.addText('Mientras llena, la pantalla calcula cobertura, interacciones, ventas por modelo e ingresos, sin usar Excel.', {
            x: 0.8, y: 3.85, w: 3.8, h: 1.0,
            fontSize: 10, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        if (fs.existsSync(imgForm)) {
            s4.addImage({
                path: imgForm,
                x: 4.8, y: 1.6, w: 7.7, h: 4.8,
                sizing: { type: 'contain' }
            });
        }
    }

    // ==========================================
    // SLIDE 5: CALENDARIO Y DESCARGA PPT (FOTO 3) - EXTRA LARGE
    // ==========================================
    {
        const s5 = pres.addSlide();
        s5.background = { color: 'FFFFFF' };

        s5.addText('EPSONREPORT  ·  SUPERVISIÓN Y REPORTES', {
            x: 0.8, y: 0.5, w: 6.0, h: 0.3,
            fontSize: 9.5, bold: true, color: '7C3AED', fontFace: 'Calibri'
        });
        s5.addText('Programación, Seguimiento y Descarga de PPT', {
            x: 0.8, y: 0.85, w: 10.0, h: 0.5,
            fontSize: 22, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });

        s5.addText('Control de Tiendas y Promotores', {
            x: 0.8, y: 1.8, w: 3.2, h: 0.3,
            fontSize: 12, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });
        s5.addText('El supervisor programa el calendario de activaciones por punto de venta y promotor, y ve cuántas se cumplieron y cuáles faltan.', {
            x: 0.8, y: 2.15, w: 3.2, h: 0.8,
            fontSize: 9.5, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        s5.addText('Descargar Presentación Lista', {
            x: 0.8, y: 3.3, w: 3.2, h: 0.3,
            fontSize: 12, bold: true, color: '059669', fontFace: 'Calibri'
        });
        s5.addText('Al cerrar el calendario, el sistema arma la presentación de PowerPoint con el formato oficial de Epson, con fotos y cifras.', {
            x: 0.8, y: 3.65, w: 3.2, h: 1.0,
            fontSize: 9.5, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        // Large image display
        if (fs.existsSync(imgCal)) {
            s5.addImage({
                path: imgCal,
                x: 4.3, y: 1.6, w: 8.4, h: 4.8,
                sizing: { type: 'contain' }
            });
        }
    }

    // ==========================================
    // SLIDE 6: BENEFICIOS Y RESULTADOS (3 CARDS)
    // ==========================================
    {
        const s6 = pres.addSlide();
        s6.background = { color: 'FFFFFF' };

        s6.addText('EPSONREPORT  ·  RESULTADOS Y BENEFICIOS', {
            x: 0.8, y: 0.6, w: 8.0, h: 0.3,
            fontSize: 9.5, bold: true, color: '7C3AED', fontFace: 'Calibri'
        });
        s6.addText('Menos Trabajo para Lucky, Reportes Listos para Epson', {
            x: 0.8, y: 0.95, w: 10.0, h: 0.6,
            fontSize: 26, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
        });
        s6.addText('EpsonReport reúne en un solo lugar el registro, la aprobación y la presentación de cada actividad de campo.', {
            x: 0.8, y: 1.55, w: 11.5, h: 0.5,
            fontSize: 12, color: C_TEXT_MUTED, fontFace: 'Calibri'
        });

        const kpis = [
            {
                stat: '8',
                statColor: '7C3AED',
                label: 'Tipos de Actividad',
                desc: 'Activaciones, Capacitaciones, Epson Day, Ferias, Exhibiciones, POP y Competencia, cada una con su formato.'
            },
            {
                stat: '1 Clic',
                statColor: '2563EB',
                label: 'Presentación en PowerPoint',
                desc: 'El archivo oficial se descarga armado con las fotos y los datos del reporte.'
            },
            {
                stat: 'En Vivo',
                statColor: '7C3AED',
                label: 'Seguimiento del Equipo',
                desc: 'Supervisores y administradores ven los avances y los registros nuevos sin recargar la página.'
            }
        ];

        kpis.forEach((k, i) => {
            const x = 0.8 + (i * 3.9);
            s6.addShape(pres.ShapeType.roundRect, {
                x: x, y: 2.3, w: 3.7, h: 2.5,
                rectRadius: 0.12, fill: { color: 'F8FAFC' }, line: { color: C_BORDER, width: 1 }
            });
            s6.addText(k.stat, {
                x: x + 0.3, y: 2.6, w: 3.1, h: 0.7,
                fontSize: 32, bold: true, color: k.statColor, fontFace: 'Calibri'
            });
            s6.addText(k.label, {
                x: x + 0.3, y: 3.35, w: 3.1, h: 0.35,
                fontSize: 13, bold: true, color: C_TEXT_MAIN, fontFace: 'Calibri'
            });
            s6.addText(k.desc, {
                x: x + 0.3, y: 3.75, w: 3.1, h: 0.8,
                fontSize: 10, color: C_TEXT_MUTED, fontFace: 'Calibri'
            });
        });

        // Bottom note
        s6.addShape(pres.ShapeType.roundRect, {
            x: 0.8, y: 5.3, w: 11.5, h: 0.8,
            rectRadius: 0.1, fill: { color: 'F5F3FF' }, line: { color: 'DDD6FE', width: 1 }
        });
        s6.addText('EpsonReport · Plataforma de Reportes de Campo', {
            x: 1.1, y: 5.4, w: 8.0, h: 0.3,
            fontSize: 11, bold: true, color: '4C1D95', fontFace: 'Calibri'
        });
        s6.addText('Desarrollado para facilitar el trabajo de Grupo Lucky y Epson Ecuador.', {
            x: 1.1, y: 5.7, w: 8.0, h: 0.3,
            fontSize: 9.5, color: '6D28D9', fontFace: 'Calibri'
        });
    }

    const outputPath = path.resolve(__dirname, 'EpsonReport_Presentacion_Ejecutiva.pptx');
    await pres.writeFile({ fileName: outputPath });
    console.log(`PPTX regenerado exitosamente en: ${outputPath}`);
}

createExecutivePresentation().catch(err => {
    console.error('Error generando PPTX:', err);
    process.exit(1);
});
