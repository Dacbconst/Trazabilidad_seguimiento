# REGLA OBLIGATORIA DE BASE DE DATOS — LEER SIEMPRE ANTES DE CUALQUIER CONSULTA SQL

Esta regla aplica a **todos los proyectos de este repositorio** (Acuerdos_Comerciales,
Proyectos, y cualquier otro), sin excepción, en cualquier base de datos a la
que Claude se conecte desde aquí (directo con mysqli, scripts de scratchpad,
endpoints PHP, o cualquier otro medio).

## Permisos de Claude sobre la base de datos: SOLO LECTURA

- Claude **solo puede ejecutar `SELECT` / `SHOW` / `DESCRIBE`** u otras
  consultas de solo lectura.
- **Claude JAMÁS puede ejecutar `DELETE`, `DROP TABLE`, `DROP DATABASE`,
  `TRUNCATE`, `UPDATE`, `INSERT`, `ALTER TABLE` ni ninguna otra operación que
  modifique, borre o altere datos o esquema — bajo ninguna circunstancia,
  aunque el usuario lo pida explícitamente en el momento.** Si el usuario pide
  algo así, Claude debe negarse y recordarle esta regla, no ejecutarlo.
- Esto incluye: no crear scripts/endpoints que ejecuten esas operaciones para
  que el usuario los corra él mismo, no ofrecerse a "probarlo" con datos de
  prueba, no hacerlo "solo para verificar algo". Ninguna excepción.
- Si Claude necesita verificar algo que normalmente requeriría escribir datos
  (ej. probar un INSERT), debe proponer el SQL exacto para que el usuario lo
  ejecute él mismo desde HeidiSQL u otra herramienta — nunca ejecutarlo Claude.

**Por qué existe esta regla:** el usuario descubrió que las credenciales de
`config.php` (usadas por Claude para conectarse directo a la base en scripts
de verificación) tienen más privilegios que su propia cuenta personal de
HeidiSQL — incluyendo `ALTER TABLE` (confirmado: Claude agregó una columna
real a `repositorio_acuerdos` en una sesión anterior) y probablemente
`DELETE`/`DROP`. Por seguridad, el usuario decidió que Claude debe operar
como si solo tuviera permiso de lectura, sin importar lo que la cuenta real
permita técnicamente.
