# Limitaciones conocidas

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

## Nuevas desde la Fase 3

- **El selector de resaltado puede apuntar a un elemento distinto del que
  el profesor creyó señalar, o a ninguno.** Se construye sobre la foto
  del profesor y se resuelve sobre la página real del alumno; si la
  página cambió entre medias (contenido dinámico, `id` generado de nuevo
  en cada carga), el selector puede no encontrar nada — en ese caso no se
  resalta nada, sin error visible para ninguna de las dos partes. No hay
  todavía una verificación de "sigue siendo el mismo elemento" como la
  que pedirá la Fase 5 para clics.
- **Sin última red de coordenadas relativas para el resaltado.** El
  documento base la lista como quinta opción; no se implementó (ver
  `docs/decisions.md`). Si ninguna de las cuatro anteriores encuentra
  nada, simplemente no se resalta.
- **La interacción del profesor depende de que `allow-same-origin` sin
  `allow-scripts` permita escuchar eventos del `iframe` desde la ventana
  padre.** Es un patrón válido de la especificación HTML, pero no se ha
  verificado en un navegador real en este MVP (sin esa herramienta
  disponible aquí) — ver el paso 27 de `docs/testing.md` y la
  justificación en `docs/decisions.md`.
- **El cursor remoto no tiene en cuenta el scroll del profesor dentro de
  su propia reconstrucción.** La fracción se calcula sobre el viewport
  visible del `iframe`, no sobre la posición absoluta en el documento
  reconstruido; si el profesor ha hecho scroll dentro de su recuadro,
  la posición que ve el alumno puede no corresponder exactamente al
  mismo punto visual. Aceptable para el MVP porque el recuadro del
  profesor normalmente muestra el mismo tramo de página que ve el
  alumno (ambos reciben actualizaciones de scroll independientemente).
- **Sin lista blanca de elementos "seguros" para resaltar.** Cualquier
  elemento es resaltable, incluidos botones destructivos — sigue sin ser
  un riesgo real porque resaltar no ejecuta ninguna acción; la lista
  blanca de la Fase 5 se aplica solo al *clic*, no al resaltado.

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
  espera** (mismo razonamiento que el paso 27 de la Fase 3 sobre
  `allow-same-origin`) — ver el paso 28 de `docs/testing.md`.

## Nuevas desde la Fase 5

- **La política de clic remoto no tiene ninguna prueba automatizada.**
  Es la limitación más importante de esta fase, no una más: toda la
  garantía de "un conjunto pequeño y claramente definido de clics
  seguros" descansa en `interaction_policy.js`, sin PHPUnit ni Jest
  detrás. Ver `docs/security.md`.
- **Heurística de palabras clave en un idioma limitado.** La lista de
  bloqueo por texto reconoce español e inglés; un tema o plugin de
  actividad en otro idioma con la misma acción destructiva no quedaría
  cubierto por esa capa concreta (las comprobaciones estructurales —
  envío de formulario, `type=file`, enlaces externos — siguen aplicando
  igual, en cualquier idioma).
- **Confirmación de clic para absolutamente todo, sin gradación.** El
  documento base sugiere confirmar solo "acciones potencialmente
  importantes"; este MVP interpreta "en caso de duda" de la forma más
  conservadora y pide confirmación siempre. Una fase futura podría querer
  distinguir clics triviales (una pestaña) de clics con más peso, pero
  eso exige una clasificación de severidad que no existe todavía.
- **El "Solicitar clic" del profesor actúa sobre el resaltado actual, no
  sobre un clic directo.** Si el profesor resalta un elemento y luego
  el alumno navega a otra página antes de pulsar "Solicitar clic", la
  petición viaja con un selector que ya no corresponde a nada visible —
  se resuelve como "no encontrado", pero no hay ninguna advertencia
  previa al profesor de que el contexto cambió.
- **`input[type=file]` se bloquea, pero no se ha probado con selectores
  de archivo personalizados (drag-and-drop, botones que abren un diálogo
  del sistema operativo mediante JavaScript sobre un `input` oculto).**
  El bloqueo cubre el `input` en sí; un patrón de UI que oculte el
  `input` real detrás de un botón visible distinto dependería de que ese
  botón también caiga en alguna regla de bloqueo (por ejemplo, palabra
  clave), no hay una regla genérica "esto abre un selector de archivos".
