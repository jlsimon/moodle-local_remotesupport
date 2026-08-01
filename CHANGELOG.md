# Changelog

## 0.24.6 (accesibilidad: nombre accesible del chat y del enlace de reproducción) — 2026-08-01

- Auditoría de accesibilidad (`axe-core`, WCAG2A/WCAG2AA) de la
  interfaz propia del plugin (barra de estado, chat, botones de
  sonido/señalado, historial de sesiones): 0 violaciones en las cuatro
  páginas verificadas en vivo con una sesión activa.
- **Fix**: el campo de texto del chat ahora tiene `aria-label` (antes
  solo `placeholder`, un nombre accesible válido pero no fiable en
  todos los lectores de pantalla).
- **Fix**: el enlace "#123" de reproducir sesión en el historial ahora
  tiene `aria-label="Reproducir sesión #123"` (nueva cadena
  `link_replaysession`) — el texto visible no cambia, solo su nombre
  accesible, antes ambiguo fuera de contexto.
- `amd/build/chat_widget.min.js` regenerado con un build Grunt real
  (núcleo de Moodle clonado en un directorio temporal, sin base de
  datos ni Composer — ver `docs/decisions.md`), no copiado a mano.

## 0.24.5 (revisión de seguridad adversarial, sin cambios de código) — 2026-08-01

- Revisión de seguridad adversarial manual (la skill `/security-review`
  no aplica: requiere un remoto `origin` que este repositorio no
  tiene). Aislamiento de capacidad/propiedad/sesión entre las ocho
  funciones externas: sin hallazgos. Dos hallazgos reales registrados
  en `docs/security.md` para corregir más adelante (a petición
  explícita, no corregidos en este commit): un bypass del filtro
  `javascript:` de `html_sanitizer` vía tabulador/CR/LF incrustado en
  el esquema (actualmente inerte por dos defensas independientes), y
  `returnurl`/`fromurl` sin filtrado de `../` (confinado al mismo
  origen, no es un open redirect externo). Detalle completo en
  `docs/decisions.md`.
- Sin cambios de código del plugin — solo documentación
  (`docs/decisions.md`, `docs/security.md`, `docs/tests_todo.md`) y
  este bump de versión.

## 0.24.4 (verificación: tipos de actividad reales SCORM/H5P/LTI/etc.) — 2026-08-01

- Verificado en vivo, con una sesión de asistencia activa, que la
  exclusión de `iframe` de la reconstrucción de pantalla funciona
  correctamente en foro, libro, tarea, cuestionario (incluido un
  intento sin responder), SCORM, H5P y LTI — no solo en páginas
  genéricas de curso como hasta ahora. 8/8 páginas en verde: sin
  `<iframe` anidado filtrado al `srcdoc`, sin errores de JavaScript en
  ninguna de las dos pestañas, contenido reconstruido de tamaño
  razonable en todos los casos.
- Sin cambios de código del plugin — solo documentación
  (`docs/decisions.md`, `docs/tests_todo.md`) y este bump de versión.
  Detalle completo en `docs/decisions.md`.

## 0.24.3 (verificación E2E: Chromium, Firefox y WebKit) — 2026-08-01

- Verificado en vivo (arnés Playwright de dos contextos, alumno y
  profesor) el ciclo de vida completo de una sesión — login,
  solicitud, aceptación, reconstrucción de pantalla, señalado con
  clic en el iframe reescalado, finalización — en Chromium, Firefox y
  WebKit, no solo Chromium como hasta ahora. 7/7 pasos en verde en los
  tres motores, dos pasadas completas cada uno. Safari real no existe
  en Linux; WebKit es la aproximación más cercana disponible en este
  entorno, documentada como tal, no como una verificación de Safari en
  sentido estricto.
- Sin cambios de código del plugin — solo documentación
  (`docs/decisions.md`, `docs/tests_todo.md`) y este bump de versión.
  Detalle completo, incluido un hallazgo real ajeno al plugin (race de
  temporización en la propia página de login de Moodle, reproducible
  en Firefox), en `docs/decisions.md`.

## 0.24.2 (Jest: dom_selector.js y screen_renderer.js) — 2026-08-01

- Añadidas las primeras pruebas JavaScript automatizadas
  (`tests/jest/`, Jest + jsdom, 37 tests): cobertura completa de
  `dom_selector.js` y de la parte de `screen_renderer.js` que no
  depende de la navegación real de un `iframe`. Ejecutar con
  `npm install && npm test`.
- Nuevos ficheros de desarrollo únicamente (`package.json`,
  `jest.config.js`, `tests/jest/`) — sin cambios de comportamiento en
  tiempo de ejecución. `node_modules/` en `.gitignore`.
- Detalle de las decisiones de diseño (carga de módulos AMD sin
  RequireJS, mock de `iframe` como objeto plano, polyfill de
  `CSS.escape()` para jsdom) en `docs/decisions.md`.

## 0.24.1 (Behat: ciclo de vida de sesiones; fix: aviso de deprecación de before_footer) — 2026-08-01

- Añadidas las primeras pruebas Behat
  (`tests/behat/session_lifecycle.feature`): solicitar y cancelar;
  solicitar, aceptar, ver la sesión activa desde ambos lados y
  finalizar desde el alumno; finalizar desde el profesor. Sin driver
  JavaScript. 3 escenarios × 2 temas, 96 pasos, verificado contra
  Moodle 5.2.1.
- **Fix**: el callback `<component>_before_footer()` de `lib.php`
  (inyecta la barra de estado del alumno y el botón flotante de
  solicitud) está deprecado en Moodle 5.x en favor de la API de hooks
  — cada carga de página emitía un aviso de deprecación. Añadidos
  `classes/hook_callbacks.php` y `db/hooks.php`, que delegan en la
  misma función de `lib.php` sin duplicar lógica; sigue funcionando
  igual en Moodle 4.1–4.x (sin API de hooks). Sin cambios de
  comportamiento visible.
- Detalle completo, incluyendo un hallazgo no obvio sobre colisiones de
  texto en pasos Behat, en `docs/decisions.md`.

## 0.24.0 (moodle-plugin-ci: estilo de código, PHPDoc y build AMD real) — 2026-07-31

- Primera ejecución de `moodle-plugin-ci` contra el plugin, de cara a
  una eventual submission al Moodle Plugins directory. Detalle
  completo y razonamiento en `docs/decisions.md`.
- `phpcs` (estándar `moodle`): 155 errores y 429 avisos corregidos —
  sobre todo PHPDoc ausente o incompleto (constantes, clases de
  eventos, tareas programadas, `external_api`), líneas demasiado
  largas y comentarios en línea mal formados. Sin cambios de
  comportamiento.
- `phpdoc`: 3 errores reales corregidos en
  `classes/realtime/polling_transport.php` (bloques `{@inheritdoc}`
  sin `@param`/`@return` explícitos).
- Mustache: añadido contexto de ejemplo a las 8 plantillas; destapó y
  corrigió un bug real en `teacher_settings.mustache`
  (`action=""` al faltar la URL en el contexto de ejemplo).
- **Build AMD real**: corregido el único error real de ESLint
  (`no-loop-func` en `amd/src/dom_selector.js`, sin cambio de
  comportamiento — verificado con pruebas de regresión sobre un DOM
  simulado). Al arreglarlo, `grunt amd` pasó a ejecutarse de verdad en
  este entorno, así que `amd/build/*.min.js` son ahora minificación y
  sourcemaps reales de Rollup, no copias sin minificar de
  `amd/src/*.js` como hasta ahora.
- Sin cambios en el modelo de datos ni en la lógica de negocio;
  PHPUnit sigue en verde (200 tests, 355 assertions).

## 0.23.4 (fix: el recuadro de señalado se filtraba en la reconstrucción del profesor) — 2026-07-31

- **Bug encontrado por el usuario**: el recuadro "El profesor está
  señalando esto" (visible en la pantalla real del alumno) aparecía
  también, en una posición incorrecta, dentro de la propia
  reconstrucción del profesor.
- **Causa**: el recuadro se añade a `document.body` en la página real
  del alumno — como cualquier otro elemento propio del plugin (barra
  de estado, chat flotante). La siguiente foto de página capturada
  (el latido cada 5 s, o cualquier otra mutación real, o el envío
  inmediato tras un clic) lo serializaba tal cual dentro del HTML
  enviado al profesor. Su `position: fixed` con `top`/`left` en
  píxeles absolutos (calculados sobre el viewport real del alumno) no
  significa nada dentro del contenedor reescalado y transformado de la
  reconstrucción, así que aparecía desplazado a una posición sin
  relación con el elemento señalado. La barra de estado y el chat ya
  tenían un problema relacionado (documentado desde antes en
  `docs/architecture.md`), mitigado solo a medias: se excluían de la
  extracción de elementos `fixed`, pero nunca se eliminaban del todo de
  la captura.
- **Arreglo**: nueva función `removeOwnElements()` en `event_capture.js`,
  llamada desde `cleanClone()` en cada foto de página — elimina del
  clon, antes de serializarlo, cualquier elemento cuya clase empiece
  por `local-remotesupport-` (barra de estado, chat, y ahora también el
  recuadro/etiqueta de señalado), con el mismo patrón "solo los más
  externos" que ya usaba `extractFixedHtml()` para no repetir trabajo
  con elementos anidados. Resuelve el problema de raíz para este
  elemento y, de paso, para cualquier otro futuro: nada de la interfaz
  propia del plugin vuelve a aparecer nunca en la reconstrucción,
  correctamente posicionado o no.
- El usuario decidió explícitamente no mostrar además una confirmación
  anclada dentro de la propia reconstrucción del profesor (opción
  considerada y descartada) — el recuadro sigue siendo una señal
  exclusiva para el alumno.
- Puramente cliente, sin cambios de servidor. Los 200 tests PHPUnit no
  se ven afectados.

## 0.23.3 (nuevo: el profesor también puede señalar campos de texto) — 2026-07-31

- **Pedido por el usuario**: tras confirmar que el señalado ya
  funcionaba, observó que los campos de texto (`input[type="text"]`,
  `textarea`, etc.) no se podían señalar — el profesor no veía el
  contorno de "candidato" al pasar el ratón por encima de uno dentro de
  su reconstrucción. Causa: el modo de señalado reutilizaba
  `CLICKABLE_SELECTOR`, la misma lista que ya usaba el resaltado
  `hover` del alumno (pensada para "qué tiene el ratón encima con
  intención de pulsar", no para campos de texto — esos ya tienen su
  propio mecanismo de resaltado `typing`, basado en el foco, no en el
  ratón). Al heredar esa lista sin plantearse si tenía sentido para el
  señalado, los campos de texto quedaron excluidos sin que fuera una
  decisión consciente para este caso.
- Nueva constante `POINTABLE_SELECTOR` en `dom_selector.js`
  (`CLICKABLE_SELECTOR` + campos de texto, reutilizando exactamente el
  mismo `TEXT_FIELD_SELECTOR` que ya usaba `event_capture.js` para el
  resaltado `typing` del alumno, ahora también exportado desde
  `dom_selector.js` como fuente única) y `findPointableAncestor()`,
  usada solo por el modo de señalado del profesor
  (`screen_renderer.js`). Deliberadamente **no** se ha ampliado
  `CLICKABLE_SELECTOR` en sí, para no cambiar el comportamiento ya
  probado del resaltado `hover` del alumno (que sigue siendo solo
  elementos clicables).
- A diferencia del clic remoto o la escritura remota (Fases 5/6, que sí
  necesitaban excluir campos por seguridad), señalar nunca ejecuta nada
  ni revela ningún valor — es puramente visual, así que no hay ninguna
  razón de seguridad para excluir campos de texto aquí. Se excluyen
  contraseña y campos ocultos igualmente, solo por coherencia con el
  resto del `TEXT_FIELD_SELECTOR` compartido, no por ningún riesgo
  nuevo.
- Sin cambios de servidor ni de esquema — puramente JavaScript cliente,
  reutilizando la misma tubería de eventos (`teacher_highlight`) ya
  existente. Los 200 tests PHPUnit no se ven afectados.

## 0.23.2 (fix: el señalado del profesor seguía sin reflejarse en la pantalla del alumno) — 2026-07-31

- El usuario reportó que, tras el fix de la 0.23.1, seguía sin funcionar
  ("cliqué sobre Calificaciones y no se reflejó… ¿a ti te funciona?").
  Como no hay navegador disponible normalmente en este entorno, se montó
  una verificación end-to-end real con Chromium headless (Playwright) —
  curso y usuarios desechables, sesión completa alumno-profesor, clic
  real dentro de la reconstrucción — que efectivamente reprodujo el
  fallo en vivo, dejando dos causas reales distintas, ninguna de ellas
  la que se había arreglado en la 0.23.1 (esa sí era un bug real, pero
  no el único):
- **Causa 1 — un iframe reescalado dentro de un contenedor `overflow:
  hidden` nunca recibe eventos de ratón reales, aunque el navegador
  esté de acuerdo en que es el elemento superior en ese punto.**
  Confirmado con un `page.mouse.move()` de bajo nivel: 0 eventos
  `mousemove` en el propio elemento `<iframe>`, tanto si sus
  `pointer-events` están en `auto` como si se activan dinámicamente por
  JS. Reproducido de forma aislada (sin nada de Moodle) en cuanto se
  replicó la misma estructura real: iframe con su tamaño de layout
  completo (sin escalar) dentro de un envoltorio `position:relative;
  overflow:hidden` dimensionado solo al tamaño *visual* (escalado) —
  exactamente `.local-remotesupport-player-viewport`. El propio
  envoltorio (no escalado) sí recibe los eventos con normalidad.
  **Arreglo**: `screen_renderer.js` escucha `mousemove`/`click` en
  `viewportWrapper`, no en el iframe ni en su `contentDocument`, y sigue
  usando `elementFromPoint()` con las coordenadas deshechas de la
  escala para encontrar el elemento real dentro del iframe — el toggle
  de `pointer-events` del fix anterior (0.23.0) ya no hace falta y se
  ha retirado; `styles.css` mantiene `pointer-events: none` en el
  iframe permanentemente, sin ninguna excepción.
