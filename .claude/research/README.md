# Briefs de investigación

Acá escribe **solo** el agente `dharma-research`. Cada archivo es un brief técnico
de una feature investigada pero todavía no implementada.

**Convención de nombre:** `AAAA-MM-DD-tema-en-kebab-case.md`

## Cómo se usa el handoff

1. `dharma-research` investiga y deja el brief acá.
2. El brief se le pasa a `dharma-backend` y/o `dharma-frontend` por ruta de archivo
   ("implementá `.claude/research/2026-09-06-proveedores-streaming.md`"), así el
   agente de desarrollo arranca con todo el contexto sin volver a investigar.
3. Los dos agentes de desarrollo leen la misma sección "Contrato entre ambos",
   que es lo que evita que se pisen (rutas, nombres de campos, ids de target,
   eventos `HX-Trigger`).

Cuando un brief queda implementado, marcalo en su encabezado (`**Estado:** implementado`)
o borralo. No lo dejes como si siguiera pendiente.
