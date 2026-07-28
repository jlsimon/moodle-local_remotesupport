# Changelog

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
