<?php
/**
 * COMPONENTE: multiphoto-card.php
 * Card con galería de múltiples fotos: thumbnails + visor + zoom + selección por foto.
 *
 * Variables requeridas (deben estar en scope al hacer include):
 *   $card_id          string  — ID único de la card  (ej: "exh_0")
 *   $fotos_json       string  — JSON array de URLs de fotos
 *   $tipos_limpio     string  — pipe-separated tipos de exhibición
 *   $categorias_limpio string — pipe-separated categorías por foto
 *   $subcats_limpio   string  — pipe-separated subcategorías por foto
 *   $pos_name         string  — nombre del local
 *   $city             string  — ciudad
 *   $first_foto       string  — URL de la primera foto (para el checkbox value)
 *   $fecha            string  — fecha de la visita
 *   $hora             string  — hora de la visita
 *   $mercaderista_r   string  — nombre del mercaderista
 *   $cat_inicial      string  — categoría de la primera foto
 *   $scat_inicial     string  — subcategoría de la primera foto
 *   $tipo_exh         string  — tipo de exhibición
 *   $address          string  — dirección del local
 *   $td_width         string  — ancho de la celda (ej: "33.33%")
 */
?>
<td style="padding:1.2%; vertical-align:top; width:<?= $td_width ?>;">
<div class="card exh-card" id="<?= $card_id ?>"
     data-images='<?= $fotos_json ?>'
     data-tipos="<?= htmlspecialchars($tipos_limpio) ?>"
     data-categorias="<?= htmlspecialchars($categorias_limpio) ?>"
     data-subcategorias="<?= htmlspecialchars($subcats_limpio) ?>"
     data-local="<?= htmlspecialchars($codigo) ?>_<?= htmlspecialchars($mercaderista_r) ?>_<?= htmlspecialchars($fecha) ?>"
     style="border-radius:8px; overflow:hidden; box-shadow:0 2px 8px rgba(0,0,0,.12);">

  <div class="exh-card-header"
       style="background:#f0f0f0; padding:10px 12px; border-bottom:3px solid #337ab7;
              display:flex; align-items:flex-start; justify-content:space-between; gap:8px;
              transition:background .25s ease;">
    <div style="overflow:hidden;">
      <strong style="font-size:14px; display:block; color:#222; white-space:nowrap;
                     overflow:hidden; text-overflow:ellipsis; transition:color .25s;">
        <?= htmlspecialchars($pos_name) ?>
      </strong>
      <span style="font-size:11px; color:#666; transition:color .25s;">
        <?= htmlspecialchars($city) ?>
      </span>
    </div>
    <input type="checkbox" name="cb"
           class="exh-main-cb sub-col-7 dont-get-data"
           value="<?= $first_foto ?>" />
  </div>

  <div style="padding:8px; background:#fff;">
    <div style="display:grid; grid-template-columns:80px 1fr; gap:8px; height:260px;">

      <!-- Miniaturas verticales — JS las rellena en exhInitAll() -->
      <div id="thumbs_<?= $card_id ?>"
           style="display:flex; flex-direction:column; gap:4px;
                  overflow-y:auto; scrollbar-width:none;">
      </div>

      <!-- Visor principal -->
      <div style="position:relative; border:2px solid #ddd; border-radius:4px;
                  background:#111; display:flex; align-items:center;
                  justify-content:center; overflow:hidden;">

        <button type="button" id="btn_prev_<?= $card_id ?>"
                onclick="exhNav('<?= $card_id ?>',-1)"
                style="position:absolute;left:0;top:50%;transform:translateY(-50%);
                       background:rgba(255,255,255,.85);border:none;border-radius:0 4px 4px 0;
                       width:26px;height:40px;cursor:pointer;z-index:2;font-size:14px;
                       display:flex;align-items:center;justify-content:center;">&#9664;</button>

        <img id="main_<?= $card_id ?>" src="" alt=""
             onclick="exhZoom('<?= $card_id ?>')"
             style="width:100%;height:100%;object-fit:contain;cursor:zoom-in;transition:opacity .25s;" />

        <!-- Botón zoom flotante -->
        <button type="button" onclick="exhZoom('<?= $card_id ?>')" title="Zoom"
                style="position:absolute;bottom:6px;right:6px;background:rgba(0,0,0,0.45);
                       border:none;border-radius:4px;color:#fff;font-size:13px;
                       width:28px;height:28px;cursor:zoom-in;z-index:4;
                       display:flex;align-items:center;justify-content:center;
                       transition:background .15s;"
                onmouseover="this.style.background='rgba(51,122,183,0.8)'"
                onmouseout="this.style.background='rgba(0,0,0,0.45)'">&#128269;</button>

        <button type="button" id="btn_next_<?= $card_id ?>"
                onclick="exhNav('<?= $card_id ?>',1)"
                style="position:absolute;right:0;top:50%;transform:translateY(-50%);
                       background:rgba(255,255,255,.85);border:none;border-radius:4px 0 0 4px;
                       width:26px;height:40px;cursor:pointer;z-index:2;font-size:14px;
                       display:flex;align-items:center;justify-content:center;">&#9654;</button>

        <!-- Contador de foto actual -->
        <span id="counter_<?= $card_id ?>"
              style="position:absolute;top:6px;right:6px;background:rgba(0,0,0,0.55);
                     color:#fff;font-size:11px;font-weight:600;padding:2px 7px;
                     border-radius:10px;z-index:3;letter-spacing:.5px;"></span>

        <!-- Badge "Última foto" -->
        <span id="limit_<?= $card_id ?>"
              style="position:absolute;bottom:6px;left:50%;transform:translateX(-50%);
                     background:rgba(106,13,173,0.85);color:#fff;font-size:10px;
                     padding:2px 8px;border-radius:8px;z-index:3;display:none;
                     white-space:nowrap;">Última foto</span>
      </div>

    </div>
  </div>

  <!-- Footer visible -->
  <div style="background:#fafafa; padding:8px 12px; border-top:1px solid #eee;
              font-size:11px; color:#555; line-height:1.7;">
    <div><b style="color:#333;">Fecha:</b> <?= $fecha ?> &nbsp; <?= $hora ?></div>
    <div><b style="color:#333;">Gestor:</b> <?= htmlspecialchars($mercaderista_r) ?></div>
    <div id="cat_display_<?= $card_id ?>">
      <b style="color:#333;">Categoría:</b>
      <?= htmlspecialchars($cat_inicial) ?>
      <span style="color:#999;"> / </span>
      <?= htmlspecialchars($scat_inicial) ?>
    </div>
  </div>

  <!-- Campos ocultos para el PPT -->
  <p name="fecha"     hidden><b>Fecha:</b> <?= $fecha ?> <?= $hora ?></p>
  <p name="user"      hidden><b>Mercaderista:</b> <?= htmlspecialchars($mercaderista_r) ?></p>
  <p name="ciudad"    hidden><?= htmlspecialchars($city) ?></p>
  <p name="local"     hidden><?= htmlspecialchars($pos_name) ?></p>
  <p name="direccion" hidden><?= htmlspecialchars($address) ?></p>
  <p name="tipo"      hidden><?= htmlspecialchars($tipo_exh) ?></p>
  <p name="status"    hidden><b>Status:</b></p>
  <p name="comentario" hidden><b>Comentario:</b></p>

</div>
</td>
