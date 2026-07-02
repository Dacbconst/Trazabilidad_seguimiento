<?php
// Delega al componente de dashboard de la cuenta activa.
if (isset($cuenta_dir) && file_exists($cuenta_dir . '/components/principal/principal.php')) {
    include $cuenta_dir . '/components/principal/principal.php';
}
?>
