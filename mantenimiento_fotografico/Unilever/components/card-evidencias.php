<?php
/**
 * COMPONENTE: card-evidencias.php
 * Card con dos fotos: Antes y Después (vi_evidencias y reportes similares).
 *
 * Variables requeridas (deben estar en scope al hacer include):
 *   $id           int|string — ID del registro
 *   $fecha        string     — fecha
 *   $hora         string     — hora
 *   $merc         string     — nombre del mercaderista
 *   $city         string     — ciudad
 *   $pos_name     string     — nombre del local
 *   $address      string     — dirección
 *   $srcAntes     string     — URL foto antes
 *   $srcDespues   string     — URL foto después
 *   $status       string     — comentario/status
 *   $tipo         string     — tipo de registro
 */
?>
<td id="<?= $id ?>" name="<?= $id ?>" class="text-center" style="padding:1.2%; vertical-align:top; width:33.33%;">
<div class="card evid-card" style="border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.12);">

  <div class="evid-card-header"
       style="background:#f0f0f0; padding:10px 12px; border-bottom:3px solid #337ab7;
              display:flex; align-items:flex-start; justify-content:space-between; gap:8px;">
    <div style="overflow:hidden;">
      <strong style="font-size:13px; display:block; color:#222; white-space:nowrap;
                     overflow:hidden; text-overflow:ellipsis;">
        <?= htmlspecialchars($pos_name) ?>
      </strong>
      <span style="font-size:11px; color:#666;"><?= htmlspecialchars($city) ?></span>
    </div>
    <input name="cb" type="checkbox"
           class="evid-main-cb sub-col-7 dont-get-data"
           value="<?= $srcAntes ?>" />
  </div>

  <div style="padding:8px; background:#fff;">
    <div style="display:flex; gap:6px;">

      <div class="foto_antes" style="flex:1; text-align:center;">
        <p style="font-size:11px; color:#888; margin:0 0 4px;"><b>Antes</b></p>
        <img name="foto_antes"
             data-src="<?= $srcAntes ?>"
             src=""
             style="width:100%; height:170px; object-fit:cover; border-radius:4px; background:#e9ecef;"
             class="img-responsive lazy-img" />
      </div>

      <div class="foto_despues" style="flex:1; text-align:center;">
        <p style="font-size:11px; color:#888; margin:0 0 4px;"><b>Después</b></p>
        <img name="foto_despues"
             data-src="<?= $srcDespues ?>"
             src=""
             style="width:100%; height:170px; object-fit:cover; border-radius:4px; background:#e9ecef;"
             class="img-responsive lazy-img" />
      </div>

    </div>
  </div>

  <!-- Footer visible -->
  <div style="background:#fafafa; padding:8px 12px; border-top:1px solid #eee;
              font-size:11px; color:#555; line-height:1.7;">
    <div><b style="color:#333;">Fecha:</b> <?= $fecha ?> <?= $hora ?></div>
    <div><b style="color:#333;">Mercaderista:</b> <?= htmlspecialchars($merc) ?></div>
  </div>

  <!-- Campos ocultos para PPT -->
  <p name="fecha"      hidden>Fecha: <?= $fecha ?> <?= $hora ?></p>
  <p name="user"       hidden>Mercaderista: <?= htmlspecialchars($merc) ?></p>
  <p name="ciudad"     hidden><?= htmlspecialchars($city) ?></p>
  <p name="local"      hidden><?= htmlspecialchars($pos_name) ?></p>
  <p name="direccion"  hidden><?= htmlspecialchars($address) ?></p>
  <p name="tipo"       hidden><?= htmlspecialchars($tipo) ?></p>
  <p name="status"     hidden>Status:<?= htmlspecialchars($status) ?></p>
  <p name="comentario" hidden>Comentario:<?= htmlspecialchars($status) ?></p>
</div>
</td>
