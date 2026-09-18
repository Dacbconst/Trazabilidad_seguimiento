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

# DISEÑO / REDISEÑOS DE UI — SKILL POR DEFECTO

Este repo tiene instaladas ~27 skills de diseño de distintos paquetes (`npx skills add
emilkowalski/skills`, `npx skills add Leonxlnx/taste-skill`, `npx impeccable install`),
con mucho solape entre ellas (`minimalist-ui`, `high-end-visual-design`,
`industrial-brutalist-ui`, `redesign-existing-projects`, `brandkit`,
`stitch-design-taste`, `design-taste-frontend`, `design-taste-frontend-v1`,
`apple-design`, etc., además de `impeccable`).

- Para **cualquier tarea de rediseño, diseño nuevo o mejora visual de UI** en
  cualquier proyecto de este repo, usar la skill **`impeccable`** por defecto.
- No usar las otras skills de diseño instaladas salvo que el usuario las pida
  explícitamente por nombre.
- `impeccable` mantiene contexto de producto por proyecto en un `PRODUCT.md`
  propio (ej. `EpsonReport/PRODUCT.md`) y un `DESIGN.md` cuando aplica — revisar
  si ya existen antes de iniciar trabajo de diseño en un proyecto, y correr
  `impeccable init` si un proyecto todavía no tiene `PRODUCT.md`.

# COMENTARIOS DE CÓDIGO — UNA SOLA LÍNEA, SIEMPRE

Regla general para todo el repositorio, en cualquier proyecto y cualquier lenguaje (PHP, JS, CSS, etc.):

- Un comentario de código ocupa **una sola línea**, nunca un bloque de varias
  líneas. Si la explicación no entra en una línea razonable, se acorta — no se
  parte en varias líneas de comentario.
- Nada de bloques `/* ... */` multilínea ni de varias líneas `//` seguidas
  explicando lo mismo. Un comentario por idea, y esa idea en una sola línea.
- Esto aplica siempre que Claude escriba o edite código en este repo, sin
  necesidad de que el usuario lo repita cada vez.
