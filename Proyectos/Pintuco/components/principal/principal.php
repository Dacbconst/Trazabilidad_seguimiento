<?php
/**
 * COMPONENTE: principal — Vista de avance de promotores (mock).
 * Usa $promotores, $resumen y $meses de mock_data.php.
 * Nota: datos estáticos hasta integrar get_avance al flujo real.
 */
$principal_dir    = __DIR__;
$principal_assets = basename((string) $cuenta_dir) . '/components/principal/assets';
$principal_css_v  = @filemtime($principal_dir . '/assets/principal.css') ?: time();
?>
<link rel="stylesheet" href="<?= htmlspecialchars($principal_assets, ENT_QUOTES) ?>/principal.css?v=<?= $principal_css_v ?>">

<div id="principalApp">

    <div class="principal-header">
        <div>
            <h2>Avance de Promotores</h2>
            <p>Resumen de estado y comisiones por mercaderista</p>
        </div>
        <div>
            <select class="form-control" id="principalMes" style="min-width:160px;">
                <?php foreach ($meses as $clave => $etiqueta): ?>
                <option value="<?= htmlspecialchars($clave) ?>" <?= $clave === $mes_actual ? 'selected' : '' ?>>
                    <?= htmlspecialchars($etiqueta) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- KPI cards -->
    <div class="principal-kpis">
        <div class="principal-kpi">
            <span class="principal-kpi-label">Total promotores</span>
            <span class="principal-kpi-valor"><?= (int)$resumen['total'] ?></span>
        </div>
        <div class="principal-kpi">
            <span class="principal-kpi-label">Realizados</span>
            <span class="principal-kpi-valor is-verde"><?= (int)$resumen['realizados'] ?></span>
        </div>
        <div class="principal-kpi">
            <span class="principal-kpi-label">Sin realizar</span>
            <span class="principal-kpi-valor is-ambar"><?= (int)$resumen['sin_realizar'] ?></span>
        </div>
        <div class="principal-kpi">
            <span class="principal-kpi-label">Comisionan</span>
            <span class="principal-kpi-valor is-azul"><?= (int)$resumen['comisionan'] ?></span>
        </div>
        <div class="principal-kpi">
            <span class="principal-kpi-label">No comisionan</span>
            <span class="principal-kpi-valor is-rojo"><?= (int)$resumen['no_comisionan'] ?></span>
        </div>
    </div>

    <!-- Barra de avance global -->
    <div class="principal-progreso">
        <span class="principal-progreso-label">Avance global</span>
        <div class="principal-progreso-track">
            <div class="principal-progreso-fill" style="width:<?= min(100, max(0, (int)$resumen['progreso'])) ?>%"></div>
        </div>
        <span class="principal-progreso-pct"><?= (int)$resumen['progreso'] ?>%</span>
    </div>

    <!-- Tabla de promotores -->
    <div class="principal-scroll">
        <table class="principal-table">
            <thead>
                <tr>
                    <th>Promotor</th>
                    <th>PDV</th>
                    <th>Ciudad</th>
                    <th>Estado</th>
                    <th>Comisiona</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($promotores)): ?>
                <tr><td colspan="5" style="text-align:center;color:#aab3c2;padding:30px;">Sin datos disponibles.</td></tr>
                <?php else: ?>
                <?php foreach ($promotores as $p): ?>
                <?php
                    $estadoLabel = ['ejecutando' => 'Ejecutando', 'cerrados' => 'Realizado', 'pendiente' => 'Pendiente'][$p['estado']] ?? $p['estado'];
                    $estadoClase = ['ejecutando' => 'is-ejecutando', 'cerrados' => 'is-cerrado', 'pendiente' => 'is-pendiente'][$p['estado']] ?? '';
                ?>
                <tr>
                    <td><?= htmlspecialchars($p['nombre']) ?></td>
                    <td><?= htmlspecialchars($p['pdv']) ?></td>
                    <td><?= htmlspecialchars($p['ciudad']) ?></td>
                    <td><span class="principal-badge <?= $estadoClase ?>"><?= htmlspecialchars($estadoLabel) ?></span></td>
                    <td>
                        <?php if ($p['comisiona'] === 'si'): ?>
                            <span class="principal-badge is-comisiona">Comisiona</span>
                        <?php elseif ($p['comisiona'] === 'no'): ?>
                            <span class="principal-badge is-no-comisiona">No comisiona</span>
                        <?php else: ?>
                            <span style="color:#aab3c2">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
