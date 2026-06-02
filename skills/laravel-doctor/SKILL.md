---
name: laravel-doctor
description: Use after running laravel-doctor to read its findings and fix the reported Laravel issues (security, performance, Eloquent, architecture).
---

# laravel-doctor — arreglar hallazgos

Cuando trabajes en un proyecto Laravel con `laravel-doctor` instalado:

1. Ejecuta `./vendor/bin/laravel-doctor --json` en la raíz del proyecto.
2. Parsea el JSON. Tiene la forma:
   `{ "score": int, "label": string, "diagnostics": [{ id, category, severity, file, line, message, recommendation }] }`.
3. Para cada diagnóstico, abre `file` en la línea `line` y aplica el arreglo que indica
   `recommendation`. El campo `message` explica el impacto real del problema.
4. Prioriza por `severity` (`error` antes que `warning` antes que `info`) y, a igualdad,
   por `category` (`security` primero).
5. Tras aplicar los arreglos, vuelve a ejecutar `laravel-doctor --json` y confirma que el
   `score` sube y que los diagnósticos resueltos desaparecen.

No desactives reglas para subir el score: arregla la causa.