- **Sin límite de frecuencia para `click_request`/`click_result`.** No
  supone un riesgo de por sí (cada clic exige una acción deliberada del
  profesor y una confirmación del alumno), pero no hay ninguna protección
  contra un profesor que solicitara clics repetidamente en sucesión
  rápida más allá de la molestia que eso causaría al alumno.

## Nuevas desde la Fase 6

- **La política de escritura remota tampoco tiene ninguna prueba
  automatizada**, por el mismo motivo que la de clic (Fase 5): solo puede
  evaluarse contra el DOM real, que el servidor nunca ve. Ver
  `docs/security.md`.
- **Sin editores enriquecidos (TinyMCE u otros).** El documento base
  excluye esto explícitamente del MVP inicial. Solo `<textarea>` e
  `<input type="text"|"search">` son candidatos; cualquier editor que
  reemplace un `<textarea>` con su propia interfaz (TinyMCE, Atto)
  presenta al DOM una estructura distinta que `canSetValue()` no
  reconoce, así que queda fuera de la lista blanca sin necesidad de una
  regla explícita para excluirlo.
- **Sin transmisión tecla a tecla.** Se transmiten cambios semánticos
  (`set_value`/`append_text`/`clear`), no eventos de teclado en crudo, tal
  como pide el documento base — significa que el alumno ve el campo
  cambiar de golpe, no letra a letra como si el profesor estuviera
  escribiendo en vivo.
- **Sin confirmación explícita por escritura individual**, a diferencia
  del clic (ver "Política de escritura remota" en `docs/security.md`
  para la justificación). El nivel `input` en sí es el paso de
  consentimiento; no hay una segunda confirmación por cada
  `set_value`/`append_text`/`clear` como si la hay por cada clic.
- **Sin límite de frecuencia para `input_request`/`input_result`**, mismo
  análisis que `click_request`/`click_result` en la Fase 5.
- **`isBlockedPageForInput()` usa una lista fija de fragmentos de ruta**
  (`/mod/quiz/attempt`, `/mod/quiz/summary`, `/mod/quiz/review`,
  `/mod/assign/view`). Otras actividades evaluables de Moodle (por
  ejemplo, algunos plugins de tipo `mod` de terceros con su propia
  página de intento) no están cubiertas por esta lista concreta si no
  comparten esos mismos fragmentos de URL.

## Nuevas tras completar el MVP — scroll dirigido por el profesor

- **Depende de que la reconstrucción del profesor tenga la misma altura
  de documento que la página real del alumno.** Las coordenadas de
  scroll que envía el profesor son absolutas (`scrollX`/`scrollY` de su
  propio `iframe`), no relativas; si el contenido reconstruido difiere
  en altura del real (por ejemplo, una imagen que aún no ha cargado en
  uno de los dos lados), el destino puede no ser exactamente el mismo
  punto visual, aunque sí el mismo entorno del documento.
- **Sin límite de frecuencia distinto del que ya tenía `scroll`.**
  `scroll_request` reutiliza la misma ventana de 150 ms que el scroll
  del alumno; no se ha ajustado de forma independiente.
- **La guarda anti-eco usa un temporizador fijo (50 ms), no una
  comprobación semántica de "es la misma posición que acabo de
  aplicar".** En una red muy lenta, en teoría un evento de scroll
  legítimo podría llegar justo durante esa ventana de 50 ms y perderse
  silenciosamente; en la práctica el margen es amplio comparado con la
  latencia normal de un evento `scroll` del navegador tras `scrollTo()`.
- **No se ha verificado en un navegador real que el listener `scroll`
  adjuntado a `contentWindow` del `iframe` sandbox se comporte como se
  espera**, mismo razonamiento que otros supuestos técnicos de
  `allow-same-origin` ya señalados para cursor/resaltado (Fase 3) y CSS
  (Fase 4) — ver los pasos 63-67 de `docs/testing.md`.

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
