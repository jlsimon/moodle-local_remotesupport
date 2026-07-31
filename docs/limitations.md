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

- **Elementos `position: sticky` dentro del contenido capturado no se
  comportan como tales** (a diferencia de `fixed`, que sí se corrigió —
  ver más abajo). El documento del iframe ya no tiene overflow
  scrollable propio: la posición de scroll se simula aplicando
  `transform: translate()` a un `<div>` que envuelve todo el contenido
  capturado, y un `transform` en un ancestro convierte a ese `<div>` en
  el contenedor de referencia de cualquier descendiente `sticky` (deja
  de posicionarse respecto al viewport). Deliberadamente no se aplica a
  `sticky` la misma extracción que a `fixed`: un elemento `sticky`
  suele depender de su posición de flujo original (offset, anchura)
  para "pegarse" correctamente, y reubicarlo sin ese contexto podría
  verse peor que dejarlo. En la práctica, esto solo importa en modo de
  captura `fullpage`, para temas/actividades que usen cabeceras o
  navegación secundaria `sticky` (no `fixed`) — la barra de navegación
  de Boost en sí es `fixed`, no `sticky`, y ya está corregida. Ver
  `docs/decisions.md`.
- **La detección de elementos `fixed` recorre todo el árbol capturado
  con `getComputedStyle()` en cada foto de página.** Coste aceptado sin
  optimizar a la escala declarada del MVP (1-20 sesiones simultáneas),
  limitado en frecuencia por el mismo debounce/latido que ya rige el
  resto de la captura — ver `docs/decisions.md`. Un tema con un DOM
  excepcionalmente grande podría notar el coste en el hilo principal
  del navegador del alumno.

## Nuevas tras completar el MVP — chat de texto

- **Alcance deliberadamente mínimo.** Solo texto plano: sin adjuntos,
  sin emojis con selector propio, sin indicadores de "escribiendo...",
  sin confirmaciones de lectura, sin edición ni borrado de mensajes
  enviados. El documento base excluye "chat complejo" del MVP; este es
  el intercambio mínimo que se consideró fuera de esa exclusión — ver
  `docs/decisions.md`.
- **Nada sobrevive al cierre de la sesión.** Los mensajes persisten
  mientras la sesión está activa (a diferencia de `page`/`scroll`, que
  se purgan a los 2 minutos si nadie los consume), pero desaparecen por
  completo al cerrarla, igual que el resto de eventos. No queda ningún
  transcript, exportación ni registro de auditoría del contenido de la
  conversación en ningún sitio.
- **Límite de longitud por mensaje** (1000 caracteres,
  `MAX_CHAT_MESSAGE_LENGTH`); un mensaje más largo se trunca, no se
  rechaza. No hay límite al número total de mensajes de una sesión más
  allá de su propia duración.
- **No disponible mientras el profesor está en pantalla completa.**
  `position: fixed` anidado dentro de un elemento en pantalla completa
  resultó poco fiable para el hit-testing de clics en al menos un
  navegador probado por el usuario (el botón "Enviar" no reaccionaba).
  En vez de perseguir esa combinación sin poder probar en un navegador
  real durante esta sesión, el chat se oculta automáticamente al entrar
  en pantalla completa y reaparece al salir — el profesor debe salir de
  pantalla completa para usarlo. Ver `docs/decisions.md`.
