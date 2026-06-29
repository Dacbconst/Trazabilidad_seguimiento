<?php
/**
 * Token PÚBLICO de Mapbox (empieza con "pk."), usado solo en el navegador
 * para buscar direcciones en el modal "Nueva visita" de Agendamientos.
 * Pégalo aquí en cuanto lo crees en https://account.mapbox.com/access-tokens/
 * — restríngelo por URL a este dominio, así no sirve si alguien lo copia del
 * código fuente de la página.
 *
 * Vive en su propio archivo (no en config.php) a propósito: config.php solo
 * se carga del lado de los getters (procesos PHP separados); este archivo
 * lo carga directo agendamientos.php dentro del mismo request que ya cargó
 * el config.php del hub (App/XploraEcuador) — si compartieran archivo,
 * HOST/USER/PASS/etc. chocarían por estar definidos dos veces.
 */
define('MAPBOX_TOKEN', 'pk.eyJ1IjoiZGFjYmNvbnN0IiwiYSI6ImNtcXptMWlzMDAzeHAycHByM29xcWc0YWEifQ.LZ7HzcJ671QG6QGzZKqrSw');
?>
