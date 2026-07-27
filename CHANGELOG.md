# Changelog

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