- **Sin pruebas JavaScript** (mismo hueco de siempre, ver "Vigentes
  desde la Fase 2" más abajo): la lógica de `chat_widget.js` (bidireccional
  own/other, contador de no leídos, heurística de "primera tanda no es
  no-leído") solo se ha verificado con `node --check` y pruebas manuales,
  no con un runner JS.
- **No probado en un navegador real** (mismo motivo que el resto de este
  documento: sin esa herramienta en esta sesión) — en particular, si dos
  pestañas del profesor abren la misma sesión simultáneamente, ambas
  recibirían y marcarían como consumidos los mismos mensajes entrantes,
  pudiendo dividir la conversación entre las dos en vez de que ambas
  vean todo (mismo comportamiento, y misma limitación, que ya existía
  para `page`/`scroll` antes del chat).

## Nuevas tras completar el MVP — grabación permanente de la sesión

- **Al construirse (esta primera fase, solo almacenamiento) no había
  forma de ver la grabación.** Eso se resolvió con la reproducción
  (`sessionreplay.php`) documentada más abajo; se deja esta nota porque
  las decisiones y compromisos de esa primera fase (retención,
  borrado) siguen aplicando sin cambios a la reproducción.
- **El chat empezó a formar parte de la grabación al construir la
  reproducción**, revisando la decisión original de esta sección (que
  decía lo contrario). Solo sesiones cerradas después de ese cambio
  tienen chat grabado — ver la sección de reproducción más abajo.
- **Colisiona deliberadamente con dos requisitos del documento base**
  (exclusión de "grabación de sesiones" y la instrucción de no
  almacenar el contenido completo de las sesiones indefinidamente) — una
  decisión consciente del usuario, no un descuido. Ver
  `docs/decisions.md` para el razonamiento completo y las políticas de
  retención/borrado acordadas.
- **CSS del tema potencialmente obsoleto en una reproducción futura.**
  La reconstrucción en vivo carga el CSS del tema por URL en el momento
  de verla (`payload.css`), no su contenido; Moodle revisa
  periódicamente la caché del tema, cambiando esa URL. Una grabación de
  hace meses, reproducida más adelante, podría verse con estilos rotos
  o distintos a como se veía originalmente — no es grave (el HTML
  capturado sigue intacto), pero afecta a la fidelidad visual de una
  reproducción futura.
- **Sin límite de tamaño acumulado por sesión.** Cada sesión puede
  generar decenas de capturas de hasta 400 000 caracteres; sin política
  de recorte dentro de una misma sesión (solo la ventana de retención
  entre sesiones), una sesión excepcionalmente larga podría generar una
  grabación considerable.
- **No probado en un navegador real** (mismo motivo que el resto de este
  documento).

## Nuevas tras completar el MVP — historial de sesiones del profesor

- **El listado en sí sigue mostrando solo metadatos** (fecha, curso,
  nombre/apellidos del alumno, duración); ver el contenido grabado
  requiere pulsar la columna "#" — ver la sección de reproducción más
  abajo.
- **Solo profesor, sin vista equivalente para el alumno todavía.** El
  usuario pidió explícitamente el listado del profesor en este cambio;
  un listado para que el alumno vea sus propias sesiones pasadas sería
  una ampliación aparte, con su propia capacidad y página.
- **Sin filtro por curso/alumno/rango de fechas**, solo ordenación por
  columna — `table_sql` soporta añadir filtros más adelante si hiciera
  falta, pero no se ha pedido ni se ha construido en este cambio.
- **Sin exportación** (CSV/Excel) — `table_sql` también lo soporta de
  forma nativa si se necesitara más adelante.
- **Sin pruebas a nivel de renderizado de `table_sql`** (cabeceras
  ordenables, HTML de paginación): las columnas/orden se verificaron
  ejecutando directamente el SQL subyacente en PHPUnit y con una
  comprobación manual de humo contra la base de datos real (ver
  `docs/testing.md`), no con una prueba automatizada del HTML que
  `table_sql` genera — mismo tipo de hueco que las pruebas de
  renderizado JS en el resto del plugin.
- **No probado en un navegador real** (mismo motivo que el resto de este
  documento) — en particular, los enlaces de ordenación por columna
  (que dependen de que el navegador siga un `<a href>` con parámetros
  GET) no se han probado interactivamente.
- **Selector de elementos por página sin opción "Todas"** (10/20/50/100
  únicamente) — no se pidió, y con un historial que crece sin límite en
  el tiempo, "mostrar todas" de golpe iría en contra del propio motivo
  de tener paginación.
- **Cambiar el número de elementos por página no conserva el orden de
  columna elegido**, porque el selector envía a la URL base de la
  página (sin los parámetros `tsort`/`tdir` que `table_sql` añade al
  ordenar) — una pequeña aspereza de interfaz, no un error, y el mismo
  compromiso que asumen varias páginas equivalentes del propio núcleo
  de Moodle.
- **`export_user_preferences()` de la nueva preferencia
  (`session_history_table::PREF_PERPAGE`) no tiene una prueba
  PHPUnit dedicada** — mismo hueco que ya existía para la preferencia
  del profesor (`teacher_settings::PREF_SUPPORT_ENABLED`), sin cambiar
  con esta ampliación; verificado en su lugar con una comprobación
  manual de humo (guardar/leer la preferencia, y que el desplegable
  refleja el valor seleccionado).

## Nuevas tras completar el MVP — reproducción de sesiones grabadas

- **Sesiones cerradas antes de esta funcionalidad no tienen chat que
  reproducir.** El chat solo empezó a grabarse permanentemente al
  construir la reproducción; su transcripción para sesiones anteriores
  nunca se guardó y no se puede recuperar. Solo afecta al chat: la
  pantalla (`page`/`scroll`) sí estaba grabada desde antes y se
  reproduce con normalidad para cualquier sesión, tenga o no chat.
- **Se descarga toda la grabación de una vez, sin paginar ni cargar de
  forma progresiva.** Aceptado conscientemente a la escala de este MVP
  (1-20 sesiones simultáneas, sesiones de minutos a una hora en la
  práctica); una sesión con una grabación excepcionalmente larga (ver
  también "sin límite de tamaño acumulado por sesión" arriba) tendría un
  payload inicial de descarga grande. Carga progresiva/paginada del
  track queda como posible mejora futura, no construida ahora.
- **Solo profesor, sin reproducción para el alumno.** El acceso está
  gateado por `local/remotesupport:replaysession` (capacidad de
  profesorado); el alumno no tiene ninguna pantalla para reproducir sus
  propias sesiones pasadas, aunque `local_remotesupport_track` contenga
  su propia actividad. Sería una ampliación aparte, con su propia
  capacidad.
- **La barra de progreso salta por tiempo, no por "eventos".** Al
  arrastrarla se recalcula el estado (última pantalla, último scroll,
  transcripción de chat) para ese instante, pero no hay una vista de
  "lista de eventos" ni marcadores en la barra que indiquen dónde hay
  cambios de página o mensajes nuevos — solo una barra de progreso lisa,
  como un reproductor de vídeo simple.
- **El CSS del tema puede haberse quedado obsoleto**, mismo motivo ya
  documentado para la grabación en sí: la reproducción carga el CSS por
  URL en el momento de verla, no su contenido congelado en el instante
  de la captura original.
- **Sin pruebas JavaScript** para `session_replay.js`/`screen_renderer.js`
  (mismo hueco de siempre, ver "Vigentes desde la Fase 2"): la lógica de
  reproducción (cálculo del último evento anterior a un instante,
  reconstrucción de la transcripción de chat, control de velocidad) solo
  se verificó con `node --check` y los pasos de verificación manual de
  más abajo.
- **No probado en un navegador real** (mismo motivo que el resto de este
  documento) — en particular, la fluidez de la reproducción a velocidades
  altas (4x/8x) y el comportamiento de la barra de progreso al
  arrastrarla rápidamente no se han probado interactivamente.
- **`sessionchat.php` (vista de solo chat, añadida después) siempre
  muestra su enlace en el historial, aunque la sesión no tenga ningún
  mensaje grabado** — en ese caso la propia página muestra un aviso de
  "no hubo mensajes" en vez de una lista vacía. No hay una comprobación
  previa por fila que oculte el enlace cuando no hay chat, por
  simplicidad (ver `docs/decisions.md`); mismo criterio ya aplicado al
  enlace "#" de reproducción.
- **Sin pruebas a nivel de renderizado de `sessionchat.php`/su
  plantilla** (mismo hueco que el resto de páginas de este plugin, ver
  "historial de sesiones del profesor" más arriba): verificado con una
  prueba PHPUnit de `track_manager::get_chat_for_session()` y una
  comprobación de humo por HTTP autenticado contra el sitio real, no con
  una prueba automatizada del HTML/Mustache en sí.

## Nuevas tras completar el MVP — posición del cursor del alumno

- **Se registra permanentemente, a diferencia de otros movimientos del
  navegador.** Una excepción deliberada y explícitamente pedida por el
  usuario a la guía general de no grabar cada movimiento del ratón — ver
  `docs/decisions.md` para el razonamiento completo y las mitigaciones
  de coste adoptadas (atado a `mousemove`, no a un temporizador; tasa de
  muestreo configurable).
- **Sin interpolación entre muestras, ni en directo ni en la
  reproducción.** El punto salta directamente de una posición a la
  siguiente en cuanto llega/se aplica un evento `cursor`, igual que
  `scroll` — con una tasa de muestreo alta (2000 ms) el movimiento se ve
  a saltos, no como un trazo continuo. Suavizarlo añadiría una capa de
  animación que el MVP no necesita.
- **No indica qué elemento hay bajo el cursor**, solo su posición en
  coordenadas de viewport — a diferencia del cursor remoto retirado
  (Fase 3, ver la nota al principio de `docs/architecture.md`), que sí
  podía resaltar un elemento concreto. Esta funcionalidad es puramente
  informativa, sin selector de elemento ni intención de señalar nada.
- **No se distingue un alumno con varios monitores o que redimensiona la
  ventana a mitad de movimiento** más allá de lo que ya cubre el
  reescalado del `iframe` (`applyViewportSize`) — un cambio de tamaño
  brusco puede producir un salto visual puntual del punto hasta el
  siguiente evento `cursor`.
- **Sin pruebas JavaScript** para la lógica de `screen_renderer.js`/
  `session_replay.js`/`event_capture.js` añadida (mismo hueco de
  siempre, ver "Vigentes desde la Fase 2"): verificado con `node --check`
  y los pasos de verificación manual de `docs/testing.md`, no con un
  entorno de pruebas de navegador real.
- **No probado en un navegador real** (mismo motivo que el resto de este
  documento): el seguimiento visual del punto, su comportamiento al
  cambiar de página, y su sincronización durante la reproducción quedan
  pendientes de la verificación manual del usuario.

## Nuevas tras completar el MVP — marca visual y sonido en los clics del alumno

- **Se registra permanentemente, igual que la posición del cursor.**
  Misma excepción deliberada, explícitamente pedida por el usuario, a la
  guía general de no grabar interacciones de ratón — ver
  `docs/decisions.md`. A diferencia de `cursor`, no hay ajuste de "tasa
  de muestreo" que atenuar: un clic es ya un evento discreto e
  infrecuente por sí mismo.
- **El sonido puede no reproducirse la primera vez**, si el navegador
  del profesor bloquea el audio por su política de autoplay hasta que
  ha habido algún gesto del usuario en la página (clic en el botón de
  sonido, en pantalla completa, etc.). No hay ningún aviso al profesor
  cuando esto ocurre — la marca visual sigue funcionando igual, el
  sonido es simplemente un extra que puede fallar en silencio.
- **El sonido puede ser audible por terceros cerca del profesor**
  (una sala compartida, un altavoz sin auriculares) — no es un problema
  de seguridad del plugin en sí, pero es un motivo real por el que
  puede convenir desactivarlo; de ahí el botón de silenciar/activar por
  sesión, además del ajuste general.
- **En la reproducción, un salto brusco con la barra de progreso nunca
  "recupera" las marcas/sonidos de los clics saltados.** Es
  deliberado (ver `docs/decisions.md`), no un fallo: solo se disparan
  avanzando de forma natural (reproduciendo hacia delante), nunca al
  saltar. Si el profesor quiere ver exactamente cuándo hizo clic el
  alumno en un tramo concreto, tiene que reproducirlo, no solo
  saltar hasta ahí.
- **La detección de "propia interfaz del plugin" es la misma que ya
  usan los observadores de mutaciones** (`isOwnElement`, basada en
  buscar una clase `local-remotesupport-*` en el propio elemento o
  alguno de sus ancestros) — no un mecanismo nuevo, pero comparte
  cualquier limitación que ya tuviera: si algún elemento inyectado por
  el plugin no llevara esa clase por error, sus clics sí se
  capturarían. No se ha detectado ningún caso así, pero no hay una
  prueba automatizada que lo garantice para elementos futuros.
- **Sin pruebas JavaScript** para la lógica añadida en
  `event_capture.js`/`screen_renderer.js`/`event_player.js`/
  `session_replay.js` (mismo hueco de siempre, ver "Vigentes desde la
  Fase 2"): verificado con `node --check` y los pasos de verificación
  manual de `docs/testing.md`, no con un entorno de pruebas de
  navegador real — en particular, la distinción "avance natural vs.
  salto manual" en la reproducción (basada en la bandera `playing`) no
  se ha podido probar interactivamente en esta sesión.
- **No probado en un navegador real** (mismo motivo que el resto de
  este documento): la marca visual, el sonido (y su bloqueo por
  autoplay), el botón de silenciar en sus dos ubicaciones, y el
  comportamiento al saltar en la reproducción quedan pendientes de la
  verificación manual del usuario.

## Nuevas tras completar el MVP — precisión de la reconstrucción, límite estructural

- **La precisión total (el cursor siempre sobre el mismo elemento
  clicable en las dos pantallas) no es un objetivo alcanzable con esta
  arquitectura, ni tras las mejoras de esta sección.** Es
  reconstrucción por DOM en un motor de renderizado distinto, no
  espejo de pantalla — decisión de diseño del documento base, no algo
  que un ajuste adicional vaya a cerrar del todo. Ver
  `docs/decisions.md` para el razonamiento completo; el criterio de
  aceptación pasó a ser "lo bastante cerca", con el consentimiento
  explícito del usuario.
- **Sigue existiendo una ventana de desincronización temporal, solo
  más estrecha que antes.** La foto de página se manda con un debounce
  de 1,5 s tras una mutación, cada 5 s como mucho (antes 10 s), y
  ahora también justo después de cada clic — pero un cambio de la
  página real que ocurra *entre* esos momentos sigue sin reflejarse de
  inmediato en la reconstrucción del profesor.
- **La limpieza de CSS inline no es un parser real, es texto.**
  `sanitize_inline_css()` elimina `@import` y cualquier `url(...)` con
  expresiones regulares, no analizando la sintaxis CSS de verdad — se
  pierde cualquier uso legítimo de `url()` (imágenes de fondo,
  `@font-face`) en las hojas inline capturadas, y un caso límite de CSS
  con sintaxis inusual podría, en teoría, no coincidir exactamente con
  las expresiones regulares usadas. Aceptado porque PHP no tiene un
  parser de CSS equivalente a `DOMDocument`, y porque el sandbox del
  `iframe` sigue siendo la barrera real contra ejecución de scripts.
- **Elementos `position: sticky` siguen sin corregirse** (a diferencia
  de `fixed`, ya arreglado) — ver la entrada anterior de esta misma
  sección de limitaciones para el porqué.
- **No probado en un navegador real** (mismo motivo que el resto de
  este documento): en particular, si la mejora percibida es
  suficiente para el uso real de la funcionalidad queda pendiente de
  que el usuario retome las pruebas de precisión que había aplazado.

## Nuevas tras completar el MVP — resaltado del elemento bajo el cursor

- **El respaldo estructural del selector puede señalar el elemento
  equivocado, no solo fallar en encontrar ninguno.** Cuando el
  elemento bajo el ratón no tiene `id`, `buildRobustSelector()`
  construye una ruta basada en la posición entre hermanos del mismo
  tag (`tag:nth-of-type(n)`) — si el orden de esos hermanos cambió
  entre la foto de página capturada y el momento del resaltado (poco
  probable dada la ventana de desincronización ya acortada, pero
  posible), el selector podría coincidir con un elemento distinto al
  que el alumno señala realmente. Visualmente confuso, pero nunca una
  acción: el plugin solo marca, no actúa sobre lo resaltado. Ver
  `docs/decisions.md` para por qué se aceptó este riesgo residual en
  vez de un tercer nivel de robustez (`data-*`) que lo mitigaría solo
  parcialmente.
- **Solo se resaltan elementos "clicables" según una lista fija de
  selectores** (`a[href]`, `button`, ciertos `input`, `select`,
  `role="button"`/`"link"`/`"tab"`/`"menuitem"`, `summary`, `label`) —
  un elemento interactivo por otras vías (por ejemplo, con un
  manejador de clic añadido por JavaScript sin ninguno de esos
  atributos/roles) no se detecta como "clicable" y no genera
  resaltado, aunque el alumno pueda pulsarlo igualmente.
- **El resaltado no se ha probado en un navegador real** (mismo motivo
  que el resto de este documento): en particular, si el `outline`
  elegido es suficientemente visible sobre distintos temas/colores de
  fondo, y si el respaldo estructural resulta fiable en la práctica en
  páginas reales de Moodle, quedan pendientes de la verificación
  manual del usuario.

## Nuevas tras completar el MVP — vuelta a la página de origen al entrar en la sesión

- **Solo se captura la página de origen si el alumno llega a
  `request.php` a través de los propios enlaces del plugin** (menú del
  curso, botón flotante). Si escribe la URL directamente, la abre
  desde un marcador guardado, o llega por cualquier otra vía sin
  `fromurl`, no hay ninguna página de origen que recordar — se usa la
  portada del curso, el comportamiento de siempre.
- **Una URL guardada puede quedar obsoleta para cuando el profesor
  acepta.** Si la actividad concreta donde estaba el alumno se borra,
  se oculta, o cambia de sitio entre la solicitud y la aceptación
  (puede pasar minutos, incluso más si la expiración de la solicitud
  es larga), la redirección llevará a esa URL igualmente — el
  resultado depende de cómo la propia página de destino maneje ya no
  encontrar lo que esperaba (típicamente un error de Moodle), no de
  nada que este plugin controle o pueda prevenir.
- **URLs muy largas (más de 255 caracteres) no se guardan, ni
  siquiera truncadas** — se descartan por completo y se usa la
  portada del curso. Poco frecuente en la práctica (una URL de Moodle
  típica está muy por debajo de ese límite), pero una página con una
  cadena de consulta especialmente larga no tendrá vuelta a su URL
  exacta.
- **No probado en un navegador real** (mismo motivo que el resto de
  este documento): en particular, la redirección inmediata sin ninguna
  página de confirmación propia, y su comportamiento al entrar desde
  el botón flotante en distintas páginas del sitio, quedan pendientes
  de la verificación manual del usuario.

## Nuevas tras completar el MVP — señalar un elemento clicable (profesor → alumno)

- **Desactivado por defecto.** `local_remotesupport/enableteacherpointer`
  no existe para ninguna sesión hasta que un administrador lo activa
  explícitamente — es una reversión selectiva de la reducción a
  solo-visualización (`aa58c26`), no una función disponible de serie.
- **Mismo riesgo de selector estructural que el resaltado `hover`
  existente, ahora en sentido contrario.** Si la foto de página que ve
  el profesor está ligeramente desactualizada respecto al DOM real del
  alumno en el momento del clic, `buildRobustSelector()` podría no
  encontrar el elemento, o encontrar uno distinto. Sigue siendo
  puramente visual (nunca se ejecuta ningún clic), pero aquí el efecto
  se ve en la pantalla del alumno, no solo en la del profesor. Ver la
  entrada equivalente más arriba ("resaltado del elemento bajo el
  cursor") para el mismo razonamiento sobre por qué se acepta.
- **Solo se puede señalar lo que la lista `CLICKABLE_SELECTOR` (en
  `dom_selector.js`) reconoce como clicable** — un elemento interactivo
  por otras vías (JavaScript propio de la actividad, sin ninguno de
  esos atributos/roles) no aparece como candidato al pasar el ratón por
  la reconstrucción, aunque sea perfectamente clicable para el alumno.
- **Sin persistencia en la reproducción de sesiones.** `teacher_highlight`
  no se graba en `local_remotesupport_track` (decisión deliberada, ver
  `docs/decisions.md`) — reproducir una sesión antigua nunca muestra
  dónde señaló el profesor, solo lo que el alumno veía y hacía.
- **Verificado extremo a extremo con Chromium headless (Playwright),
  no con los navegadores reales que usarán alumno y profesor.** Tras
  dos rondas de bugs reales en uso (ver `docs/decisions.md`: un
  selector anclado en un `id` sintético, y un iframe reescalado que no
  recibía eventos de ratón reales), se montó una verificación completa
  — curso y usuarios desechables, sesión real, clic real sobre un
  elemento concreto ("Calificaciones") — que confirmó que el recuadro
  aparece en el alumno exactamente sobre el elemento señalado. Sigue
  pendiente la verificación manual en los navegadores reales del
  usuario (Firefox, Safari, y Chrome/Edge no headless): en particular,
  si el recuadro y su etiqueta son suficientemente visibles sobre
  distintos temas, y si el reposicionado durante scroll se percibe
  fluido. Tampoco existe un arnés de pruebas JavaScript en este
  proyecto (ver `docs/testing.md`), así que `dom_selector.js` y la
  lógica de `startPicking()`/`stopPicking()` no tienen pruebas
  automáticas propias, solo esta verificación puntual y la revisión por
  lectura.