- **Causa 2 — `event.payload` llega como cadena JSON sin analizar, no
  como objeto ya decodificado**, exactamente igual que en el resto de
  tipos de evento (`chat_widget.js` ya hace su propio `JSON.parse()`
  antes de usarlo) — un patrón ya establecido que la nueva rama de
  `teacher_highlight` en `pollIncoming()` pasó por alto, leyendo
  directamente `event.payload.selector`/`.ttlms` de una cadena, lo que
  siempre daba `undefined` y hacía que `applyTeacherPointer()` volviera
  de inmediato sin hacer nada. Arreglado con el mismo `JSON.parse()`
  envuelto en `try`/`catch` que ya usa `chat_widget.js`.
- Ninguno de los dos fallos lo detectaban las pruebas PHPUnit (son
  puramente de JavaScript cliente, sin arnés de pruebas JS en este
  proyecto — ver `docs/limitations.md`) ni se habían detectado sin
  ejecutar el flujo completo en un navegador real; quedan documentados
  en detalle en `docs/decisions.md` para que la próxima vez que se
  añada un consumidor de eventos no se repita ninguno de los dos.
- También se descubrió, y hubo que corregir para poder verificar nada,
  que dos purgas de caché de JavaScript de Moodle (`admin/cli/purge_caches.php`)
  habían quedado pendientes tras desplegar la 0.23.0/0.23.1 — el
  navegador seguía sirviendo JS anterior al fix hasta que se purgó
  explícitamente. Ver `docs/decisions.md` y la entrada correspondiente
  en la memoria del proyecto.
- Verificado en vivo, extremo a extremo, con Chromium headless: el
  recuadro que aparece en la pantalla del alumno coincide en píxeles
  con la posición real del enlace "Calificaciones" que el profesor
  señaló en su reconstrucción.

## 0.23.1 (fix: el señalado del profesor no se reflejaba en la pantalla del alumno) — 2026-07-31

- **Bug encontrado por el usuario nada más probar en vivo**: al usar
  "Señalar elemento", el profesor veía el resaltado azul discontinuo al
  pasar el ratón por su propia reconstrucción, pero nada aparecía nunca
  en la pantalla del alumno.
- **Causa**: el contenido capturado se inyecta en el iframe del
  profesor como el `innerHTML` de un `<div id="local-remotesupport-viewport-content">`
  — un envoltorio sintético que solo existe dentro de esa
  reconstrucción, nunca en la página real del alumno. Cuando el
  elemento que el profesor pulsaba no tenía un `id` propio (el caso
  habitual, según ya advertía `docs/limitations.md` para el resaltado
  `hover`), `buildRobustSelector()` subía por los ancestros hasta
  encontrar ese `id` sintético y lo usaba como ancla del selector
  (`#local-remotesupport-viewport-content > div:nth-of-type(3)`), un
  selector que nunca puede coincidir con nada en el DOM real del
  alumno — así que el señalado nunca se resolvía ni se mostraba,
  siempre, no solo a veces.
- **Arreglo**: `buildRobustSelector()` (en el nuevo `dom_selector.js`,
  ver 0.23.0) ahora reconoce ese `id` sintético y detiene la subida sin
  incluirlo, dejando un selector relativo al contenido de ese
  envoltorio en vez de un ancla falsa. `event_capture.js` resuelve ese
  selector con `findCaptureRoot(mode).querySelector(selector)` (el
  mismo elemento raíz que ya usa para capturar, no `document`
  directamente) en vez de `document.querySelector()` — `Element.querySelector()`
  sigue resolviendo igual un selector absoluto `#id` real, así que esto
  amplía el caso que ya funcionaba (elementos con `id` propio) en vez
  de estrecharlo.
- Fix puramente de JavaScript cliente, sin PHP implicado, así que no
  añade pruebas PHPUnit — no hay arnés de pruebas JavaScript en este
  proyecto (ver `docs/limitations.md`), verificado por lectura del
  código y pendiente de confirmación en vivo por el usuario.

## 0.23.0 (nuevo: el profesor puede señalar un elemento clicable en la pantalla del alumno) — 2026-07-31

- **Pedido por el usuario**: reintroducir, de forma selectiva y activable
  desde la configuración general, una pieza concreta de la Fase 3 original
  (cursor remoto y resaltado) que se había retirado por completo al reducir
  el plugin a solo-visualización (commit `aa58c26`): que el profesor pueda
  señalar un elemento clicable de la pantalla del alumno. El usuario pidió
  explícitamente que la localización se basara en el elemento del DOM, no
  en coordenadas x/y (la imprecisión de posición del cursor ya documentada
  en `docs/decisions.md` no sirve para señalar con precisión un elemento
  concreto), y que el resaltado expirase solo, con la duración configurable
  en segundos.
- Dos ajustes nuevos en la configuración general del plugin:
  `enableteacherpointer` (casilla, desactivado por defecto — es una
  reversión selectiva de una decisión de producto deliberada, no debía
  activarse silenciosamente) y `teacherpointerttlseconds` (duración, 5 s
  por defecto) — cuánto tiempo permanece visible cada señalado antes de
  desaparecer solo.
- Nuevo tipo de evento `teacher_highlight` (profesor → alumno), el primer
  evento en ese sentido desde que se retiró el cursor remoto: payload
  `{selector, ttlms}`. `ttlms` nunca lo decide el cliente del profesor —
  `event_manager::record_event()` lo sobrescribe siempre a partir del
  ajuste `teacherpointerttlseconds` vigente en ese momento, para que un
  cliente modificado no pueda hacer que su propio señalado dure más de lo
  que el administrador permite. `selector` reutiliza el mismo límite de
  longitud y el mismo tratamiento que los selectores `hover`/`typing` de
  `cursor` (nunca interpretado como HTML, solo argumento de
  `querySelector()`).
- Autorización en dos capas independientes, igual que el resto del
  transporte: `polling_transport::push_event()` exige rol profesor **y**
  el ajuste `enableteacherpointer` activo (si no, rechaza aunque el
  profesor lo intente); `event_player.js` ni siquiera crea el botón
  "Señalar elemento" si el ajuste está desactivado. Suelo de frecuencia
  añadido en `rate_limiter` (0.2 s), igual que otros eventos discretos
  (`student_click`, `chat_message`).
- Selección del elemento en el lado del profesor: nuevo módulo compartido
  `amd/src/dom_selector.js` (extraído de `event_capture.js`, sin cambiar su
  comportamiento) con `buildRobustSelector()` y `CLICKABLE_SELECTOR` — el
  mismo algoritmo de selector robusto y la misma noción de "clicable" que
  ya usaba el resaltado `hover` del alumno, ahora usados también por
  `screen_renderer.js` para que el profesor "recoja" un elemento dentro de
  su propia reconstrucción (`startPicking()`/`stopPicking()`): al pasar el
  ratón por encima de un elemento clicable dentro del iframe reconstruido
  se resalta como candidato (contorno azul discontinuo), y al hacer clic
  se calcula su selector y se envía como `teacher_highlight`.
- **Hallazgo durante la implementación**: `styles.css` ya fijaba
  `pointer-events: none` en el iframe de reconstrucción del profesor,
  deliberadamente, "para que ningún elemento capturado pueda reaccionar a
  un clic" — lo cual habría impedido también que el modo de señalado
  recibiera los eventos de ratón. Solución: `startPicking()`/`stopPicking()`
  activan y desactivan `pointer-events` en el iframe solo mientras dura el
  modo de señalado, dejando la política por defecto intacta el resto del
  tiempo.
- Render en el alumno: `event_capture.js` resuelve el selector recibido
  contra el DOM real (no la reconstrucción) y dibuja un recuadro
  superpuesto (`position: fixed`, `pointer-events: none`) con una etiqueta
  ("El profesor está señalando esto"), reposicionándose en scroll/resize
  mientras esté activo y desapareciendo solo tras `ttlms` — igual que el
  resto de marcas visuales del plugin, un selector que no resuelve a nada
  simplemente no muestra nada, nunca es un error.
- Deliberadamente fuera de alcance (para no reabrir lo que se quitó en
  `aa58c26`): no se ejecuta ningún clic, no hay niveles de permiso por
  sesión, no se guarda `teacher_highlight` en la grabación permanente de
  sesión (`track_manager::TRACKED_EVENT_TYPES` sin cambios) — es
  puramente una señal visual en tiempo real, simétrica al resaltado
  `hover` existente pero en sentido contrario.
- Pruebas PHPUnit nuevas en `event_manager_test.php` (tipo aceptado,
  selector obligatorio, truncado, ttl siempre tomado de la configuración
  incluso si el cliente envía uno propio, límite de frecuencia) y
  `polling_transport_test.php` (rechazado con el ajuste desactivado,
  aceptado solo para el profesor con el ajuste activado, rechazado para
  el alumno aunque esté activado, no se graba en `local_remotesupport_track`).

## 0.22.0 (nuevo: el profesor puede eliminar sesiones de su historial) — 2026-07-30

- **Pedido por el usuario**: en `sessionhistory.php`, poder eliminar
  entradas del historial de sesiones pasadas, del modo habitual de
  Moodle: casillas de selección por fila y un icono de borrado con
  confirmación. También pidió una capacidad específica para poder
  revocar ese permiso de borrado por separado, concedida a profesores
  por defecto.
- Nueva capacidad `local/remotesupport:deletesessionhistory`
  (contexto de curso, `CAP_ALLOW` para `teacher`/`editingteacher` por
  defecto) — un administrador puede revocarla sin tocar
  `provideassistance` ni el resto de capacidades del plugin.
- `permission_manager::can_delete_session_history()` reutiliza
  exactamente la misma regla que ya gobernaba quién puede reproducir
  una sesión (`can_replay_session()`): el profesor asignado a esa
  sesión, si sigue teniendo la capacidad en el curso, o cualquiera con
  `managesessions` como override. Nadie puede eliminar una sesión que
  no podría reproducir.
- `session_manager::delete_sessions()` revalida propiedad/capacidad y
  que la sesión esté cerrada por cada id (mismo patrón defensivo que
  `close_session()`, no confía en que el llamador ya lo comprobó), y
  es todo-o-nada: si cualquier id del lote falla la validación, no se
  borra ninguno. El borrado reutiliza exactamente el mismo purgado que
  ya usaba la baja por privacidad (grabación de pantalla en
  `local_remotesupport_track` y eventos en `local_remotesupport_event`),
  y queda auditado con un nuevo evento `session_deleted`.
- Interfaz sin JavaScript nuevo: `session_history_table` gana una
  columna de casillas (`ids[]`), `sessionhistory.php` envuelve la
  tabla en un formulario POST con un botón "Eliminar seleccionadas",
  y la nueva `sessiondelete.php` implementa el flujo clásico de
  confirmación en dos pasos de Moodle (`$OUTPUT->confirm()`) antes de
  borrar nada.
- 11 nuevas pruebas PHPUnit (capacidad por defecto, revocación de la
  capacidad, profesor distinto rechazado, sesión aún abierta
  rechazada, override de gestor, borrado en cascada de la grabación,
  todo-o-nada en un lote mixto). 189 tests pasando (antes 178).
  Verificado también en vivo (curso/usuarios desechables): la casilla
  y el borrado en cascada funcionan igual que en las pruebas.
- **Bug encontrado por el usuario nada más probarlo en vivo**: al
  pulsar "Eliminar seleccionadas" aparecía el error de Moodle "clean()
  can not process arrays, please use clean_array() instead".
  `sessiondelete.php` leía el mismo parámetro `ids` con
  `optional_param_array()` (array, primer paso) y con `optional_param(
  ..., PARAM_SEQUENCE)` (cadena, paso de confirmación) sin condición —
  cuando llegaba como array real, `clean_param()` lo rechazaba. Arreglado
  usando nombres de parámetro distintos para cada forma (`ids` para el
  array, `idlist` para la secuencia), eliminando la ambigüedad en vez
  de intentar detectarla. Ver `docs/decisions.md` para el detalle
  completo y cómo se verificó.

## 0.21.0 (nuevo: el campo de texto en el que escribe el alumno se remarca en la reconstrucción del profesor) — 2026-07-30

- **Pedido por el usuario**: cuando el alumno teclea en un campo de
  texto, el profesor debe poder ver cuál es ese campo en su
  reconstrucción, igual que ya podía ver qué elemento clicable estaba
  señalando el ratón del alumno.
