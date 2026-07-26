# 002 - Migración del Frontend de Vue a Next.js (React) y Arquitectura Híbrida

## Contexto

El diseño inicial de DocuBrain contemplaba un frontend basado en Vue 3 con Vuetify, consumiendo exclusivamente GraphQL para todas las operaciones, incluida la subida de archivos (multipart). Además, la autenticación se manejaba mediante tokens Bearer guardados en `localStorage`.

Durante el desarrollo de la Fase 7, identificamos varias limitaciones importantes en este enfoque:
1. **Seguridad de Autenticación**: Almacenar tokens Bearer en `localStorage` expone la aplicación a ataques XSS.
2. **Eficiencia en Subida de Archivos**: GraphQL no es eficiente para transmitir archivos binarios grandes (como PDFs). El parseo multipart añade carga innecesaria y dificulta el trackeo preciso del progreso de subida (`upload progress`).
3. **Ecosistema y Componentes UI**: Vue 3 presentaba ciertas fricciones para integrar ecosistemas modernos de diseño premium (como Shadcn/UI o animaciones complejas).

## Decisión

Se decidió descartar la base de Vue y migrar el frontend a **Next.js (App Router) con React y TypeScript**. Adicionalmente, se introdujeron cambios en la arquitectura de interacción con el backend:

1. **Autenticación SPA con Sanctum (Cookies HttpOnly)**:
   - Pasamos de usar tokens en `localStorage` a emitir una cookie de sesión (`laravel_session`) y un token CSRF (`XSRF-TOKEN`).
   - Esto asegura protección contra ataques XSS y se alinea con las mejores prácticas de seguridad para Single Page Applications (SPA).
2. **Next.js Middleware (SSR)**:
   - Se implementó protección de rutas privada (`/dashboard`, `/chat`) utilizando el Middleware en Edge de Next.js, leyendo la sesión en el servidor para evitar "flickering" visual y redirecciones tardías en cliente.
3. **Arquitectura Híbrida (GraphQL + REST)**:
   - **GraphQL** se mantiene para operaciones CRUD complejas y datos relacionales (listado de documentos, chat, metadatos, etc.) usando `graphql-request`.
   - **REST** se introdujo para operaciones binarias pesadas: un endpoint `POST /api/documents/upload` para la subida fluida de PDFs con barra de progreso, y un `GET /api/documents/{id}/download` protegido por sesión para visualizar el PDF en un `iframe`.
4. **Stack UI Premium**:
   - Adopción de **Tailwind CSS + Shadcn/UI** para construir una interfaz "100% Dark Mode Premium".
   - Uso de **Framer Motion** para implementar una interfaz de Chat "Split-Screen" fluida y atractiva.
   - Estado manejado localmente por **Zustand** y remotamente (caché y server-state) por **TanStack Query**.

## Consecuencias

### Positivas
- **Alta Seguridad**: Mitigación de riesgos XSS con cookies HttpOnly.
- **Mejor UX**: UI mucho más robusta y moderna, con seguimiento de progreso real de subidas y animaciones fluidas al chatear y navegar.
- **Desempeño**: La subida REST reduce la sobrecarga en el backend frente a GraphQL Multipart y los iframes cargan los PDFs nativamente con la seguridad de la sesión.

### Negativas
- **Desvío del Roadmap Original**: Requirió refactorizar temporalmente los tests y endpoints que esperaban una subida por GraphQL, y reescribir los resolvers de autenticación (Login/Register) para que establezcan una sesión (`web` guard) en lugar de emitir un Bearer token.
- **Complejidad del Middleware**: El SSR de Next.js requiere un manejo cuidadoso de proxies y cookies hacia `localhost` o la API en producción.
