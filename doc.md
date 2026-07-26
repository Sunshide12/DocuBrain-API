# Guía de Optimización de Flujo de Trabajo y Ahorro de Tokens

> Esta guía documenta las mejores prácticas para optimizar el rendimiento y minimizar el consumo de tokens trabajando con **Antigravity** y **Claude Code**.

---

## 1. ¿Cómo funcionan las reglas del Workspace (`AGENTS.md` y `CLAUDE.md`)?

- **Antigravity**: Lee de forma nativa la ruta `.agents/AGENTS.md` en la raíz de cada proyecto. Todo lo que esté ahí se inyecta en el prompt del sistema en **cada mensaje**.
- **Claude Code**: Lee `CLAUDE.md`. Para no duplicar información, puedes incluir `@.agents/AGENTS.md` dentro de `CLAUDE.md` para usar una **única fuente de verdad**.

> 💡 **Regla de oro de los archivos de reglas**: Mantén `AGENTS.md` ultra-corto (máximo 20-30 líneas). Incluye solo stack, comandos clave, archivos a ignorar y reglas inquebrantables de código.

---

## 2. Sistema de Skills (`.agents/skills/`) — El mayor ahorrador de tokens

### Carga Progresiva vs. Carga en Prompt
- Si pones guías de desarrollo largas en `AGENTS.md`, pagas esa lectura de tokens **en cada interacción**.
- Si las colocas como **Skills**, Antigravity solo lee el `name` y la `description` (~20 tokens por skill). La guía completa se carga **únicamente cuando la tarea la requiere**.

### Estructura de la carpeta de Skills
```text
.agents/
├── AGENTS.md
└── skills/
    ├── crear-agente-rag/
    │   ├── SKILL.md
    │   └── references/ (opcional: JSONs, schemas)
    ├── nueva-mutation-graphql/
    │   └── SKILL.md
    └── depurar-embeddings/
        └── SKILL.md
```

### Plantilla para un `SKILL.md`
```markdown
---
name: nombre-de-la-skill
description: Descripción clara de qué hace y cuándo debe activarse esta skill.
---

# Título del Procedimiento

1. Paso 1...
2. Paso 2...
3. Convención o snippet clave...
```

---

## 3. Estrategias para Evitar Búsquedas Innecesarias (`grep`/`ls`)

1. **Referenciar Archivos Explícitamente**:
   - ❌ *"Modifica la mutación de mensajes"* (Obliga a la IA a buscar en todo el repo).
   - ✅ *"Modifica `app/GraphQL/Mutations/SendMessage.php`"* (La IA va directo al archivo sin turnos de búsqueda).

2. **Filtros de Test Específicos**:
   - ❌ *"Corre los tests"* (Corre toda la suite y vuelca logs gigantes).
   - ✅ *"Ejecuta `php artisan test --filter=DocumentQAAgentTest`"*.

3. **Exclusiones de Inspección**:
   - Asegúrate de ignorar carpetas pesadas en `AGENTS.md`:
     - Backend: `vendor/`, `storage/logs/`, `storage/framework/`, `database/snapshots/`
     - Frontend: `.next/`, `node_modules/`, `out/`, `build/`

---

## 4. Uso de Slash Commands para Tareas Complejas

- **/grill-me**: Úsalo antes de implementar features ambiguas. La IA te entrevistará brevemente para alinear decisiones de diseño antes de escribir código.
- **/goal**: Úsalo para tareas largas en segundo plano donde quieras que el agente trabaje de forma autónoma hasta completar el objetivo.
- **/learn**: Úsalo cuando corrijas un comportamiento recurrente del agente para que guarde la lección a futuro.

---

## 5. Resumen del Flujo Optimizando Tokens

1. **Inicio de conversación**: Prompt corto + referencia directa a los archivos a modificar.
2. **Si es un proceso complejo recurrente**: La IA activará automáticamente la **Skill** correspondiente sin que tengas que explicárselo de nuevo.
3. **Verificación**: Correr solo los tests unitarios específicos del componente modificado.