- Reutiliza el mecanismo existente de `hover` (Fase 3): `event_capture.js`
  añade listeners `focusin`/`focusout` sobre campos de texto
  (`input`/`textarea`, excluidos `password` y `hidden`) y manda un
  nuevo campo opcional `typing` (selector CSS del campo, nunca su
  valor) en el evento `cursor` existente — no hace falta un tipo de
  evento nuevo. `event_manager.php` valida/trunca `typing` igual que
  ya hacía con `hover`. `screen_renderer.js` añade
  `applyTypingHighlight()`, con su propia clase CSS (color distinto al
  resaltado de `hover`, para que el profesor distinga "el alumno está
  señalando esto" de "el alumno está escribiendo aquí"), sin mover el
  punto del cursor — el foco de teclado no tiene una coordenada de
  ratón asociada. Se propaga tanto en la vista en vivo
  (`event_player.js`) como en la reproducción grabada
  (`session_replay.js`), ya que ambas reutilizan el mismo renderer.
- Coherente con la regla ya existente de "nunca se envía el valor de
  un campo de formulario, solo su identidad" — no cambia nada del
  saneamiento ni de la política de privacidad del plugin.
- 3 nuevas pruebas PHPUnit (selector aceptado, truncado al límite,
  valor no-string descartado — mismas pruebas que ya existían para
  `hover`). 178 tests pasando (antes 175).

## 0.20.1 (mejora: el enlace "Ver chat" del historial aparece deshabilitado si la sesión no tiene chat) — 2026-07-29

- **Propuesto por el usuario**: revisar la decisión original de
  mostrar siempre el enlace "Ver chat" en el historial de sesiones,
  aunque a veces llevara a una transcripción vacía — muchas sesiones
  reales no tienen ningún mensaje.
- `session_history_table::query_db()` ahora hace, tras la consulta
  paginada estándar, una única consulta adicional acotada a los ids de
  la página actual (`IN (...)`) para saber cuáles tienen chat — no una
  subconsulta por fila, la preocupación de rendimiento que motivó la
  decisión original. `col_chatlink()` renderiza un `<button disabled>`
  (mismo patrón ya usado para "No hay personal de soporte disponible")
  en vez del enlace cuando no hay chat que ver.
- Solo afecta a "Ver chat" — la columna "#" (reproducción) sigue
  mostrando siempre el enlace, ya que toda sesión activa graba al
  menos una foto de pantalla, a diferencia del chat, que es
  verdaderamente opcional.

## 0.20.0 (nuevo: el alumno vuelve a la página exacta donde pidió asistencia, sin doble confirmación) — 2026-07-29

- **Pedido por el usuario**: al aceptar, el alumno tenía que confirmar
  dos veces ("Entrar en la sesión" y luego "Ir al curso" en una página
  aparte), y el segundo clic llevaba siempre a la portada del curso,
  nunca a la página concreta donde estaba.
- Nueva columna `returnurl` en `local_remotesupport_session`, guardada
  como ruta local (nunca una URL completa, sin posibilidad de
  redirigir a un dominio externo) en el instante en que el alumno pide
  asistencia — captada en los enlaces del menú del curso y del botón
  flotante, hilada a través del formulario de solicitud (clásico y
  AJAX) hasta `session_manager::create_request()`.
- `session.php` ya no muestra ninguna página de confirmación al
  alumno: redirige directamente a esa página guardada, o a la portada
  del curso si no hay ninguna. Se elimina `session_active.mustache` y
  las dos cadenas de idioma que solo ella usaba — el botón "Finalizar"
  que ofrecía sigue disponible en la barra de estado persistente de la
  página de destino.
- Nuevas pruebas PHPUnit (guardado, valor por defecto, descarte de
  URLs demasiado largas, hilado a través del endpoint AJAX). 175 tests
  pasando.

## 0.19.1 (mejora: mensaje explicativo de qué es la asistencia remota en el formulario de solicitud) — 2026-07-29

- **Pedido por el usuario**: el formulario "Solicitar asistencia" no
  explicaba al alumno qué implica realmente — solo mostraba el campo
  de motivo y el botón.
- Nuevo párrafo explicativo (`info_whatisassistance`), acordado con el
  usuario antes de implementarlo, encima del campo "Motivo": qué puede
  ver el profesor (la página de Moodle, no el resto de la pantalla),
  que solo observa (no actúa por el alumno), que hay chat, y que el
  alumno puede finalizar cuando quiera. Solo visible cuando el
  formulario de solicitud realmente se muestra.

## 0.19.0 (nuevo: aviso explícito de despedida al alumno cuando el profesor finaliza la sesión) — 2026-07-29

- **Pedido por el usuario**: cuando el profesor finaliza la sesión, la
  barra "Asistencia activa" del alumno desaparecía sin más aviso —
  asimetría respecto al aviso que ya recibía el profesor cuando era el
  alumno quien la finalizaba.
- Al detectar que la sesión ya no está activa, `event_capture.js`
  reutiliza la propia barra de estado (en vez de un overlay grande,
  ya que el alumno puede estar en cualquier página de Moodle, no en
  una vista dedicada): sustituye su contenido por un mensaje de
  despedida y un botón "Cerrar", que se queda visible hasta que el
  alumno lo descarta — deliberadamente sin autoocultarse, para
  garantizar que se vea.
- Nuevas cadenas de idioma (`sessionendedbyteacher`, `button_close`).
  Mismo matiz de imprecisión ya aceptado para el aviso equivalente del
  profesor: no se puede saber con certeza quién cerró la sesión.

## 0.18.4 (mejora: el punto del cursor se centra en el elemento resaltado, más fiable que la coordenada bruta) — 2026-07-29

- **Propuesto por el usuario**: puesto que un elemento resaltado
  significa, por construcción, que el ratón real está sobre él, si el
  punto de cursor no coincide visualmente con la zona resaltada hay
  que mover el punto hasta que coincida — la identidad del elemento es
  más fiable que la coordenada.
- `applyHoverHighlight()`, al encontrar el elemento, reposiciona ahora
  el punto en su centro (`getBoundingClientRect()` dentro del propio
  documento del `iframe`, mismo sistema de coordenadas que ya usaba el
  punto, sin conversión nueva), sobrescribiendo la posición que la
  coordenada bruta le había dado un instante antes. Sin resaltado, el
  punto sigue con su comportamiento de siempre.
- `lastCursor` se actualiza también al centro, para que un
  redimensionado o cambio a pantalla completa posterior respete el
  punto ya centrado en vez de volver a la coordenada bruta.

## 0.18.3 (fix: el resaltado ya no desaparece con cada actualización periódica de la página) — 2026-07-29

- **Corregido, reportado por el usuario**: al situar el cursor del
  alumno sobre una zona clicable, el resaltado aparecía en la
  reconstrucción pero, a los pocos segundos, desaparecía por sí solo
  aunque el alumno no hubiera movido el ratón.
- Causa: `renderPage()` reconstruye `iframe.srcdoc` por completo en
  cada evento `page`, no solo al navegar de verdad — también en cada
  latido (cada 5 s) y tras cada clic. Eso recarga el `iframe` entero;
  el punto del cursor no se ve afectado (vive fuera del `iframe`), pero
  el resaltado sí, porque es una clase CSS aplicada dentro del
  documento del `iframe`, que desaparece con la recarga.
- `applyHoverHighlight()` recuerda ahora el último selector aplicado
  (`lastHoverSelector`); `renderPage()` lo reaplica en `iframe.onload`
  tras cada reconstrucción del documento, salvo que sea una navegación
  real (en cuyo caso ya se había puesto a `null` correctamente).

## 0.18.2 (fix: el resaltado del elemento bajo el cursor ahora encuentra el elemento en páginas con anidamiento profundo) — 2026-07-29

- **Corregido, reportado por el usuario**: tras el fix de precisión
  del cursor, el resaltado del elemento clicable bajo el ratón seguía
  sin funcionar. Causa: el respaldo estructural del selector
  (`buildRobustSelector()`, usado cuando el elemento no tiene `id` —
  el caso más común en enlaces/botones de Moodle) se cortaba a 5
  niveles de profundidad, muy por debajo de lo habitual en el HTML
  real de Moodle (10-15+ niveles con Bootstrap). El resultado era una
  cadena de selector sin anclar a ningún punto real, que en la
  práctica casi nunca coincidía con nada al buscarla en la
  reconstrucción — no impreciso, prácticamente inoperante.
- `HOVER_SELECTOR_MAX_DEPTH` sube de 5 a 30 (una red de seguridad
  contra un bucle patológico, no un límite pensado para alcanzarse) —
  la ruta ahora sube de verdad hasta un `id` o hasta la raíz del
  documento. `MAX_HOVER_SELECTOR_LENGTH` en el servidor sube de 300 a
  1500 caracteres en proporción, para no truncar a mitad de cadena las
  rutas más largas que esto puede producir.

## 0.18.1 (fix: la última posición del cursor ya no se pierde al parar el ratón bruscamente) — 2026-07-29

- **Corregido, reportado por el usuario**: moviendo el ratón del
  alumno rápido y parándolo de golpe, a veces el cursor (y el
  resaltado del elemento bajo él) se quedaba "perdido" en una posición
  anterior a la real, sin recuperarse nunca.
- Causa: `throttle()` (compartida por los eventos `cursor` y `scroll`)
  solo tenía flanco de subida — una llamada que caía dentro de la
  ventana de espera se descartaba sin más, en vez de posponerse. Si la
  posición final de una ráfaga de movimiento caía en ese hueco, y el
  ratón se paraba justo después, no había ningún `mousemove` posterior
  que la reintentara: esa posición no llegaba a mandarse nunca.
- `throttle()` ahora también dispara una única llamada pendiente al
  terminar la ventana de espera cuando descarta una — sin ningún
  cambio en `sendCursor()`, que ya lee la posición/resaltado más
  recientes de su propio cierre en el momento de ejecutarse. Arregla
  cursor y resaltado a la vez (viajan en el mismo evento), y de paso
  el mismo fallo latente en `scroll`.

## 0.18.0 (nuevo: la reconstrucción resalta el elemento clicable que el alumno está señalando) — 2026-07-29

- **Propuesto por el usuario** para compensar la imprecisión geométrica
  del cursor que persistía tras las mejoras anteriores: en vez de
  depender solo de que el punto caiga en el sitio exacto, identificar
  qué elemento clicable tiene el alumno bajo el ratón y resaltar ese
  mismo elemento en la reconstrucción — una coincidencia por identidad
  de elemento, no por coordenadas, que no hereda ninguna de las fuentes
  de imprecisión ya conocidas.
- `event_capture.js` detecta, en cada muestra de cursor, el ancestro
  clicable más cercano al ratón (enlaces, botones, campos, `role`
  interactivos...) y construye un selector robusto para él —
  preferentemente su `id`, o si no tiene, una ruta estructural corta —
  mismo orden de robustez que ya proponía el documento base para el
  cursor remoto retirado. Viaja como campo `hover` del mismo evento
  `cursor` ya existente, sin pipeline nuevo: mismo throttling, mismo
  límite de frecuencia, misma grabación permanente (disponible también
  en la reproducción).
- El servidor acota `hover` a 300 caracteres si es una cadena, o lo
  descarta si no lo es — nunca rechaza el evento `cursor` completo por
  esto, es auxiliar a la posición. `screen_renderer.js` busca ese
  selector en su propio DOM ya reconstruido (`querySelector()` dentro
  del `iframe` aislado, con su propio `try`/`catch`) y añade un
  contorno visual al elemento encontrado, quitándolo del anterior.
- Riesgo residual aceptado: sin `id`, el respaldo estructural podría
  señalar un elemento distinto si el orden de sus hermanos cambió desde
  la última foto de página — nunca una acción, solo una marca visual
  potencialmente equivocada. Ver `docs/limitations.md`.
- Nuevas pruebas PHPUnit (selector aceptado, truncado, y descartado si
  no es una cadena). 171 tests pasando.

## 0.17.4 (fix: el punto del cursor ya no desaparece cuando el alumno deja de mover el ratón) — 2026-07-29

- **Corregido, reportado por el usuario**: regresión introducida por la
  mejora de precisión anterior (0.17.3). `renderPage()` ocultaba el
  punto del cursor en cada evento `page`, no solo en una navegación
  real; al bajar el latido a 5 s y reenviar una foto tras cada clic, el
  punto pasó a esconderse cada vez que el alumno dejaba el ratón quieto
  ese intervalo, cada vez más corto.
- `screen_renderer.js` ahora compara la URL de la página entrante con
  la última renderizada y solo oculta el punto si de verdad cambia —
  arregla la vista en directo y la reproducción a la vez, ambas
  comparten la misma función.

## 0.17.3 (mejora: precisión de la reconstrucción — CSS inline, ancho sin scrollbar, resincronización tras cada clic) — 2026-07-29

- **Pedido por el usuario tras confirmar que la precisión seguía sin
  ser buena** pese al fix de los elementos fijos, y aceptar
  explícitamente apuntar a "lo bastante cerca" en vez de precisión
  total (ver `docs/decisions.md` para por qué la precisión total no es
  un objetivo alcanzable con esta arquitectura).
- **CSS inline capturado.** `collectInlineStyleText()` recoge ahora el
  texto de las hojas `<style>` sin `href` (antes solo se capturaban
  las cargadas por `<link>`), enviado como `payload.inlineCss` y
  saneado en el servidor con una limpieza basada en texto (elimina
  `@import` y cualquier `url(...)`, PHP no tiene un parser de CSS
  real) — `screen_renderer.js` lo inyecta como `<style>` adicional en
  el `iframe`, con el mismo cuidado de escapado que ya se aplicaba a
  otras URLs para evitar que un `</style` en el payload rompiera la
  etiqueta.
- **Ancho del `iframe` corregido para excluir la barra de scroll.**
  `viewport.width`/`height` pasan de `window.innerWidth`/`innerHeight`
  (incluye la barra de scroll) a
  `document.documentElement.clientWidth`/`clientHeight` (la excluye,
  igual que el `iframe`, que nunca tiene barra propia) — hasta ahora
  el contenido se maquetaba unos 15-17px más ancho de lo que el alumno
  ve realmente.
- **Resincronización más frecuente.** `PAGE_HEARTBEAT_MS` baja de
  10 000 a 5 000 ms, y cada clic del alumno dispara ahora una foto de
  página inmediata (sin pasar por el debounce habitual de 1,5 s),
  acortando la ventana en la que la reconstrucción puede estar
  desactualizada respecto a la página real.
- Nueva prueba PHPUnit (limpieza de `inlineCss`). 168 tests pasando.

## 0.17.2 (el punto del cursor se desplaza de forma continua en vez de a saltos) — 2026-07-29

- **Pedido por el usuario**: que el punto que marca la posición del
  cursor del alumno se deslice entre posiciones en vez de saltar
  instantáneamente de una a otra.
- Cambio puramente CSS: una `transition` en
  `.local-remotesupport-student-cursor` (`styles.css`). Sin cambios en
  `screen_renderer.js` ni en los datos transmitidos — sigue siendo una
  aproximación en línea recta entre las posiciones realmente
  muestreadas, no una reconstrucción del trayecto real. No introduce
  ningún deslizamiento espurio al cambiar de página (las transiciones
  CSS no se ejecutan sobre cambios ocurridos mientras el elemento está
  oculto).

## 0.17.1 (fix: precisión del cursor/clic — los elementos fijos ya no se desplazan con el scroll) — 2026-07-29

- **Corregido, reportado por el usuario**: la posición del cursor/clic
  en la reconstrucción tenía "error excesivo". Causa confirmada contra
  el tema Boost real: la barra de navegación es `position: fixed` por
  diseño, pero en modo de captura `fullpage` perdía ese comportamiento
  dentro de la reconstrucción en cuanto el alumno hacía scroll (un
  `transform` usado para simular el scroll convierte en su contenedor
  de referencia a cualquier descendiente `fixed`) — el contenido
  quedaba desplazado respecto al real por, aproximadamente, la altura
  de la barra, y la marca del cursor/clic (correctamente calculada)
  quedaba entonces sobre contenido mal alineado.
- `event_capture.js` ahora detecta los elementos `position: fixed` del
  contenido capturado (excluyendo la propia interfaz del plugin) y los
  extrae a un campo de payload nuevo (`fixed`), generalizando la misma
  técnica que ya usaba el modal (Fase 4) para lo mismo. El servidor los
  sanea igual que el resto del HTML capturado; `screen_renderer.js` los
  renderiza fuera del contenedor con scroll simulado, así que vuelven a
  comportarse como fijos de verdad dentro del `iframe`.
- Deliberadamente solo `position: fixed`, no `sticky` — ver
  `docs/decisions.md` para el porqué. `docs/limitations.md` documenta
  el caso `sticky` restante.
- Nueva prueba PHPUnit (saneamiento del campo `fixed`). 167 tests
  pasando.

## 0.17.0 (nuevo: marca visual y sonido cuando el alumno hace clic) — 2026-07-29

- **Pedido por el usuario**, como ampliación directa de la posición del
  cursor: que la reconstrucción marque de algún modo visible cuándo y
  dónde hace clic el alumno, con sonido opcional, también en la
  reproducción de sesiones grabadas.
- `event_capture.js` envía la posición de cada clic (`clientX`/`clientY`)
  como nuevo tipo de evento `student_click` — sin throttling propio (un
  clic ya es un evento discreto e infrecuente), excluyendo los clics
  sobre la propia interfaz inyectada del plugin (barra de estado, chat).
  Se almacena permanentemente junto al resto de la grabación, igual que
  `cursor`.
- `screen_renderer.js` gana `showClickMark()` (un "ripple" que se
  desvanece en 0,6 s) y `playClickSound()` (un "tick" sintetizado con
  la Web Audio API — sin fichero de audio empaquetado), compartidas por
  la vista en vivo y la reproducción.
- Sonido controlable en dos niveles, tal como se pidió: ajuste general
  en Ajustes del sitio (`local_remotesupport/clicksound`, activado por
  defecto) más un botón de silenciar/activar en la barra del visor —
  tanto en directo como en la reproducción — que solo cambia esa
  visualización concreta, sin persistirse.
- En la reproducción, la marca/el sonido solo se disparan avanzando de
  forma natural, nunca al saltar con la barra de progreso (un clic es
  un efecto momentáneo, no un estado que "recuperar" al saltar). En
  directo, se suprimen en el primerísimo sondeo de una vista recién
  abierta, igual que ya hace `chat_widget.js` con su notificación de
  "no leído".
- Nuevas pruebas PHPUnit (tipo aceptado, coordenadas obligatorias,
  límite de frecuencia, autorización por rol, grabación permanente).
  166 tests pasando.

## 0.16.0 (nuevo: el profesor ve la posición del cursor del alumno) — 2026-07-29

- **Pedido por el usuario**: que la reconstrucción marque dónde tiene el
  ratón el alumno, tanto en directo como en la reproducción de sesiones
  grabadas — un punto puramente informativo, no un cursor que el
  profesor mueve (esa capacidad se retiró en `aa58c26`).
- `event_capture.js` envía la posición del ratón (`clientX`/`clientY`)
  como nuevo tipo de evento `cursor`, atado al propio evento del
  navegador `mousemove` (no a un temporizador): un alumno inactivo no
  genera ningún dato, con throttling adicional según la nueva tasa de
  muestreo configurable en Ajustes del sitio
  (`local_remotesupport/cursorsamplems`: 200/500/1000/2000 ms).
- Se almacena permanentemente en `local_remotesupport_track`, junto a
  `page`/`scroll`/`chat_message` — una excepción deliberada y
  explícitamente pedida por el usuario a la guía del documento base de
  no grabar cada movimiento del ratón; ver `docs/decisions.md`. Reutiliza
  la retención y el borrado por privacidad ya existentes, sin mecanismo
  nuevo.
- `screen_renderer.js` gana `applyCursorPosition()`/`hideCursor()`,
  compartido por la vista en vivo (`event_player.js`) y la reproducción
  (`session_replay.js`); el punto se oculta al cambiar de página en vez
  de arrastrar una posición obsoleta.
- Nuevas pruebas PHPUnit (tipo aceptado, coordenadas obligatorias, límite
  de frecuencia, autorización por rol, grabación permanente). 162 tests
  pasando.

## 0.15.6 (fix: la barra inferior del alumno ya no oculta el pie fijo de Moodle) — 2026-07-29

- **Corregido, reportado por el usuario**: la barra persistente
  "Asistencia activa..." (`position: fixed` al pie de la ventana) tapaba
  el pie fijo propio de Moodle (`.stickyfooter`, el bloque con los
  botones "Guardar cambios"/"Cancelar" de formularios largos como la
  edición del perfil), que también es `position: fixed` al pie de la
  ventana. Un primer intento (0.15.5, nunca llegó a publicarse en el
  changelog) añadía `padding-bottom` a `<body>` para reservar espacio,
  pero no funcionaba: el pie de Moodle está igualmente fuera del flujo
  del documento, así que el padding no lo desplaza.
- `event_capture.js` ahora vigila la clase `hasstickyfooter` de
  `<body>` (así es como `theme_boost/sticky-footer.js` marca el pie de
  Moodle como visible u oculto, incluso dinámicamente al hacer scroll
  en pantallas estrechas) y, mientras esté visible, desplaza la propia
  barra (`bottom`) por encima de él una altura igual a la real del pie
  de Moodle.
- Solo JavaScript del lado del alumno; sin cambios de servidor ni de
  pruebas PHPUnit (no hay suite JS en este proyecto, ver
  `docs/limitations.md`).

## 0.15.4 (el botón de reproducir de la reproducción es ahora un icono) — 2026-07-29

- **Pedido por el usuario**: que el botón de reproducir/pausar de
  `sessionreplay.php` sea un icono en vez de texto, como en cualquier
  reproductor de vídeo.
- Iconos `fa-play`/`fa-pause` de Font Awesome (ya disponibles en el
  núcleo de Moodle, sin dependencias nuevas), alternando según el
  estado de reproducción.
- **Accesibilidad**: el icono lleva `aria-hidden="true"` (es puramente
  decorativo) y el propio botón lleva un `aria-label` con el texto
  "Reproducir"/"Pausar" localizado, actualizado en cada cambio de
  estado — quien use un lector de pantalla sigue teniendo una etiqueta
  clara, aunque visualmente ya no haya texto.
- El resto de botones del plugin (pantalla completa, aceptar,
  finalizar) se mantienen con texto, sin cambios — esta ampliación es
  deliberadamente solo para el control más "de reproductor de vídeo" de
  toda la interfaz.

## 0.15.3 (fix: el selector de velocidad de la reproducción era demasiado ancho) — 2026-07-29

- **Corregido, reportado por el usuario**: en `sessionreplay.php`, el
  desplegable de velocidad (1x/2x/4x/8x) ocupaba todo el ancho
  disponible en vez de ajustarse al texto de sus opciones. Causa: la
  clase `.custom-select` de Bootstrap fija `width: 100%`, con la misma
  especificidad CSS (una sola clase) que la regla propia del plugin, así
  que cuál ganaba dependía del orden de carga de las hojas de estilo.
- Corregido con un selector más específico (`select.local-remotesupport-replay-speed`,
  combinando etiqueta y clase), que gana siempre a `.custom-select`
  independientemente del orden — sin recurrir a `!important`.
- Solo CSS; sin cambios de servidor ni de JavaScript.

## 0.15.2 (fix: la vista de solo chat solo mostraba el principio de la conversación) — 2026-07-29

- **Corregido, reportado por el usuario**: "no se ve el chat completo,
  solo una parte del inicio" en `sessionchat.php`. Causa: la plantilla
  reutilizaba la clase CSS pensada para el panel flotante/lateral del
  chat (`max-height: 240px; overflow-y: auto`), que en esta página es el
  contenido principal, sin ningún JavaScript que la desplazara hasta el
  final — se veían solo los primeros mensajes, recortados dentro de una
  caja pequeña.
- Nueva clase `local-remotesupport-session-chat-full`, sin límite de
  altura propio: la conversación completa fluye ahora con el scroll
  normal de la página.
- Sin cambios de servidor; verificado por HTTP autenticado contra una
  sesión real de 7 mensajes, confirmando que los 7 aparecen ya sin
  recortar.

## 0.15.1 (nuevo: vista de solo chat en el historial de sesiones) — 2026-07-29

- **Pedido por el usuario**: un enlace en el historial de sesiones para
  ver "sólo el chat completo" de una sesión, sin entrar en la
  reproducción completa con su reproductor de pantalla.
- Nueva columna "Chat" en `session_history_table.php`, junto a la "#" de
  reproducción, con un enlace "Ver chat" por fila bajo la misma
  autorización (`replaysession`) que la reproducción completa.
- Nueva página `sessionchat.php` (con su plantilla
  `session_chat.mustache`): la transcripción completa de la conversación
  de una sesión cerrada, renderizada enteramente en PHP/Mustache, sin
  JavaScript ni endpoint AJAX nuevo — más ligera que la reproducción
  completa al no tener que descargar ni reconstruir la pantalla.
- Nuevo método `track_manager::get_chat_for_session()`, con su prueba
  PHPUnit.
- **Bug real corregido, encontrado en pruebas de humo (no por
  PHPUnit)**: la columna "Chat" (un enlace calculado, no una columna
  SQL real) aparecía marcada como ordenable; pulsar su cabecera habría
  producido un error de base de datos. Corregido con
  `$this->no_sorting('chatlink')`.
- 158 pruebas PHPUnit en total, todas en verde. Verificado además con
  una comprobación de humo por HTTP autenticado, incluida una sesión
  real del sitio de pruebas con 7 mensajes de chat.
- Documentación actualizada: `docs/decisions.md`, `docs/architecture.md`,
  `docs/security.md`, `docs/limitations.md`, `docs/testing.md`,
  `README.md`.

## 0.15.0 (nueva funcionalidad: reproducción de sesiones grabadas) — 2026-07-29

- **Pedido por el usuario**: que el profesor pueda "reproducir" sesiones
  de soporte pasadas desde el historial (`sessionhistory.php`), pulsando
  una columna "#" nueva, viendo "todas las pantallas mandadas por el
  alumno, junto con el chat, sincronizado".
- **Revisión consciente de una decisión anterior**: el chat solo
  persistía mientras la sesión estaba activa, no de forma permanente.
  Confirmado con el usuario, ahora `chat_message` se graba también en
  `local_remotesupport_track` (junto a `page`/`scroll`), con las mismas
  políticas de retención/borrado ya acordadas. Sesiones cerradas antes de
  este cambio no tienen chat que reproducir, solo pantalla.
- Columna nueva `sourceuserid` en `local_remotesupport_track` (opcional,
  vía `db/upgrade.php`), necesaria para saber quién envió cada mensaje de
  chat al reproducirlo; filas anteriores quedan con el valor `null`.
- **Capacidad nueva `local/remotesupport:replaysession`**, separada de
  `viewsessionhistory`: reproducir el contenido completo grabado es más
  sensible que ver solo los metadatos del listado.
- Nueva página `sessionreplay.php` (con su plantilla
  `session_replay.mustache`) y endpoint AJAX
  `local_remotesupport_get_session_track`, que descarga de una sola vez
  todo lo grabado de una sesión cerrada (no es un sondeo progresivo,
  como sí lo es la vista en vivo).
- Nuevo módulo AMD `session_replay.js`: reproduce la pantalla y el chat
  con controles de reproducir/pausar, velocidad (1x/2x/4x/8x) y una
  barra de progreso que permite saltar a cualquier punto sin tener que
  reproducir desde el principio.
- La reconstrucción de pantalla (iframe sandbox, simulación de scroll por
  CSS, `srcdoc`) se extrajo de `event_player.js` a un módulo compartido
  nuevo, `screen_renderer.js`, usado ahora tanto por la vista en vivo
  como por la reproducción.
- Columna "#" en `session_history_table.php`: el id real de la sesión
  (no un número de fila), como enlace a su reproducción cuando el
  profesor puede reproducirla.
- 14 pruebas PHPUnit nuevas/actualizadas (`permission_manager_test.php`,
  `track_manager_test.php`, `polling_transport_test.php`,
  `external_api_test.php`), 157 en total, todas en verde. Verificado
  además con una comprobación de humo por HTTP autenticado contra datos
  reales del sitio de pruebas (sesión con 76 eventos grabados antes de
  esta funcionalidad, reproducida sin errores).
- Documentación actualizada: `docs/architecture.md`, `docs/decisions.md`,
  `docs/security.md`, `docs/limitations.md`, `docs/testing.md`,
  `README.md`.

## 0.14.4 (fix: el título del historial aparecía duplicado) — 2026-07-29

- **Corregido, reportado por el usuario**: "Historial de sesiones de
  asistencia" aparecía dos veces en `sessionhistory.php`. Causa:
  `$PAGE->set_heading()` ya hace que el tema renderice el título como
  `<h1>` dentro de `$OUTPUT->header()`; se añadió además una llamada
  redundante a `$OUTPUT->heading()` justo después, duplicándolo. No
  tenía relación con la paginación en sí (pasaba en toda carga de la
  página) — `view.php`/`teachersettings.php` ya seguían el patrón
  correcto (solo `set_heading()` + `header()`, sin llamada adicional) y
  no la tenían.
- Eliminada la llamada redundante a `$OUTPUT->heading()`.
- Sin cambios de servidor; sin pruebas nuevas (detalle de plantilla de
  página).

## 0.14.3 (selector de elementos por página en el historial) — 2026-07-29

- **Pedido por el usuario**: "paginado tipo Moodle, con posibilidad de
  elegir número de elementos por página" en el historial de sesiones.
  El paginado (siguiente/anterior) ya lo daba `table_sql` de fábrica;
  faltaba el propio selector de cuántas filas mostrar.
- Investigado el núcleo real de Moodle antes de construir nada propio:
  no hay un componente de "tabla + selector" listo para usar de un solo
  golpe, pero sí un patrón que se repite (`tool_dataprivacy`, el
  informe de calificador) — `\core\output\single_select` (un
  desplegable que se autoenvía por GET) combinado con una preferencia
  de usuario para recordar la elección. Se replicó ese patrón
  exactamente en vez de inventar un desplegable a mano.
- Opciones 10/20/50/100 filas por página (sin "Todas" — no se pidió, y
  con un historial que crece sin límite en el tiempo iría en contra del
  propio motivo de paginar). La elección se recuerda entre visitas
  (`session_history_table::PREF_PERPAGE`, nueva constante), no solo
  durante la sesión de navegador actual.
- Nueva preferencia de usuario declarada y exportada en
  `classes/privacy/provider.php`, igual que la preferencia ya existente
  del profesor (`teacher_settings::PREF_SUPPORT_ENABLED`) — toda
  preferencia que este plugin lee/escribe tiene que pasar por ahí.
- Sin pruebas PHPUnit nuevas (mismo hueco ya existente para
  `export_user_preferences()`, sin cambiar con esta ampliación);
  verificado con una comprobación manual de humo contra la base de
  datos real: guardar/leer la preferencia y renderizar el `single_select`
  con la opción correcta marcada como seleccionada, sin errores. 146
  tests siguen pasando (271 assertions, sin cambios en el recuento —
  esta ampliación no añade lógica de servidor nueva más allá de la
  preferencia).

## 0.14.2 (duración con unidades abreviadas, sin ambigüedad) — 2026-07-29

- **Pedido por el usuario**: el formato tipo cronómetro de 0.14.1
  (`1:15:30`) podía resultar confuso sin indicar qué número es horas,
  cuál minutos y cuál segundos. Se investigó si Moodle tiene algún
  formato corto de duración estándar (como sí tiene para fechas,
  `strftimedatetimeshort`) — no existe ninguno; `format_time()` es la
  única función del núcleo y siempre es verbosa, incluso en tablas de
  administración como el informe de intentos de un cuestionario.
- Columna de duración: unidades abreviadas con etiqueta ("1h 15m 30s",
  "5m 30s", "45s") en vez del formato sin etiquetar — igual de conciso,
  sin la ambigüedad. Nuevas cadenas `durationshort_hours`/`_minutes`/
  `_seconds` (en/es, ambas usan las mismas letras h/m/s).
- Sin cambios de servidor más allá del formateo; sin pruebas nuevas
  (solo presentación); verificado con una comprobación manual de humo
  contra la base de datos real (sesión de 1h 15m 30s → "1h 15m 30s").

## 0.14.1 (fecha y duración en formato corto en el historial) — 2026-07-29

- **Pedido por el usuario**: en una tabla de datos, la concisión importa
  más que la legibilidad "en prosa" de los formatos por defecto.
- Columna de fecha: `strftimedatetimeshort` en vez de `userdate()` por
  defecto — mismo formato ya usado en "Esperando desde" de la tabla de
  solicitudes pendientes de `view.php`, por consistencia.
- Columna de duración: formato tipo cronómetro (`M:SS`, o `H:MM:SS` a
  partir de una hora) en vez de la redacción verbosa de `format_time()`
  ("1 hora 15 minutos" → "1:15:00").
- Sin cambios de servidor más allá del formateo; sin pruebas nuevas
  (solo presentación); verificado con una comprobación manual de humo
  contra la base de datos real (sesión de 1h 15m 30s → "1:15:30").

## 0.14.0 (listado de historial de sesiones para el profesor) — 2026-07-29

- **Pedido por el usuario**: que el profesor pueda ver el listado de
  sesiones de asistencia que ha tenido, como tabla ordenable por fecha,
  curso, nombre del alumno, apellidos del alumno y duración de la
  sesión. Sin colisión con el documento base — es la interfaz de
  consulta que quedó pendiente al añadir la grabación permanente de
  sesión.
- Nueva página `sessionhistory.php`, enlazada desde `view.php` (y
  reflejada en la respuesta AJAX de `get_teacher_dashboard`, que ahora
  incluye también `hashistory`/`historyurl`). Lista solo sesiones
  `closed` del profesor actual.
- Primer uso de `\table_sql` (API estándar de Moodle) en este plugin,
  en `classes/table/session_history_table.php`: ordenado y paginación
  funcionan por parámetros GET en la propia URL, sin AJAX ni
  JavaScript. Las cinco columnas pedidas son las cinco ordenables;
  la duración se calcula en la propia consulta SQL
  (`timeended - timestarted`) para que también se pueda ordenar por
  ella como cualquier otra columna.
- Nueva capacidad `local/remotesupport:viewsessionhistory` (lectura,
  contexto de curso, `RISK_PERSONAL`, profesorado por defecto) — aparte
  de `viewactivesessions`, no una reutilización: ver una única sesión
  activa y ver actividad histórica agregada de un alumno son cosas
  distintas en cuanto a privacidad.
- `session_manager::get_closed_sessions_sql_for_teacher()` devuelve
  fragmentos SQL (`fields`/`from`/`where`/`params` con el `JOIN` a
  `{course}`/`{user}` ya resuelto), no un array de filas — necesario
  para que `table_sql` pueda añadir su propio `ORDER BY`/`LIMIT` según
  lo que pida el profesor, sin traer todas las filas a PHP primero.
  `session_manager` sigue siendo el único sitio que conoce la forma de
  la tabla `local_remotesupport_session`.
- Solo muestra metadatos de la sesión (quién, cuándo, cuánto duró); no
  reproduce el contenido grabado en `local_remotesupport_track` — eso
  sigue siendo un paso posterior sin construir.
- 8 tests PHPUnit nuevos (`tests/permission_manager_test.php` nuevo,
  ampliaciones en `session_manager_test.php`/`external_api_test.php`):
  aislamiento por profesor/estado, cálculo de nombres/duración,
  rechazo/permiso de capacidad, y el esquema AJAX real de
  `get_teacher_dashboard` con los campos nuevos. 146 tests en total
  (271 assertions). Verificación manual de humo adicional contra la
  base de datos real (sin navegador disponible): render de la tabla
  con datos reales, sin errores.

## 0.13.0 (grabación permanente de la sesión para reproducción futura) — 2026-07-29

- **Pedido por el usuario**: almacenar en el servidor un "track"
  completo de cada sesión de asistencia, para que alumno y profesor
  puedan reproducirla más adelante (el alumno para recordar la ayuda
  recibida, el profesor como justificación de su trabajo). **Solo la
  fase de almacenamiento**: sin pantalla de listado ni de reproducción
  todavía, pedido explícitamente así por el usuario.
- **Colisión consciente con el documento base**, planteada al usuario
  antes de implementar: la sección 4.1 excluye "Grabación de sesiones"
  del MVP, y la sección 7.2 dice literalmente que no hay que almacenar
  el contenido completo de las sesiones indefinidamente. El usuario
  confirmó explícitamente que quiere seguir adelante pese a ambas, tras
  conocer los riesgos concretos (quién es el titular real de lo
  grabado, el problema ya existente de "dos titulares" en
  `local_remotesupport_session`, crecimiento de almacenamiento,
  posible desfase del CSS del tema en una reproducción futura). Ver
  `docs/decisions.md` para el razonamiento completo.
- Nueva tabla `local_remotesupport_track` (`sessionid`, `eventtype`
  — solo `page`/`scroll`, ni `chat_message` ni `resync_request` —,
  `payload`, `timecreated`), deliberadamente separada de
  `local_remotesupport_event`: una es entrega en vivo efímera, la otra
  un archivo permanente: mezclarlas habría significado una purga
  pensada para una borrando por error contenido de la otra. Nueva clase
  `classes/local/track_manager.php`, que `event_manager` no conoce.
  `polling_transport::push_event()` alimenta la grabación con el mismo
  payload ya validado/saneado que ya se aceptó para entrega en vivo, en
  el mismo punto — sin repetir saneado.
- **Retención configurable por el administrador**
  (`local_remotesupport/trackretentiondays`: 15/30/90/180/365 días,
  por defecto 90), aplicada por una nueva tarea programada diaria
  `purge_track` — independiente de `purge_events` (ventanas y tablas
  distintas).
- **No se purga al cerrar la sesión** (a diferencia de todo lo demás en
  este plugin) — es justo la propiedad que la hace útil. Solo
  desaparece por la ventana de retención o por una solicitud de
  supresión de datos personales, que la borra de inmediato sin importar
  la retención configurada — decisión explícita del usuario: el
  contenido grabado es la actividad del alumno, así que su derecho de
  supresión se respeta sobre él sin excepción ni anonimización.
- Proveedor de privacidad actualizado: nueva tabla declarada,
  exportación de un recuento por sesión (no el contenido completo,
  impracticable en un archivo de exportación) y borrado en cascada
  añadido a las tres rutas de eliminación ya existentes
  (`delete_data_for_user`/`_for_all_users_in_context`/`_for_users`).
- 9 tests PHPUnit nuevos (`tests/track_manager_test.php` +
  ampliaciones en `polling_transport_test.php`/`privacy_provider_test.php`):
  grabación y aislamiento por sesión, purga por sesión/por antigüedad,
  que `resync_request`/`chat_message` no se graban, que cerrar sesión
  no purga la grabación, y que una solicitud de supresión sí lo hace.
  140 tests en total (256 assertions).

## 0.12.3 (el chat se oculta durante la pantalla completa) — 2026-07-28

- **Decisión del usuario tras 0.12.2**: en vez de seguir persiguiendo el
  problema de hit-testing de `position: fixed` dentro de un elemento en
  pantalla completa (dos intentos fallidos: 0.12.1 arregló que el chat
  desapareciera, 0.12.2 intentó arreglar que el botón de enviar no
  reaccionara), el chat simplemente se oculta mientras el profesor está
  en pantalla completa y reaparece al salir. Sin poder probar en un
  navegador real durante esta sesión, seguir ajustando CSS a ciegas para
  una combinación que ya había fallado dos veces de formas distintas no
  era buen uso del tiempo — ver `docs/decisions.md`.
- `chat_widget.js` expone `hide()`/`show()`; el listener de
  `fullscreenchange` que ya existía en `event_player.js` (para
  reescalar la reconstrucción) los llama al entrar/salir.
- Revertida la complejidad añadida en 0.12.1/0.12.2: el chat vuelve a
  adjuntarse siempre a `document.body` (igual que en el lado del
  alumno, sin caso especial), y se elimina la regla CSS
  `position: absolute` específica para `:fullscreen` — ya no hace
  falta, el chat nunca está visible mientras dura la pantalla completa.
- Nueva limitación documentada: el chat no está disponible mientras el
  profesor está en pantalla completa; debe salir para usarlo.
- Sin cambios de servidor; sin pruebas nuevas (lógica de cliente, mismo
  hueco de siempre); paso de verificación manual añadido en
  `docs/testing.md` (69).

## 0.12.2 (fix: el botón de enviar chat no reaccionaba en pantalla completa) — 2026-07-28

- **Corregido, reportado por el usuario tras 0.12.1**: con el chat ya
  visible dentro de la pantalla completa, el botón "Enviar" dejó de
  reaccionar al clic (ninguna reacción en absoluto, ni visual ni de
  envío). Causa probable: `position: fixed` anidado dentro de un
  elemento en pantalla completa es poco fiable para el hit-testing en
  algunos navegadores — la misma categoría de rareza que ya afectó al
  scroll con rueda del ratón dentro del iframe (0.10.3/0.10.4), esta vez
  aplicada a la detección de clics en vez del scroll.
- En vez de perseguir la causa exacta navegador por navegador, se evita
  la combinación problemática: nueva regla CSS
  `.local-remotesupport-player:fullscreen .local-remotesupport-chat {
  position: absolute; }` — mientras `#local-remotesupport-player` es el
  elemento en pantalla completa (ya convertido en una caja fija que
  llena la pantalla por la propia hoja de estilos del navegador para
  `:fullscreen`), sigue siendo un ancestro posicionado válido para un
  hijo `position: absolute`, sin la combinación fixed-dentro-de-fullscreen
  que fallaba. Fuera de pantalla completa, y en el lado del alumno
  (donde nunca hay pantalla completa), el chat sigue siendo
  `position: fixed` exactamente como antes.
- Cambio puramente CSS; sin cambios de JS ni de servidor; sin pruebas
  nuevas.

## 0.12.1 (fix: el chat se ocultaba en pantalla completa) — 2026-07-28

- **Corregido, reportado por el usuario**: al pulsar el profesor
  "Pantalla completa", la burbuja de chat desaparecía. Causa: el chat se
  añadía como hijo directo de `document.body`, pero la Fullscreen API
  solo renderiza el elemento puesto en pantalla completa
  (`#local-remotesupport-player`) y sus propios descendientes — al
  quedar el chat como hermano de ese elemento, deja de estar en su
  subárbol y el navegador lo oculta mientras dura la pantalla completa.
- `chat_widget.js` acepta ahora un parámetro opcional `appendto` (el
  nodo donde adjuntarse; por defecto `document.body`, igual que antes).
  `event_player.js` le pasa su propio `#local-remotesupport-player` en
  vez de dejarlo caer en `document.body`. Sin cambios en el lado alumno
  (`event_capture.js`), donde nunca hay pantalla completa y
  `document.body` sigue siendo correcto.
- Sin cambios de servidor; sin pruebas nuevas (lógica de cliente, mismo
  hueco de siempre).

## 0.12.0 (chat de texto entre profesor y alumno) — 2026-07-28

- **Pedido por el usuario**: un chat de texto entre profesor y alumno
  durante una sesión activa, en una caja flotante. El documento base
  (`AGENTS.md`) excluye explícitamente "chat complejo" del MVP; se
  interpreta que esa exclusión apunta a plataformas de chat pesadas
  (adjuntos, hilos, indicadores de escritura, historial persistente
  más allá de la sesión), no a un intercambio mínimo de texto plano —
  confirmado explícitamente con el usuario antes de implementar. Ver
  `docs/decisions.md` para el diseño completo y por qué.
- Nuevo tipo de evento `chat_message`, reutilizando la tabla
  `local_remotesupport_event` y el mecanismo de sondeo ya existente —
  sin transporte nuevo, sin tabla nueva, sin endpoint nuevo. A
  diferencia de `page`/`scroll`/`resync_request`, es **bidireccional**:
  ambos roles pueden empujarlo (`polling_transport::ROLE_EVENT_TYPES`),
  y `event_manager::get_events_since()` deja de excluir los eventos
  propios solo para este tipo, de modo que cada participante ve la
  conversación completa, incluidos sus propios mensajes.
- **Persiste durante toda la sesión activa**, no solo unos minutos: se
  exime explícitamente de `purge_stale_events()` (la purga por
  antigüedad de 2 minutos), y solo desaparece cuando la sesión se
  cierra (`purge_session_events()`, sin cambios). Como una carga de
  página nueva del alumno reinicia `sinceid` a 0 (`event_capture.js` se
  reinyecta en cada página de Moodle), el primer sondeo tras esa
  recarga recupera solo por eso todo el historial de chat sin
  necesidad de un endpoint ni almacenamiento de cliente aparte.
- Validación en `event_manager::record_event()`: campo `message`
  obligatorio, no vacío tras recortar espacios, truncado a
  `MAX_CHAT_MESSAGE_LENGTH` (1000 caracteres). Siempre texto plano,
  pintado con `textContent` en el cliente, nunca interpretado como
  HTML — no pasa por el saneador de HTML.
- **Corregido de paso, encontrado al diseñar esto**: `rate_limiter`
  llevaba la cuenta por `sessionid + eventtype` únicamente, sin
  distinguir remitente. Para `scroll` (un único remitente posible, el
  alumno) eso nunca fue un problema, pero `chat_message` lo tiene
  ambos roles — con la clave antigua, un mensaje del alumno habría
  limitado por error la respuesta del profesor si llegaba dentro de la
  misma ventana. La clave de caché ahora incluye también el `userid`.
- Nuevo módulo AMD compartido `amd/src/chat_widget.js` (caja flotante
  colapsable, contador de no leídos, formulario de envío), usado por
  `event_capture.js` (alumno) y `event_player.js` (profesor). El envío
  es "fire-and-forget": el propio mensaje no se pinta al enviarlo, solo
  aparece cuando el sondeo normal lo entrega de vuelta — como el tipo
  ya es bidireccional en el servidor, no hace falta lógica de eco
  optimista ni de-duplicación en el cliente.
- El primer sondeo exitoso tras crear el widget se trata como
  "reposición de historial", no como mensajes nuevos: no incrementa el
  contador de no leídos, para no mostrar una alarma falsa de "N sin
  leer" cada vez que el alumno simplemente cambia de página.
- `pull_events` (`classes/external/pull_events.php`) devuelve ahora
  también `sourceuserid` en cada evento — necesario para que el
  widget distinga mensajes propios de ajenos; antes no se exponía al
  cliente.
- Nuevas cadenas `chat_toggle`/`chat_heading`/`chat_placeholder`/
  `chat_send` (en/es); descripciones de privacidad de
  `local_remotesupport_event` actualizadas para mencionar el chat.
- 12 tests PHPUnit nuevos (validación, exención de purga, bidireccionalidad,
  límite de frecuencia por remitente, roles) — 131 tests en total (239
  assertions). Sin pruebas JavaScript (mismo hueco de siempre).

## 0.11.0 (botón de pantalla completa para el profesor) — 2026-07-28

- **Pedido por el usuario**: una forma de ampliar la reconstrucción, que
  por defecto queda reescalada hacia abajo para caber en la columna
  estándar de Moodle. Evaluadas Fullscreen API nativa vs. un overlay
  CSS propio, y si añadir además un control de zoom manual; el usuario
  eligió solo la Fullscreen API, sin zoom manual (ver
  `docs/decisions.md`).
- `event_player.js` añade un botón **Pantalla completa** que llama a
  `requestFullscreen()`/`exitFullscreen()` sobre todo
  `#local-remotesupport-player` (indicador de conexión + info de página
  + reconstrucción juntos, no solo el `<iframe>`). El evento
  `fullscreenchange` reutiliza `applyViewportSize()` (misma lógica que
  ya existía para el resize de ventana) para reescalar automáticamente
  al entrar/salir, y actualiza el texto del botón también al salir con
  Esc. Botón oculto por completo en navegadores sin Fullscreen API.
- Nuevas cadenas `button_fullscreen`/`button_exitfullscreen` (en/es).
- Sin cambios de servidor; sin pruebas nuevas (lógica de cliente, mismo
  hueco de siempre); pasos de verificación manual añadidos en
  `docs/testing.md` (55-60), incluyendo confirmar que la reconstrucción
  sigue sin reaccionar a clics ni scroll manual.

## 0.10.4 (la reconstrucción ya no es scrollable de forma nativa) — 2026-07-28

- **Pedido por el usuario, tras comprobar que 0.10.3 no bastaba**: en
  vez de seguir bloqueando reactivamente cada vía de scroll manual
  (rueda, teclado...), el documento capturado dentro del iframe deja de
  tener overflow scrollable por completo. `html`/`body` pasan a
  `overflow: hidden`, y `payload.html` se envuelve en un `<div>` interno
  que se posiciona con `transform: translate(-x, -y)` en vez de
  `contentWindow.scrollTo()`. Sin ninguna caja con scroll nativo, no
  hay nada que la rueda, el teclado o cualquier otro input puedan
  mover — la posición solo cambia cuando llega un evento `page`/`scroll`
  sincronizado del alumno.
- Se elimina el bloqueo de `wheel`/`keydown` de 0.10.3 (ya no hace
  falta: no hay scroll nativo que interceptar).
- El modal capturado se mantiene fuera del `<div>` traducido, para que
  siga comportándose como `position: fixed` normal — un `transform` en
  un ancestro convertiría a ese `<div>` en el contenedor de referencia
  de cualquier descendiente `fixed`/`sticky`, rompiendo ese
  comportamiento.
- **Limitación conocida, aceptada conscientemente**: cualquier elemento
  `position: fixed`/`sticky` *dentro del propio contenido capturado*
  (no el modal, que queda aparte) deja de comportarse como fijo y se
  desplaza junto con el resto — en la práctica solo relevante en el
  modo de captura `fullpage` (p. ej. una barra de navegación `sticky`
  del tema); el modo `main` (por defecto) rara vez tiene este patrón.
  Documentado en `docs/limitations.md` y `docs/decisions.md`.
- Sin cambios de servidor; sin pruebas nuevas (lógica de cliente, mismo
  hueco de siempre).

## 0.10.3 (fix: el profesor podía desplazar la reconstrucción manualmente) — 2026-07-28

- **Corregido, reportado por el usuario**: pese al `pointer-events: none`
  de 0.10.2, el profesor todavía podía desplazar el scroll de la
  reconstrucción con la rueda del ratón, de forma independiente a la
  posición real del alumno. Causa: en navegadores basados en Chromium,
  el scroll con rueda sobre un `<iframe>` puede resolverse por un camino
  optimizado (compositor thread) que no pasa por el hit-testing normal,
  saltándose `pointer-events: none`.
- `event_player.js` añade ahora, cada vez que se carga contenido nuevo
  en el iframe (mismo punto donde ya se sincroniza el scroll del
  alumno), un listener `wheel` (con `{passive: false}`) y otro
  `keydown` (para Espacio/Re Pág/Av Pág/Inicio/Fin/flechas) sobre
  `iframe.contentWindow`, ambos con `preventDefault()`. Misma técnica de
  acceso ya usada para `scrollTo()` (permitida por `allow-same-origin`,
  sin que el iframe ejecute script propio) — la reconstrucción ya solo
  puede moverse por un evento `scroll` sincronizado del alumno.
- Sin cambios de servidor; sin pruebas nuevas (lógica de cliente, mismo
  hueco de siempre).

## 0.10.2 (fix: los enlaces de la reconstrucción reaccionaban al clic) — 2026-07-28

- **Corregido, reportado por el usuario**: al pulsar un enlace dentro de
  la reconstrucción de pantalla del profesor, este reaccionaba
  (navegaba), cuando debería ser una vista puramente pasiva. Causa: el
  `<iframe>` usa `sandbox="allow-same-origin"` sin `allow-scripts`, lo
  que ya bloquea JS y formularios, pero un `<a href>` normal navega de
  forma nativa sin depender de JS — el atributo `sandbox` sin
  `allow-top-navigation` solo impide navegar el contexto de nivel
  superior, no que el propio iframe navegue su contenido interno. Antes
  esto quedaba bloqueado como efecto colateral del listener de clic de
  la Fase 3/5 (llamaba `e.preventDefault()` antes de nada); al
  eliminarse ese código entero en 0.10.0, se perdió el efecto colateral
  sin que nada lo sustituyera.
- Añadido `pointer-events: none` a `.local-remotesupport-player-frame`
  en `styles.css`: el navegador deja de entregarle al iframe cualquier
  evento de ratón, así que ningún enlace, botón o elemento futuro dentro
  del contenido capturado puede reaccionar nunca — no depende de qué
  contenga la foto de página, a diferencia de una solución basada en JS
  o en reescribir los `href` durante el saneado.
- Sin cambios de servidor; sin pruebas nuevas (regla CSS pura).

## 0.10.1 (fix: el campo de motivo se vaciaba al escribir) — 2026-07-28

- **Corregido, reportado por el usuario**: al escribir en el campo
  "Motivo (opcional)" del formulario de solicitud, el texto desaparecía
  a los pocos segundos. Causa: `student_client.js` sondea el estado cada
  4 s y volvía a renderizar todo el panel incondicionalmente —
  `Templates.replaceNodeContents()` destruye y recrea el `<input>` en
  el que el alumno está escribiendo, y la plantilla no conoce ese valor
  todavía (no se ha enviado el formulario), así que el campo nuevo nace
  vacío.
- `refresh()` ahora compara el estado recién sondeado con el anterior
  (`JSON.stringify`) y solo vuelve a renderizar si algo cambió realmente
  — mientras el alumno solo está escribiendo el motivo sin enviar nada,
  el estado del servidor no cambia entre sondeos, así que el panel deja
  de tocarse.
- Sin cambios de servidor; sin pruebas nuevas (lógica de cliente, mismo
  hueco de siempre).

## 0.10.0 (solo visualización: sin cursor remoto, clic remoto ni escritura remota) — 2026-07-28

- **Pedido por el usuario**: eliminar toda capacidad del profesor de
  actuar sobre la pantalla del alumno, dejando únicamente visualización
  pasiva. Se elimina por completo lo añadido en la Fase 3 (cursor remoto
  y resaltado), la Fase 5 (clic remoto seguro), la Fase 6 (escritura
  remota en formularios) y la ampliación posterior de scroll
  bidireccional (el profesor moviendo el scroll del alumno). Se
  mantienen las Fases 1 y 2 completas, y el scroll unidireccional
  alumno→profesor de la Fase 4 (el profesor sigue viendo por dónde se
  desplaza el alumno).
- El sistema de niveles de consentimiento (`view`/`pointer`/`click`/
  `input`) desaparece por completo: una sesión activa ya solo implica
  visualización, sin nada que el alumno deba conceder o revocar más
  allá de aceptar la sesión en sí. Se elimina la columna `controllevel`
  de `local_remotesupport_session` vía un nuevo paso de
  `db/upgrade.php` (no se reescribe el paso histórico que la añadió).
- Eliminados: `classes/local/control_level.php`,
  `classes/external/set_control_level.php`,
  `classes/event/control_level_changed.php`,
  `classes/event/remote_click.php`, `classes/event/remote_input.php`,
  `amd/src/interaction_policy.js`. El servicio AJAX
  `local_remotesupport_set_control_level` desaparece de
  `db/services.php` (quedan diez).
- `event_capture.js`/`event_player.js` pierden toda la lógica de cursor,
  resaltado, petición/resultado de clic, petición/resultado de
  escritura y scroll dirigido por el profesor; la barra de estado del
  alumno pierde los botones "Permitir señalar/clics/escritura".
  `event_manager::EVENT_TYPES` queda en `page`, `scroll`,
  `resync_request`; `polling_transport` ya no gatea por nivel, solo por
  rol (alumno empuja `page`/`scroll`, profesor solo `resync_request`).
- El código completo previo a este cambio queda accesible en el tag de
  git `pre-viewonly-full-featured`, por si alguna de estas capacidades
  se necesita retomar más adelante — ver `docs/decisions.md`.
- Pruebas: 119 tests PHPUnit pasando (211 assertions), tras eliminar o
  adaptar los tests específicos de cursor/resaltado/clic/escritura/
  niveles de consentimiento en `tests/event_manager_test.php`,
  `tests/polling_transport_test.php`, `tests/external_api_test.php`,
  `tests/session_manager_test.php`, `tests/rate_limiter_test.php`;
  `tests/control_level_test.php` eliminado.

## 0.9.2 (aviso al profesor cuando el alumno finaliza) — 2026-07-27

- **Pedido por el usuario**: al finalizar el alumno la asistencia (lo
  más habitual, desde la barra de estado persistente mientras navega el
  curso), el profesor solo se enteraba por un cambio sutil en el
  indicador de conexión, fácil de pasar por alto. El usuario pidió que
  se "cerrase la ventana del profesor" — no es posible desde
  JavaScript para una pestaña no abierta con `window.open()` (ver
  `docs/decisions.md`), así que se implementa el equivalente práctico:
  un panel imposible de pasar por alto con el mensaje "El alumno ha
  finalizado la asistencia" y un botón **Volver a las solicitudes**.
- Nueva cadena `sessionendedbystudent`; reutiliza `link_backtorequests`
  (ya existente) para el botón.
- No se puede saber con certeza *quién* cerró la sesión — el mensaje
  asume que fue el alumno, el caso ampliamente más frecuente cuando el
  profesor sigue viendo `session.php` sin haberla cerrado él mismo; ver
  `docs/decisions.md`/`docs/limitations.md` para el caso borde.
- Sin cambios de servidor; sin pruebas nuevas (lógica de cliente, mismo
  hueco de siempre).

## 0.9.1 (fix: precisión del cursor remoto) — 2026-07-27

- **Corregido, reportado por el usuario al probar "Permitir señalar"**:
  el cursor del profesor no señalaba el mismo sitio que veía el alumno.
  Causa: el `<iframe>` del profesor se dibujaba a `width: 100%` de su
  propia columna (casi siempre más estrecho que la ventana del alumno),
  y al ser Moodle responsive, la misma página se reorganiza de forma
  distinta a cada anchura — la fracción de posición se calculaba bien,
  pero sobre un diseño que no era el mismo que veía el alumno.
- `event_player.js` fuerza ahora el `<iframe>` a las dimensiones reales
  del viewport del alumno (`payload.viewport`, ya se enviaba desde la
  Fase 2 sin usarse) y lo encoge visualmente con `transform: scale()`
  para que quepa en la pantalla del profesor — como `transform` no
  cambia lo que `iframe.contentWindow.innerWidth` reporta por dentro,
  el cálculo de fracción que ya existía queda correcto sin tocarlo. La
  escala se recalcula también si el profesor redimensiona su ventana.
- Sin cambios en el resaltado ni el clic remoto (resolución por
  selector, no por coordenadas — no dependían de este problema).
- Sin pruebas nuevas (cambio puramente de cliente, sin equivalente
  server-side — mismo hueco de siempre); verificación manual añadida en
  `docs/testing.md` (pasos 89-92).

## 0.9.0 (modo de captura "página completa") — 2026-07-27

- **Ampliación pedida por el usuario** tras probar la reconstrucción de
  pantalla: quería ver exactamente lo mismo que el alumno, incluida la
  navegación, los bloques laterales y el pie de página — no solo el
  contenido principal (alcance de la Fase 2 desde el principio).
- Nuevo ajuste de administración `local_remotesupport/capturemode`
  (`main`/`fullpage`, por defecto `main` = comportamiento sin cambios),
  aplicado a todas las sesiones del sitio por igual — no por profesor
  ni por sesión, decisión tomada explícitamente con el usuario; ver
  `docs/decisions.md`.
- `event_capture.js`: nueva `findCaptureRoot(mode)` decide qué capturar
  y observar (`document.body` completo en `fullpage`, el contenido
  principal de siempre en `main`); en `fullpage` el modal ya no se
  captura aparte (viene incluido) y un único observador de mutaciones
  basta (el segundo, dedicado a modales en `main`, sería redundante).
- El filtro "ignora mis propios elementos" del observador de mutaciones
  pasa de mirar solo hijos directos a mirar ascendientes
  (`Element.closest()`) — necesario en `fullpage`, donde la barra de
  estado/cursor/confirmación de clic sí viven dentro de lo observado;
  sin cambio de comportamiento en `main`.
- Límites de tamaño más altos, solo relevantes en `fullpage`: recorte
  del cliente 400 000 caracteres (antes 150 000, sigue igual en
  `main`), `html_sanitizer::MAX_LENGTH` 400 000 (antes 200 000),
  `event_manager::MAX_PAYLOAD_BYTES` 600 000 (antes 260 000).
- Sin pruebas JavaScript automatizadas nuevas (mismo motivo que el
  resto del plugin); las pruebas PHPUnit existentes que referencian los
  límites de tamaño dinámicamente siguen pasando sin cambios.

## 0.8.1 (fix: "Aceptar" dejaba de funcionar tras el primer sondeo) — 2026-07-27

- **Corregido un fallo real de la versión 0.8.0**: `get_teacher_dashboard`
  (la función AJAX que refresca la tabla de `view.php`) devolvía las filas
  de solicitudes pendientes/sesiones abiertas **sin** `accepturl`/
  `enterurl`/`finishurl` — esas urls solo se añadían en el render PHP
  inicial de `view.php`, no en `classes/output/teacher_dashboard.php`
  (que sí comparte el resto del contexto entre el primer render y el
  AJAX). En cuanto el primer sondeo automático (a los pocos segundos de
  cargar la página) reemplazaba la tabla, los enlaces de acción quedaban
  con `href=""`; pulsar "Aceptar" en ese momento simplemente recargaba
  `view.php` sin ningún parámetro, sin efecto visible.
- Corregido moviendo la construcción de esas urls dentro de
  `teacher_dashboard::export_pending()`/`export_open()`, igual que ya
  hacía `student_status::export()` para el lado del alumno — las dos
  clases quedan ahora simétricas, y `view.php` ya no necesita reconstruir
  las urls por su cuenta tras llamar al exportador.
- **Gap de pruebas real que dejó pasar este fallo**: las 18 pruebas
  nuevas de la 0.8.0 llamaban a `::execute()` directamente, que **no**
  valida el valor devuelto contra el esquema declarado en
  `execute_returns()` — esa validación solo la aplica
  `external_api::call_external_function()`, la ruta real de una llamada
  AJAX de verdad. Se añadió `assert_valid_return()` en
  `tests/external_api_test.php`, que llama a
  `external_api::clean_returnvalue()` explícitamente sobre cada
  resultado, y se aplicó a las 8 funciones nuevas — un desajuste de
  esquema como este ahora lo detecta PHPUnit, no un clic real en el
  navegador.

## 0.8.0 (ciclo de vida de solicitud/sesión sin recarga de página) — 2026-07-27

- **Ampliación pedida explícitamente por el usuario** ("sin esta
  funcionalidad, no me lo aprobarían" como MVP): `request.php` y
  `view.php` dejan de depender de una recarga completa de página para
  reflejar cada cambio de estado (solicitud aceptada, nueva solicitud
  pendiente, sesión finalizada...); también el badge de solicitudes
  pendientes de la barra de navegación, que hasta ahora solo se
  recalculaba en cada carga de página.
- Ocho funciones de servicio web nuevas
  (`local_remotesupport_get_student_status`, `_request_assistance`,
  `_cancel_request`, `_enter_session`, `_finish_session`,
  `_accept_request`, `_get_teacher_dashboard`, `_get_pending_count`),
  envoltorios finos sobre `session_manager`/`permission_manager` que no
  cambian ninguna regla de negocio existente — mismo patrón que ya
  usaban `push_event`/`pull_events`/`set_control_level` desde la Fase 2.
- Dos clases nuevas, `classes/output/student_status.php` y
  `teacher_dashboard.php`, construyen el contexto de plantilla una sola
  vez, reutilizado tanto por el primer render PHP como por cada
  respuesta AJAX de refresco — evita que las dos rutas puedan mostrar
  mensajes distintos para el mismo estado.
- Progressive enhancement, no una reescritura: los `<form>`/`<a>` que ya
  existían en `student_page.mustache`/`teacher_dashboard.mustache` no se
  tocan; los módulos AMD nuevos (`amd/src/student_client.js`,
  `teacher_client.js`, `session_requests.js`) solo interceptan esos
  mismos elementos (`preventDefault` + llamada AJAX). Sin JavaScript, o
  si un módulo falla al cargar, todo sigue funcionando exactamente igual
  que antes de este cambio, con recarga completa.
- Sondeo cada 4 s en `request.php`/`view.php`, pausado mientras la
  pestaña no es visible (`document.visibilitychange`).
- El icono de solicitudes pendientes del navbar (ampliación anterior)
  gana sondeo propio cada 15 s (`amd/src/navbar_badge.js`, nueva función
  `get_pending_count`), revirtiendo deliberadamente la decisión previa
  de "sin sondeo en vivo" — documentado como tal en `docs/decisions.md`,
  no como un descuido de aquella decisión.
- **"Entrar"/"Aceptar" siguen siendo una navegación real** a
  `session.php`; lo único que pasa a pedirse por AJAX es el token de
  entrada de un solo uso, justo en el momento del clic
  (`enter_session`/`accept_request`) en vez de venir pre-incrustado en
  el HTML — necesario porque `issue_entry_token()` invalida el token
  anterior en cada llamada, así que pre-generarlo en cada sondeo
  periódico habría invalidado constantemente el enlace de una pestaña
  de `session.php` ya abierta.
- Dos métodos nuevos en `permission_manager`
  (`require_can_view_dashboard()`, `require_can_provide_anywhere()`)
  para no duplicar en las nuevas funciones externas comprobaciones que
  `view.php`/`lib.php` ya hacían de forma inline.
- 18 pruebas nuevas en `tests/external_api_test.php`, una por cada
  función externa nueva más sus rechazos de autorización
  correspondientes; ninguna prueba existente cambia de comportamiento
  (la máquina de estados de `session_manager` no se toca).
- Sin pruebas JavaScript automatizadas para los módulos AMD nuevos,
  mismo motivo que el resto del plugin (sin Node/Grunt en el servidor de
  pruebas) — ver `docs/limitations.md`.

## 0.7.2 (revisión de código: validación de eventos y de-duplicación) — 2026-07-27

- **Tras una revisión de código pedida por el usuario** sobre todo lo
  hecho hasta ahora (sencillez, eficiencia, legibilidad), se aplican los
  dos hallazgos con impacto real; el resto queda documentado en
  `docs/TODO.md` para revisar más adelante.
- **`event_manager::record_event()` valida ahora la forma de los
  payloads `cursor`, `scroll`, `scroll_request` (coordenadas `x`/`y`
  numéricas) y `highlight` (`selector` no vacío).** Antes, estos cuatro
  tipos solo estaban protegidos por el límite de tamaño total del
  payload (260&nbsp;000 bytes); un valor no numérico o un selector vacío
  se guardaba y reenviaba tal cual. Los tipos `page` e `input_request` ya
  tenían su propia validación desde antes.
- **`session_manager::get_open_request_for_student()` gana un segundo
  parámetro opcional `?int $courseid = null`**, eliminando la consulta
  SQL casi idéntica que antes vivía por separado en
  `get_open_request_for_student_global()`. Este último método se
  mantiene como un alias de una línea (se conserva el nombre porque en su
  único punto de uso — el botón flotante de solicitud — la ausencia de
  curso es precisamente lo relevante).
- 6 pruebas nuevas en `tests/event_manager_test.php` para la validación
  de forma añadida; sin cambios de comportamiento observable en las
  pruebas existentes (se verificó que ninguna dependía de aceptar un
  payload con forma inválida).

## 0.7.1 (indicador visual de disponibilidad en el icono del navbar) — 2026-07-27

- **Ampliación pedida tras probar activar/desactivar asistencia**: el
  icono del navbar ahora indica, con una barra cruzada dibujada por CSS
  sobre el propio icono, si el profesor que lo ve se ha desactivado a sí
  mismo — mismo lenguaje visual que un icono de "micrófono silenciado".
  Sin icono nuevo ni assets adicionales.
- El `title`/`aria-label` del icono también cambia de texto cuando está
  desactivado ("... no estás disponible ahora mismo"), para que la
  información no dependa solo de la señal visual.
- `tests/lib_test.php` ampliado con los dos estados (activado/
  desactivado).

## 0.7.0 (ajustes personales del profesor: activar/desactivar asistencia) — 2026-07-27

- **Ampliación pedida tras probar el motivo de la solicitud**: el icono
  del navbar deja de aparecer solo cuando hay solicitudes pendientes — a
  partir de ahora se muestra siempre a cualquiera que pueda proporcionar
  asistencia en algún curso, porque además de llevar a la lista de
  solicitudes es la puerta de entrada a una nueva pantalla de
  configuración personal (`teachersettings.php`). El número de
  solicitudes pendientes sigue apareciendo como badge solo cuando es
  mayor que cero.
- Primer parámetro de esa pantalla: activar/desactivar si el profesor
  acepta actualmente solicitudes de asistencia. Se guarda como
  preferencia de usuario estándar de Moodle (`get_user_preferences()` /
  `set_user_preference()`), no como columna nueva en la tabla de
  sesiones — el usuario ya avisó que añadirá más parámetros, y así cada
  uno nuevo no necesita su propia migración de esquema. Por defecto
  activado, para que ningún profesor existente deje de recibir
  solicitudes sin haberlo decidido explícitamente tras la actualización.
- Nueva clase `classes/local/teacher_settings.php`, con
  `is_support_available_for_course()` como método central: mira a todos
  los usuarios con `provideassistance` en un curso y comprueba si al
  menos uno tiene la asistencia activada.
- Si ningún profesor de un curso tiene la asistencia activada, el
  alumno ve un botón deshabilitado "No hay personal de soporte
  disponible" en vez del formulario de solicitud — comprobado tanto en
  la vista (`request.php`) como, por si acaso, antes de crear la
  solicitud de verdad (para que no baste con conocer la URL de la
  acción para saltarse el aviso).
- El botón flotante (ampliación anterior) no se ha tocado: sigue
  enumerando cursos solo por capacidad, no por disponibilidad actual;
  el aviso de "sin soporte" aparece al llegar a la página de solicitud
  de ese curso, sea por el menú, por el botón flotante o por una
  solicitud ya abierta.
- Declarado en la Privacy API como preferencia de usuario
  (`user_preference_provider`), con su propio `export_user_preferences()`.
- Pruebas nuevas en `tests/teacher_settings_test.php` (valor por
  defecto, activar/desactivar, aislamiento entre profesores,
  disponibilidad con varios profesores) y `tests/lib_test.php`
  actualizado para el nuevo comportamiento del icono del navbar.

## 0.6.5 (motivo opcional en la solicitud) — 2026-07-27

- **Ampliación pedida tras probar el botón flotante**: al solicitar
  asistencia, el alumno puede escribir opcionalmente un breve motivo
  (máx. 255 caracteres) que el profesor ve en la lista de solicitudes
  pendientes, para decidir con más contexto si aceptar.
- Nueva columna `reason` (nullable) en `local_remotesupport_session`,
  vía `db/upgrade.php`. `session_manager::create_request()` recorta
  espacios y trunca a `MAX_REASON_LENGTH` (255); una cadena vacía tras
  recortar se guarda como `null`, no como cadena vacía.
- El formulario de solicitud (`request.php`/`student_page.mustache`) deja
  de ser un enlace simple y pasa a ser un `<form method="post">` — un
  enlace no puede llevar texto libre escrito por el alumno. El resto de
  acciones (cancelar, entrar, finalizar) siguen siendo enlaces.
- El motivo se muestra en la tabla de solicitudes pendientes del
  profesor tal cual, sin pasar por el saneador HTML de la Fase 2 (ese
  saneador es para reconstruir la pantalla del alumno; esto es texto
  simple). Mustache escapa `{{reason}}` automáticamente al no usar
  triple-llave, así que un motivo con `<script>` o similar se muestra
  como texto literal, nunca se ejecuta — verificado con una prueba de
  humo en el sitio real.
- Añadido a la Privacy API: `reason` en los metadatos y en la
  exportación de datos del alumno.
- Pruebas nuevas en `tests/session_manager_test.php` (guardado, recorte,
  truncado, valor por defecto) y `tests/privacy_provider_test.php`
  (el motivo se exporta correctamente).

## 0.6.4 (botón flotante de solicitud de asistencia) — 2026-07-27

- **Ampliación pedida tras probar el MVP completo**: botón flotante,
  visible en cualquier página de Moodle (no solo dentro de un curso),
  para que el alumno pueda solicitar asistencia aunque no consiga llegar
  al menú del curso (p. ej. desde el panel principal, o tras un error de
  navegación). El enlace del menú del curso se mantiene tal cual.
- Como las solicitudes son por curso, el botón resuelve tres estados: si
  el alumno ya tiene una solicitud abierta en algún curso, enlaza
  directamente a ella ("Ver mi solicitud"); si no, y solo tiene un curso
  donde puede solicitar asistencia, enlaza directamente a él; si tiene
  varios, muestra un selector.
- El selector de curso es un `<details>` HTML simple, sin JavaScript
  adicional — mismo criterio que el icono de la barra de navegación
  (`docs/decisions.md`).
- Nuevo `session_manager::get_open_request_for_student_global()`: única
  pieza de datos nueva que hizo falta, ya que hasta ahora ninguna
  consulta buscaba la solicitud abierta de un alumno sin conocer de
  antemano el curso.
- No se muestra durante una sesión activa (la barra de estado ya cubre
  ese caso) ni cuando el alumno no tiene ningún curso donde pueda
  solicitar asistencia — desaparece por completo, igual que el icono de
  la barra de navegación.
- `lib.php`: `local_remotesupport_before_footer()` pasa de no devolver
  nada a devolver el HTML del botón; Moodle añade ese valor de retorno
  al pie de página automáticamente (`before_footer_html_generation::process_legacy_callbacks()`),
  así que no hace falta ningún cambio en cómo se registra el callback.
- Pruebas nuevas en `tests/session_manager_test.php` y
  `tests/lib_test.php` para los tres estados del botón y el nuevo
  método de `session_manager`.

## 0.6.3 (icono de solicitudes pendientes) — 2026-07-27

- **Ampliación pedida tras probar el MVP completo**: icono junto a los
  de mensajes/notificaciones en la barra de navegación que indica
  cuántas solicitudes de asistencia están pendientes, y lleva
  directamente a `view.php` al pulsarlo. Solo visible cuando hay al
  menos una solicitud pendiente.
- Implementado con el mecanismo estándar de Moodle para esto
  (`PLUGIN_render_navbar_output`, el mismo que usa `message_popup` para
  la campana de notificaciones) — sin tocar el tema.
- Reutiliza `session_manager::get_pending_requests_for_teacher()`, el
  mismo método que ya usa `view.php`, así que el contador del icono y la
  lista que se ve al pulsar nunca pueden desincronizarse.
- Enlace simple, sin desplegable ni sondeo en segundo plano — el
  contador se recalcula en cada carga de página; decisión documentada en
  `docs/decisions.md`.
- Nuevo `tests/lib_test.php`: primera prueba directa de un callback de
  `lib.php` en este plugin.

## 0.6.2 (fix codificación UTF-8) — 2026-07-27

- **Corregido**: el profesor veía caracteres acentuados y `ñ`/`¿`/`¡`
  corrompidos (mojibake tipo "Ã³") en la reconstrucción de pantalla.
  Causa: `html_sanitizer::sanitize()` pasaba el HTML capturado a
  `DOMDocument::loadHTML()` sin indicar la codificación; libxml asume
  ISO-8859-1 por defecto y reinterpreta cada carácter UTF-8 multibyte
  como dos caracteres Latin-1 al volver a serializar. Se añade la
  instrucción de procesamiento XML de codificación estándar antes de
  parsear (nunca
  llega a aparecer en el fragmento extraído, así que no hace falta
  quitarlo después). Prueba de regresión añadida en
  `tests/html_sanitizer_test.php`.

## 0.6.1 (scroll bidireccional) — 2026-07-26

- **Ampliación pedida tras probar el MVP completo, fuera de las seis
  fases originales de `AGENTS.md`**: el profesor ahora también puede
  desplazar la página real del alumno haciendo scroll dentro de su
  propia reconstrucción. Nuevo evento `scroll_request`
  (profesor→alumno), gateado por nivel `pointer` (el mismo que ya exigen
  cursor/resaltado, sin nivel nuevo), sin confirmación (no ejecuta
  ninguna acción sobre contenido).
- Guarda anti-eco bidireccional: aplicar el scroll de una parte no
  reenvía automáticamente esa misma posición como si fuera una orden
  nueva de la otra — bandera temporal de 50 ms en ambos lados
  (`event_player.js`/`event_capture.js`). Documentado en
  `docs/decisions.md`.
- Límite de frecuencia en servidor para `scroll_request` (150 ms, mismo
  que `scroll`).
- Pruebas PHPUnit para el rol/nivel de `scroll_request` y su ciclo
  completo a través de la API externa real.
- Documentado explícitamente como ampliación consciente fuera de alcance
  original, no como parte retroactiva de la Fase 4.

## 0.6.0 (Fase 6) — 2026-07-26

- Escritura remota en formularios: nuevo par de eventos `input_request`
  (profesor→alumno, exige nivel `input`, el más alto del sistema) y
  `input_result` (alumno→profesor). El profesor resalta un campo (Fase 3)
  y escribe en un pequeño compositor con tres acciones: **Establecer
  valor**, **Añadir texto**, **Vaciar campo**.
- `canSetValue()` añadido a la política centralizada
  (`amd/src/interaction_policy.js`, junto a `canClick()` de la Fase 5):
  lista blanca (solo `<textarea>`/`<input type="text"|"search">`, ni
  deshabilitado ni de solo lectura, nunca dentro de un `<iframe>`) y
  lista de bloqueo (tipos sensibles, `autocomplete` sensible, palabras
  clave en `name`/`id`), más un bloqueo por página completa en
  intento/resumen/revisión de cuestionario y envío de tarea.
- Sin confirmación explícita por escritura individual, a diferencia del
  clic — el nivel `input`, que ya exige un paso explícito del alumno,
  hace de consentimiento de grano grueso; decisión documentada en
  `docs/decisions.md`.
- Se transmiten cambios semánticos (`set_value`/`append_text`/`clear`),
  nunca pulsaciones de teclado en crudo; `value` se trunca a 4000
  caracteres en servidor.
- Nuevo botón "Permitir escritura" en la barra de estado del alumno
  (visible solo con nivel `click` ya concedido).
- Auditoría persistente del resultado de cada escritura remota
  (`remote_input`, eventos Moodle estándar), igual que `remote_click`.
- **Corregido un fallo real de la Fase 5**: `audit_manager::remote_click()`
  estaba definido pero nunca se llamaba desde ningún sitio, así que
  ningún clic remoto quedó nunca en el log de auditoría permanente.
  `polling_transport::push_event()` ahora dispara `remote_click()`/
  `remote_input()` justo después de guardar con éxito un
  `click_result`/`input_result`; documentado en `docs/decisions.md` y
  `docs/security.md`, con pruebas de regresión dedicadas.
- Pruebas PHPUnit para la validación de `action`/`value` en servidor, el
  gate por nivel `input` en `polling_transport`, el ciclo completo de
  `input_request`/`input_result` de extremo a extremo, y la corrección de
  auditoría anterior.

## 0.5.0 (Fase 5) — 2026-07-26

- Sistema de niveles de consentimiento (`view` < `pointer` < `click` <
  `input`, `classes/local/control_level.php`), columna nueva
  `controllevel` en la sesión, cambiable solo por el propio alumno
  (`set_control_level`, tercer web service AJAX) con efecto inmediato.
- **Cambio de comportamiento respecto a la Fase 3**: cursor y resaltado
  pasan a exigir nivel `pointer` (antes funcionaban sin ninguna puerta de
  consentimiento) — corrige una incoherencia del sistema de niveles, no
  una regresión accidental; documentado en `docs/decisions.md`.
- Clic remoto seguro: nuevo par de eventos `click_request`
  (profesor→alumno, exige nivel `click`) y `click_result`
  (alumno→profesor). El profesor primero resalta (Fase 3) y después pulsa
  "Solicitar clic" como segunda acción explícita.
- Política centralizada de clics (`amd/src/interaction_policy.js`):
  lista blanca (enlaces, botones que no envían formularios, pestañas,
  disparadores de acordeón/desplegable) y lista de bloqueo (envíos de
  formulario, enlaces externos, descargas, subida de archivos, palabras
  clave destructivas) — debe pasar ambas.
- Confirmación explícita del alumno para el 100% de los clics, sin
  excepción, con caducidad de 15 segundos.
- Barra de estado del alumno ahora interactiva: botones "Permitir
  señalar"/"Permitir clics"/"Revocar todo", con el nivel actual visible.
- Auditoría persistente (eventos Moodle estándar, no la tabla efímera)
  de cambios de nivel y de cada clic remoto resuelto.
- Pruebas PHPUnit para el sistema de niveles, el gate por nivel en
  `polling_transport`, y el ciclo completo de `click_request`/
  `click_result` de extremo a extremo.

## 0.4.0 (Fase 4) — 2026-07-26

- **Corregido un fallo real de las Fases 2–3**: la reconstrucción del
  profesor no cargaba ninguna hoja de estilos y se veía como HTML sin
  formato. Ahora el alumno reporta las URLs de sus hojas de estilo del
  mismo origen y el profesor las carga como `<link>` en su `iframe`.
- Captura y relevo de modales de Moodle (Bootstrap): backdrop + diálogo,
  saneados igual que el contenido principal, detectados con un
  `MutationObserver` propio sobre `<body>`.
- Indicador visible de "página actual" (título + URL) sobre la
  reconstrucción del profesor.
- Nuevo evento `resync_request` (solo el profesor puede empujarlo): se
  dispara automáticamente al recuperar la conexión tras un corte, para
  pedir una foto completa inmediata en vez de esperar al latido de 10 s.
- Límite de frecuencia en servidor también para `scroll` (150 ms), además
  del ya existente para `cursor` (50 ms).
- Guarda explícita local/remoto en el observador de `<body>`, para no
  confundir las propias inserciones del plugin (barra de estado, cursor)
  con cambios del alumno que merecieran un reenvío.
- Pruebas PHPUnit para el filtrado de URLs de CSS, el saneamiento del
  modal, el límite de frecuencia de `scroll`, y la autorización de
  `resync_request` por rol.

## 0.3.0 (Fase 3) — 2026-07-26

- Cursor remoto: el profesor mueve el ratón sobre su reconstrucción y el
  alumno ve un puntero identificado en la posición equivalente de su
  propia pantalla (enviado como fracción de viewport, no píxeles
  absolutos).
- Resaltado de elementos: clic del profesor sobre un elemento de la
  reconstrucción → se resalta el elemento equivalente en la página real
  del alumno; botón para quitar el resaltado.
- Selector de elemento con cadena de prioridad (id → `data-*` →
  estructural acotado a 4 niveles), sin depender de coordenadas.
- Transporte de eventos ahora bidireccional por rol: el alumno empuja
  `page`/`scroll` y lee `cursor`/`highlight`; el profesor empuja
  `cursor`/`highlight` y lee `page`/`scroll`. `polling_transport`
  rechaza cualquier tipo de evento que no corresponda al rol de quien lo
  envía.
- Límite de frecuencia en servidor (`rate_limiter`, caché de aplicación)
  para eventos `cursor`: mínimo 50 ms entre eventos de la misma sesión,
  descartados en silencio (no es un error) si llegan antes.
- Ningún registro permanente de movimientos de cursor ni resaltados,
  igual que el resto de eventos de pantalla.
- Pruebas PHPUnit para el límite de frecuencia, la autorización por rol
  y tipo de evento, y el filtrado "solo eventos de la otra parte" al
  leer.

## 0.2.0 (Fase 2) — 2026-07-26

- Reconstrucción aproximada, saneada del lado servidor, de la página que
  el alumno está usando, visible por el profesor dentro de un `iframe`
  sandbox.
- Captura sitewide: `lib.php::local_remotesupport_before_footer()`
  inyecta el módulo de captura en cualquier página de Moodle mientras el
  alumno tiene una sesión activa; barra de estado persistente para el
  alumno con botón de finalizar.
- Tabla `local_remotesupport_event`, purgada al cerrar la sesión y por una
  tarea programada de seguridad (eventos huérfanos > 2 minutos).
- Transporte por AJAX periódico (`polling_transport`), tras la interfaz
  `transport_interface` para poder sustituirlo más adelante.
- Dos web services AJAX (`push_event`, `pull_events`), cada uno
  restringido a un rol de la sesión (alumno empuja, profesor lee).
- Saneador de HTML autoritativo (`html_sanitizer`, `DOMDocument`): quita
  scripts, iframes, atributos `on*`, URLs `javascript:`, y todo valor de
  campo de formulario, antes de guardar cualquier evento `page`.
- Evaluación documentada de rrweb vs. implementación propia (se elige la
  propia): `docs/decisions.md`.
- Pruebas PHPUnit para el saneador, el gestor de eventos, el transporte y
  los dos web services de extremo a extremo.

## 0.1.0 (Fase 1) — 2026-07-26

- Solicitud, aceptación, entrada y finalización de sesiones de asistencia,
  sin ningún tipo de captura o control del navegador todavía.
- Capacidades `requestassistance`, `provideassistance`,
  `viewactivesessions`, `managesessions`.
- Tabla `local_remotesupport_session` con máquina de estados
  `requested → accepted → active → closed`, y salidas `cancelled`/
  `expired`.
- Tokens de entrada de un solo uso por rol (alumno/profesor), hasheados,
  nunca almacenados en claro.
- Auditoría vía eventos estándar de Moodle.
- Tarea programada de caducidad de solicitudes pendientes.
- Proveedor de privacidad (Privacy API).
- Pruebas PHPUnit para la máquina de estados, permisos, tokens y
  privacidad.
