# Limitaciones conocidas

**Nota: este plugin es deliberadamente de solo visualización.** El
profesor no puede señalar, hacer clic ni escribir en la página del
alumno — solo observarla. Ver `docs/decisions.md` para el porqué de esta
decisión y qué código se retiró.

## Vigentes desde la Fase 1

- **El token de entrada viaja en la URL.** Ver
  [security.md](security.md#riesgos-conocidos) — es una limitación
  aceptada para el MVP, no un descuido.
- **Borrado de datos personales por dos titulares.** Una fila de
  `local_remotesupport_session` nombra a la vez a un alumno y a un
  profesor. Cuando se ejercita el derecho de supresión de una de las dos
  personas, se borra la fila completa (no se anonimiza conservando la
  mitad de los datos de la otra persona). Esto es una simplificación
  deliberada para el MVP: significa que borrar los datos del alumno
  también hace desaparecer, de esa fila, el registro de que ese profesor
  atendió esa sesión. Si esto resulta inaceptable en producción, revisar
  antes de salir del estado experimental.
- **Sin tabla de auditoría propia.** Los eventos relevantes de sesión
  (`request_created`, `request_accepted`, `request_cancelled`,
  `session_started`, `session_ended`, `access_denied`) se registran a
  través del sistema estándar de eventos/log de Moodle, no en una tabla
  propia del plugin. Su retención depende de la configuración general de
  logs del sitio.
- **Sin panel de administración dedicado.** Un manager puede cerrar
  cualquier sesión mediante `session_manager::close_session()` (cubierto
  por PHPUnit) pero no hay todavía una página de administración que liste
  todas las sesiones activas del sitio.

## Nuevas desde la Fase 2

- **Los endpoints AJAX requieren Moodle ≥4.2.** `classes/external/*.php`
  usa las clases namespaced `core_external\external_api` y compañía. Se
  probó primero con los nombres globales heredados
  (`external_api`, etc.) tal como existían en Moodle 4.1, pero al
  cargarlos vía el autoloader de un plugin (no incluyendo
  `lib/externallib.php` manualmente) PHPUnit los rechaza con "debe
  ejecutarse en un proceso aislado" — es una protección real de Moodle
  contra un patrón de carga legado, no un capricho de este plugin. El
  resto del plugin (sesiones, tokens, capacidades) sigue funcionando
  desde Moodle 4.1; solo esta parte necesitaría revisarse para un sitio
  4.1/4.2 real.

- **Selector de "contenido principal" único y fijo**
  (`#region-main` → `main[role="main"]` → `main` → `body`). En temas muy
  personalizados que no usen ninguno de esos selectores, la reconstrucción
  cae a `<body>` completo, que puede incluir más ruido (cabecera,
  navegación) del deseado. No hay configuración por tema todavía.
- **Sin captura de contenido dentro de `iframe`, aunque sea del mismo
  sitio.** La etiqueta se elimina por completo al sanear — esto incluye
  actividades que renderizan su contenido en un `iframe` propio (algunos
  H5P, LTI, SCORM): el profesor verá un hueco vacío donde estaría esa
  actividad, no un error, pero tampoco su contenido.
- **La foto de página es HTML "congelado", no la página funcionando.**
  Enlaces, botones y formularios se ven pero no reaccionan dentro del
  recuadro del profesor (es intencional: el `iframe` no ejecuta scripts).
  No es una vista previa interactiva.
- **Ancho de banda por foto completa, no por diferencias.** Ver
  `docs/decisions.md` y `docs/security.md`.
- **La purga de eventos huérfanos depende de una tarea programada, no es
  instantánea.** Un evento de una sesión que nunca cerró correctamente
  (pestaña cerrada, cliente colgado) vive como máximo ~2 minutos (ver
  `classes/task/purge_events.php`) en vez de desaparecer en el instante
  exacto en que la sesión debería haber terminado.
- **Sin pruebas JavaScript automatizadas (Jest).** El entorno de pruebas
  no tenía Node/Grunt configurado; instalarlo era otra intervención de
  infraestructura considerable. La lógica de saneamiento/validación está
  cubierta en PHPUnit del lado servidor (la capa autoritativa); la
  construcción de la foto en el navegador y el pintado en el `iframe` solo
  están cubiertos por la verificación manual. Ver `docs/testing.md`.
- **Sin build real de AMD (Grunt/Webpack).** `amd/build/*.min.js` son
  copias literales de `amd/src/*.js`, no minificadas de verdad. Ver
  `docs/decisions.md`.
- **No probado contra SCORM, H5P, LTI, contenido externo, editores
  enriquecidos, popups ni cuestionarios en curso** — la exclusión de
  `iframe` cubre el caso más común, pero no se ha verificado
  exhaustivamente contra cada tipo de actividad.

## Nuevas tras completar el MVP — aviso al profesor cuando el alumno finaliza

- **No se puede saber con certeza quién cerró la sesión**, solo que no
  fue el propio profesor desde esa misma pestaña. El mensaje asume que
  fue el alumno (el caso ampliamente más frecuente); si en realidad la
  cerró un manager, o el mismo profesor desde otra pestaña, el aviso
  sería técnicamente impreciso pero inofensivo (solo texto, ningún
  efecto). Ver `docs/decisions.md`.
- **No cierra la pestaña**, solo muestra un aviso y un enlace de
  vuelta — cerrar una pestaña que no se abrió con `window.open()` no es
  posible desde JavaScript en ningún navegador moderno.
- **No probado en un navegador real** (mismo motivo que el resto del
  módulo de reproducción).

## Nuevas desde la Fase 4

- **Solo se captura un modal a la vez, el primero que encuentre el
  selector.** Si hubiera varios abiertos simultáneamente (poco frecuente
  en Moodle), solo se relaja el primero.
- **El modal se inyecta al final de `<body>` en el `srcdoc`, no en su
  posición original.** Como su propio CSS lo posiciona con `position:
  fixed`/`absolute`, normalmente no importa dónde quede en el árbol, pero
  un tema muy personalizado que dependiera de la posición relativa podría
  verse distinto.
- **La detección de modal se basa en clases de Bootstrap
  (`.modal.show`, `.modal.in`) o en `aria-modal="true"`.** Un componente
  de diálogo que no use ninguna de las dos convenciones (poco común en
  Moodle estándar) no se detectaría.
- **Las hojas de estilo se referencian por URL, no se congelan en el
  momento de la foto.** Si el profesor carga una versión de la CSS más
  nueva que la que tenía el alumno en el momento exacto de la captura
  (por ejemplo, justo tras purgar cachés de tema), el aspecto podría
  variar mínimamente. Irrelevante en la práctica porque el CSS de un
  tema no cambia en mitad de una sesión de asistencia.
- **`resync_request` no fuerza también un reenvío de scroll/modal
  inmediatos más allá de la foto `page`** — la foto `page` sí incluye el
  scroll y el modal actuales, así que en la práctica una resincronización
  completa ya cubre todo, pero conceptualmente es un único evento "pide
  una foto nueva", no un mecanismo genérico de resincronización por tipo.
- **No se ha verificado en un navegador real que la carga del `<link>`
  de CSS dentro del `iframe` sandbox funcione exactamente como se
  espera.**

## Nuevas tras completar el MVP — icono de solicitudes pendientes

- **El contador no se actualiza en vivo.** Se recalcula en cada carga de
  página, no mientras el profesor permanece en una única pantalla — ver
  `docs/decisions.md` para por qué se descartó el sondeo en segundo
  plano en esta primera versión.
- **El icono desaparece por completo cuando no hay nada pendiente**, en
  vez de quedarse siempre visible con un contador en cero como la
  campana de notificaciones — decisión deliberada, no un descuido; ver
  `docs/decisions.md`.
- **Sin pruebas de la plantilla Mustache en sí** (`navbar_requests.mustache`)
  ni de su integración visual real en `navbar.mustache` del tema — las
  pruebas de `tests/lib_test.php` cubren la lógica de autorización y
  contenido del HTML devuelto, no su renderizado final en un navegador.

## Nuevas tras completar el MVP — ciclo de vida de solicitud/sesión sin recarga de página

- **No es tiempo real, es sondeo periódico.** Un cambio de estado puede
  tardar hasta 4 s en reflejarse en `request.php`/`view.php`, y hasta
  15 s en el badge del navbar — igual que el resto del plugin (sondeo
  de eventos en sesión), no hay WebSocket. Aceptable para el objetivo de
  1-20 sesiones simultáneas del MVP.
- **El sondeo del badge de navbar corre en todas las páginas de Moodle**
  para cualquier profesor con capacidad de proveer asistencia, no solo
  en las páginas propias del plugin — a diferencia del resto del
  sondeo, que solo existe mientras el alumno/profesor tiene
  `request.php`/`view.php` abiertos. Es una petición AJAX barata cada
  15 s (se pausa si la pestaña no es visible), pero es, por diseño, el
  primer sondeo sitewide de este plugin; revisar si el sitio crece
  mucho más allá de ese objetivo.
- **Sin pruebas JavaScript automatizadas** para `session_requests.js`,
  `student_client.js`, `teacher_client.js` ni `navbar_badge.js`, mismo
  motivo que el resto del plugin (sin Node/Grunt en el servidor de
  pruebas). La interceptación de clics, el `setInterval`/
  `visibilitychange`, y el re-renderizado vía `core/templates` solo
  están cubiertos por la verificación manual (`docs/testing.md`).
- **El `sessionid` de una fila de la tabla del profesor se extrae del
  `href` ya renderizado** (`teacher_client.js`), en vez de venir en un
  atributo `data-*` dedicado — funciona porque `teacher_dashboard.mustache`
  no cambió y ese `href` ya llevaba el `sessionid` como parámetro de
  consulta firmado con `sesskey`, pero es una dependencia implícita
  entre el JS y la forma exacta en que la plantilla construye esas
  urls; un cambio futuro en la plantilla que dejara de incluir
  `sessionid` en la url rompería silenciosamente la interceptación (los
  enlaces seguirían funcionando como recarga completa, vía
  progressive enhancement, pero sin la actualización sin recarga).

## Nuevas tras completar el MVP — modo de captura `fullpage`

- **Ajuste único de sitio, no por sesión ni por profesor.** Todas las
  sesiones activas del sitio usan el mismo modo (`main` o `fullpage`) a
  la vez; no hay forma de que un profesor concreto pida "página
  completa" solo para su propia sesión sin cambiarlo para todo el
  sitio. Decisión deliberada (ver `docs/decisions.md`), no una
  limitación técnica de por qué no podría ser de otra forma.
- **Más ruido potencial en el reenvío.** Al vigilar `<body>` entero, en
  `fullpage` cualquier elemento vivo de la navegación (por ejemplo, un
  badge que se actualiza periódicamente) puede disparar una foto nueva
  con más frecuencia que en `main`, donde ese elemento nunca estaba
  dentro de lo observado.
- **Sin lista de bloques "sensibles" que excluir selectivamente.**
  `fullpage` es todo o nada: no hay forma de decir "captura navegación
  y pie, pero no este bloque lateral en concreto". Si un bloque
  concreto resulta problemático, la única palanca disponible hoy es
  volver a `main`.
- **No probado en un navegador real** (mismo motivo que el resto de
  Fase 2/3/4: sin esa herramienta en esta sesión) — en particular, si
  la estructura de bloques/tema de un sitio muy personalizado hace que
  la reconstrucción de página completa se vea notablemente distinta al
  original. Ver pasos de verificación manual añadidos en
  `docs/testing.md`.

## Nuevas tras completar el MVP — la reconstrucción no es scrollable de forma nativa

- **Elementos `position: fixed`/`sticky` dentro del contenido capturado
  no se comportan como tales.** El documento del iframe ya no tiene
  overflow scrollable propio: la posición de scroll se simula
  aplicando `transform: translate()` a un `<div>` que envuelve todo el
  contenido capturado, y un `transform` en un ancestro convierte a ese
  `<div>` en el contenedor de referencia de cualquier descendiente
  `fixed`/`sticky` (deja de posicionarse respecto al viewport). En la
  práctica solo relevante en el modo de captura `fullpage` (por ejemplo,
  una barra de navegación `sticky` del tema, que se desplazaría junto
  con el resto en vez de quedarse fija); el modal (que sí sigue
  comportándose como `position: fixed` normal) se mantiene
  deliberadamente fuera de ese `<div>`, y el modo `main` rara vez tiene
  este patrón. Aceptado conscientemente a cambio de que el profesor no
  pueda desplazar la reconstrucción manualmente por ninguna vía. Ver
  `docs/decisions.md`.
