---
name: feedback-arquitectura-sin-sobreingenieria
description: Mantener arquitectura simple y escalable en Proyectos/Pintuco — reusar getters/helpers existentes en vez de crear abstracciones nuevas
metadata:
  type: feedback
---

Al agregar funcionalidad a `Proyectos/Pintuco` (ej. agendamientos.php y sus getters), priorizar reusar lo que ya existe (parámetros de filtro en `get_agenda.php`, helpers como `normalizarEstado()`/`renderizar()`) en vez de crear endpoints, capas o abstracciones nuevas para cada feature.

**Por qué:** El usuario pidió explícitamente "que estés organizando bien la arquitectura y estructura sin aplicar sobreingeniería para mantener la escalabilidad a futuro" al pedir features incrementales (filtros, estados, leyenda). Es una señal de que está vigilando que el código no acumule duplicación ni capas innecesarias a medida que se piden mejoras una por una.

**Cómo aplicar:** Antes de crear un nuevo getter/endpoint o una nueva función de render, revisar si uno existente ya puede extenderse (ej. `get_agenda.php` ya soporta filtros por `usuario`/`estado_agenda`, se reusó para el filtro de Promotor en vez de crear un endpoint de catálogo aparte). Centralizar lógica de negocio repetida en un solo helper (ej. `normalizarEstado()` como única fuente de verdad para colores/badge/leyenda) en vez de repetir condicionales en cada lugar.

Relacionado: [[feedback_detalle_ux_formato]]
