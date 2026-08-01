# Seguridad

**Nota: este plugin es deliberadamente de solo visualización.** El
profesor no puede señalar, hacer clic ni escribir en la página del
alumno. Ver `docs/decisions.md` para el porqué de esta decisión y qué
código/superficie de amenaza se retiró junto con ella.

**Nota (2026-07-29): la sesión se graba de forma permanente y puede
reproducirse**, una excepción deliberada a la política general de este
plugin de no conservar contenido indefinidamente — ver "Grabación
permanente de la sesión" más abajo y `docs/decisions.md` para el
razonamiento completo, incluida la colisión consciente con dos
requisitos del documento base y la capacidad `:replaysession` que
protege quién puede ver el contenido reproducido.

## Modelo de amenazas

Actores considerados desde la Fase 1:

- Un alumno intentando ver, cancelar o cerrar la solicitud/sesión de otro
  alumno.
- Un profesor sin relación con el curso intentando aceptar una solicitud o
  entrar en una sesión ajena.
- Un usuario adivinando o incrementando un `sessionid` en la URL.
- Un usuario reutilizando un enlace de `session.php` (con token) después
  de que la sesión haya terminado, o de que se haya emitido uno nuevo.

Añadidos en la Fase 2, con la transmisión de eventos de pantalla:

- El **navegador del alumno**, potencialmente manipulado (DevTools,
  petición directa a la API AJAX) para intentar enviar HTML/JS ejecutable
  que el navegador del profesor terminaría renderizando (XSS almacenado
  y reflejado a un tercero). Este es el riesgo nuevo más serio de la fase
  y el que determina el diseño del saneamiento (ver más abajo).
- Un usuario que no es ni el alumno ni el profesor de una sesión activa
  intentando empujar o leer eventos de esa sesión.
- Captura accidental de datos sensibles (contraseñas, campos ocultos,
  contenido de otro dominio) por un fallo en la lógica de selección de
  "contenido principal".
- Acumulación indefinida de eventos si una sesión no se cierra
  correctamente (pestaña cerrada, cliente colgado).

Añadidos en la Fase 4, con CSS/modal en el payload de `page` y el nuevo
evento `resync_request`:

- URLs de hoja de estilo arbitrarias reportadas por un cliente
  manipulado, que el profesor cargaría como `<link>` en su propio
  navegador si no se filtraran (posible fuga de datos vía CSS, o simple
  molestia visual con estilos ajenos).
- HTML de un modal con el mismo tipo de riesgo de XSS que el contenido
  principal, si no se saneara con la misma rigurosidad.
- Un **alumno** intentando empujar `resync_request` (no le corresponde:
  ese evento es una petición del profesor hacia el alumno, no al revés).

Añadido tras completar el MVP, con el icono de solicitudes pendientes en
el navbar:

- No introduce ninguna superficie de autorización nueva: el icono llama
  a `session_manager::get_pending_requests_for_teacher()`, el mismo
  método (y por tanto la misma comprobación de capacidad vía
  `get_user_capability_course()`) que ya usa `view.php`. No hay ruta para
  ver el número de solicitudes de un curso sin la capacidad
  `provideassistance` en él — se comprueba con una prueba dedicada
  (`test_navbar_output_empty_for_teacher_without_capability_in_any_course`).
  El riesgo real ya está cubierto por las pruebas existentes de
  `session_manager`; esta es solo una segunda vista sobre el mismo dato
  ya autorizado.

Añadido tras completar el MVP, con el ciclo de vida de solicitud/sesión
sin recarga de página y el badge de navbar sondeado en vivo:

- No se introduce ninguna superficie de autorización nueva: las ocho
  funciones externas nuevas (`classes/external/get_student_status.php` y
  compañía) son envoltorios finos que delegan siempre en
  `session_manager`/`permission_manager` — las mismas comprobaciones de
  capacidad, propiedad y estado que ya hacían `request.php`/`view.php`
  por POST/GET, ahora también alcanzables por AJAX. Se añadieron dos
  métodos nuevos a `permission_manager`
  (`require_can_view_dashboard()`, `require_can_provide_anywhere()`) para
  no duplicar en cada función externa la misma comprobación que ya hacía
  `view.php`/`lib.php` de forma inline.
- Un usuario sin la capacidad correspondiente que llame directamente a
  uno de estos web services (sin pasar por la interfaz) recibe el mismo
  `errornopermission`/`required_capability_exception` que recibiría
  intentando la URL PHP equivalente — cubierto por
  `tests/external_api_test.php` para cada una de las ocho funciones.
- El sondeo del badge de navbar (`navbar_badge.js`, cada 15 s en
  *cualquier* página de Moodle) reutiliza exactamente la misma consulta
  que ya usa `view.php` para su lista
  (`session_manager::get_pending_requests_for_teacher()`); no hay una
  segunda ruta de autorización que pudiera desincronizarse de la
  primera.
- El token de entrada de un solo uso ya no viaja pre-incrustado en el
  HTML de `request.php`/`view.php`: se pide en el momento del clic vía
  AJAX (`enter_session`/`accept_request`). No cambia el modelo de
  amenazas del propio token (sigue siendo de un solo uso, hasheado,
  ligado a rol — ver "Tokens" más abajo); solo cambia cuándo se genera.

Añadido tras completar el MVP, con el modo de captura `fullpage`:

- Superficie de captura más amplia (todo `<body>`, no solo el contenido
  principal), pero **la misma capa autoritativa de saneamiento**: pasa
  igual por `html_sanitizer::sanitize()` que el modo `main`, así que las
  garantías de siempre (nunca `<script>`, `<iframe>`, atributos `on*`,
  esquemas `javascript:`, valores de campos de formulario) no cambian —
  solo cambia cuánto HTML se sanea, no las reglas con las que se sanea.
- Riesgo nuevo, de privacidad más que de seguridad: bloques laterales o
  elementos de navegación pueden mostrar información específica del
  alumno (por ejemplo, mensajes recientes, notas, un bloque
  personalizado) que en modo `main` nunca se capturaba por estar fuera
  del contenido principal. Es una consecuencia esperada y deseada de lo
  que pide `fullpage` ("ver exactamente lo que ve el alumno"), no un
  fallo — pero es el motivo por el que el ajuste es de administración
  del sitio, decidido conscientemente, y no el modo por defecto.
  `MAIN_CONTENT_SELECTORS`, el saneador y el resto de la política de
  captura no distinguen "qué bloque es sensible", así que activar
  `fullpage` es responsabilidad de quien administra el sitio, no algo
  que un profesor o alumno puedan activar por su cuenta.
- Los límites de tamaño (`html_sanitizer::MAX_LENGTH`,
  `event_manager::MAX_PAYLOAD_BYTES`) suben para que quepa una foto de
  página completa; siguen existiendo y siguen aplicándose igual, solo
  con un techo distinto — ver `docs/decisions.md`.

Añadido tras completar el MVP, con `teacher_highlight` (el señalado de
un elemento del profesor hacia el alumno) — el primer flujo
profesor→alumno con efecto visible en la pantalla del alumno desde que
se retiró el cursor remoto en `aa58c26`:

- Un **profesor** intentando señalar un elemento cuando el sitio tiene
  `enableteacherpointer` desactivado — rechazado en el servidor, no solo
  oculto en la interfaz (ver "Eventos permitidos" más abajo).
- Un **alumno** intentando empujar `teacher_highlight` (no le
  corresponde: es una señal del profesor hacia el alumno, no al
  revés) — mismo tratamiento que un alumno intentando `resync_request`.
- Un cliente del profesor manipulado enviando su propio `ttlms` para
  que el señalado dure indefinidamente en la pantalla del alumno —
  el servidor lo sobrescribe siempre, nunca confía en el valor recibido.
- Un `selector` que, al resolverse en el DOM real del alumno, apunte a
  un elemento distinto del que el profesor realmente señaló (foto
  desactualizada, estructura cambiada) — el riesgo es puramente visual
  (confuso, nunca una acción: nunca se ejecuta ningún clic), mismo
  riesgo ya aceptado para el resaltado `hover`/`typing` existente, ver
  `docs/limitations.md`.

## Capacidades

`local/remotesupport:requestassistance` (alumno, contexto curso),
`:provideassistance` (profesor, contexto curso), `:viewactivesessions`
(profesor, contexto curso), `:viewsessionhistory` (profesor, contexto
curso, `RISK_PERSONAL` — tras el MVP, ver más abajo por qué es una
capacidad aparte y no una reutilización de `:viewactivesessions`),
`:replaysession` (profesor, contexto curso, `RISK_PERSONAL` — tras el
MVP, ver más abajo), `:deletesessionhistory` (profesor, contexto
curso, `RISK_DATALOSS` — tras el MVP, ver más abajo), `:managesessions`
(manager, contexto sistema). Todas las comprobaciones pasan por
`permission_manager`; ninguna otra clase llama a
`require_capability()`/`has_capability()` directamente sobre
capacidades del plugin.

**`:viewsessionhistory` y `:replaysession` son las únicas con
`RISK_PERSONAL` entre las de solo lectura, y son capacidades distintas
entre sí.** Ver actividad pasada de un alumno concreto, agregada a lo
largo de meses (fechas, cursos, duraciones), es un perfil de
comportamiento más revelador que ver una única sesión activa en curso —
de ahí el riesgo marcado en `:viewsessionhistory`, a diferencia de
`:viewactivesessions`, que nunca lo tuvo. Reproducir el contenido
completo grabado (pantallas reales y conversación) es más sensible
todavía que ver solo los metadatos del listado, así que `:replaysession`
es una capacidad aparte, no una reutilización de `:viewsessionhistory` —
un sitio podría, por ejemplo, conceder ver el historial a más
profesorado del que puede reproducir el contenido íntegro. Además de la
capacidad, `permission_manager::can_replay_session()`/
`require_can_replay_session()` exigen que el usuario sea concretamente
el profesor asignado a esa sesión (o tenga `managesessions`) — tenerla
en el curso no basta si la sesión es de otro profesor.

Además de la capacidad, cada operación sobre una sesión concreta comprueba
la propiedad: `session_manager` exige que quien cancela sea el
`studentid`, que quien acepta tenga la capacidad en el curso de esa
solicitud (no en cualquier curso), y que quien cierra o entra sea el
`studentid`, el `teacherid`, o tenga `managesessions`.

**`:deletesessionhistory` reutiliza exactamente la regla de
`:replaysession` (post-MVP, 2026-07-30), a propósito: nadie puede
eliminar una sesión que no podría ya reproducir.**
`permission_manager::can_delete_session_history()` es, campo por
campo, la misma comprobación que `can_replay_session()` (profesor
asignado + capacidad en el curso, o `managesessions`) — capacidad
separada de todas formas, para que un administrador pueda revocar el
borrado sin tocar la reproducción. `session_manager::delete_sessions()`
revalida esto (y que la sesión esté cerrada) por cada id, sin confiar
en que quien llama ya lo comprobó, y es todo-o-nada: si un id del
lote falla cualquier comprobación, no se borra ninguno del lote. El
borrado reutiliza el mismo purgado ya usado por la baja de datos por
privacidad (`local_remotesupport_track` y `local_remotesupport_event`
de esa sesión), y queda auditado (`session_deleted`).

## Tokens

- Generados con `random_bytes(32)` (`token_manager::generate()`), 256 bits
  de entropía.
- Solo se persiste `hash('sha256', $token)`, nunca el token en claro.
- Cada parte (alumno/profesor) tiene su propia columna de hash
  (`tokenhashstudent`/`tokenhashteacher`): pedir un enlace nuevo no
  invalida el de la otra parte.
- Un token solo es válido mientras la sesión está en `accepted` o
  `active`; se vuelve inservible en cuanto la sesión se cierra, caduca o
  se cancela.
- El acceso a `session.php` exige capacidad + propiedad **y** token
  válido; el token no es el único mecanismo de autorización, es una capa
  adicional pensada para servir de credencial al futuro transporte en
  tiempo real (Fase 2+).

## Eventos permitidos

Lista blanca cerrada en `event_manager::EVENT_TYPES`: `page`, `scroll`,
`cursor`, `student_click`, `resync_request`, `chat_message`,
`teacher_highlight`. Cualquier otro valor (`eval`, `script`, `html`,
etc.) es rechazado con
`errorinvalideventtype` antes de llegar a almacenarse. También se
valida el tamaño (`MAX_PAYLOAD_BYTES`, 600 000 bytes de JSON) y, para
`page`, el propio contenido HTML (y, desde la Fase 4, el HTML del modal
si lo hay, y las URLs de CSS) se sanea/filtra antes de guardar (ver
"Saneamiento de HTML" más abajo). `cursor` y `student_click` reciben la
misma comprobación ligera que `scroll` (campos `x`/`y` presentes y
numéricos) — ninguno es HTML, así que no pasan por el saneador.
`cursor` acepta además un campo opcional `hover` (el selector del
elemento clicable bajo el ratón del alumno, añadido tras el MVP —
ver `docs/decisions.md`): si es una cadena, se acota a
`MAX_HOVER_SELECTOR_LENGTH` (1500 caracteres); si no lo es, se
descarta. Un `hover` inválido o desproporcionado nunca rechaza el
evento completo, a diferencia de `x`/`y` — es auxiliar a la posición,
no la razón de ser del evento. No se sanea como HTML porque no lo es:
nunca se inserta como marcado, solo se usa como argumento de
`querySelector()` en el `iframe` ya aislado del profesor, envuelto en
su propio `try`/`catch`. `cursor` acepta, con exactamente la misma
validación, un segundo campo opcional `typing` (el selector del campo
de texto que el alumno tiene enfocado, añadido tras el MVP — ver
`docs/decisions.md`): nunca lleva el valor tecleado, solo qué campo
es, y password/hidden quedan excluidos ya en el propio cliente
(`event_capture.js`) antes de que llegue nada al servidor.
`teacher_highlight` (añadido tras el MVP, ver
`docs/architecture.md`) requiere un campo `selector` de texto no vacío
(mismo tratamiento que `hover`/`typing`: acotado a
`MAX_HOVER_SELECTOR_LENGTH`, nunca sanea como HTML porque nunca lo es,
solo argumento de `querySelector()`), y su campo `ttlms` **nunca se
toma del cliente**: `event_manager::record_event()` lo sobrescribe
siempre a partir del ajuste `local_remotesupport/teacherpointerttlseconds`
vigente en el momento de guardar el evento, precisamente para que un
cliente del profesor modificado no pueda hacer que su propio señalado
dure más de lo que el administrador del sitio permite.
`chat_message`
requiere un campo `message` de texto no vacío (tras recortar espacios),
truncado a `MAX_CHAT_MESSAGE_LENGTH` (1000 caracteres) — siempre texto
plano, nunca pasa por el saneador de HTML porque nunca se interpreta
como HTML: el cliente lo pinta con `textContent`. Las acciones de las
páginas (`request`, `cancel`, `accept`, `enter`, `finish`) siguen
validándose igual que en la Fase 1, con `PARAM_ALPHA` y una lista fija
reconocida.

Cada tipo de evento tiene, además, un **rol autorizado a emitirlo**
(`polling_transport::ROLE_EVENT_TYPES`): el alumno empuja `page`/
`scroll`/`cursor`/`student_click`/`chat_message`; el profesor
`resync_request`/`chat_message`.
Que el usuario sea participante de la sesión no basta — si un alumno
intenta empujar un `resync_request`, se rechaza igual que si no
perteneciera a la sesión en absoluto, y se registra como `access_denied`
con motivo `wrongrole`.

`teacher_highlight` añade una segunda condición encima del rol,
comprobada aparte en `push_event()` porque una constante `ROLE_EVENT_TYPES`
no puede consultar configuración: además de ser el profesor de la
sesión, el ajuste `local_remotesupport/enableteacherpointer` tiene que
estar activo — **desactivado por defecto**. Si el ajuste está
desactivado, se rechaza exactamente igual (`errornopermission`,
`access_denied` con motivo `wrongrole`) tanto si lo intenta el profesor
como si lo intenta el alumno; la interfaz del profesor (`event_player.js`)
ni siquiera crea el botón correspondiente cuando el ajuste está
desactivado, pero esa es solo la mitad de la defensa — el servidor no
confía en que el cliente respete esa ausencia de botón.

`chat_message` es también el único tipo que **no** excluye al propio
emisor al leer: `get_events_since()` normalmente filtra "no me devuelvas
mis propios eventos" (el alumno nunca necesita ver su propio `page`/
`scroll` reflejado de vuelta), pero un chat necesita que cada
participante vea la conversación completa, incluidos sus propios
mensajes. Esto no relaja ninguna comprobación de autorización: solo
altera qué fila de una sesión ya validada como propia se devuelve, no
quién puede pedir qué sesión.

## Límite de frecuencia

`rate_limiter::is_allowed()` exige al menos 150 ms entre eventos
`scroll`, 150 ms entre eventos `cursor`, 100 ms entre eventos
`student_click`, 300 ms entre eventos `chat_message` y 200 ms entre
eventos `teacher_highlight` de la misma sesión, respaldado por una
caché de aplicación (no por la tabla de
eventos, cuyo `timecreated` solo tiene resolución de un segundo). Un
evento que llega demasiado pronto **no se guarda ni se lanza un
error**: `record_event()` devuelve `null` y el llamador AJAX responde
con éxito de todos modos (`id: 0`), porque llegar un poco rápido no es
un intento de abuso, es tráfico normal de un scroll continuo (o de un
doble envío accidental de chat). `page` y `resync_request` no tienen
límite de frecuencia propio (el cliente ya limita `page`
razonablemente, y `resync_request` solo se dispara por una recuperación
de conexión, no continuamente).

El suelo de `student_click` es más bajo que el de `scroll`/`cursor`
(100 ms frente a 150 ms) simplemente porque un clic real, a diferencia
de un movimiento de ratón, nunca necesita muestrearse — el suelo aquí
existe solo como defensa frente a un cliente modificado que dispare
clics falsos en bucle, no como un límite pensado para suavizar tráfico
legítimo (`event_capture.js` no aplica ningún throttling propio a los
clics, cada clic real se envía).

El suelo de 150 ms para `cursor` es independiente del ajuste de admin
`local_remotesupport/cursorsamplems` (200/500/1000/2000 ms, ver
`docs/architecture.md`): es una defensa en profundidad frente a un
cliente modificado que ignore su propio throttling, no el mecanismo que
gobierna la tasa normal — el valor mínimo permitido en el ajuste ya
queda por encima de este suelo, así que un cliente sin modificar nunca
lo alcanza.

La clave de la caché de límite de frecuencia incluye el **remitente**
(`sessionid_eventtype_userid`), no solo sesión y tipo — necesario desde
que `chat_message` es el primer tipo con más de un remitente posible
por sesión; con una clave compartida, un mensaje de un lado habría
podido bloquear por error la respuesta del otro si llegaba dentro de la
misma ventana.

## Saneamiento de HTML (Fase 2)

Dos capas independientes; ninguna confía en que la otra ya haya limpiado
el contenido:

1. **Servidor, autoritativa** — `html_sanitizer::sanitize()`, ejecutada
   siempre en `event_manager::record_event()` para eventos `page`, nunca
   solo en el cliente. Usa `DOMDocument` para eliminar `<script>`,
   `<iframe>`, `<object>`, `<embed>`, `<applet>`, `<noscript>`, `<link>`,
   `<meta>`; quita todos los atributos que empiecen por `on`; quita
   `href`/`src` con esquema `javascript:`; quita `value` de `<input>` y
   vacía `<textarea>`. Nunca se confía en que el cliente ya limpió nada:
   la petición AJAX la hace el propio navegador del alumno, que un
   usuario podría manipular directamente sin pasar por
   `event_capture.js`.
2. **Cliente, defensa adicional** — el visor del profesor
   (`event_player.js`) renderiza cada foto con `iframe.srcdoc` dentro de
   un `<iframe sandbox="allow-same-origin">` (sin `allow-scripts`, sin
   `allow-forms`, sin `allow-popups`). Sin `allow-scripts`, la
   especificación HTML desactiva toda ejecución de script en ese frame de
   forma incondicional (etiquetas `<script>`, manejadores en línea,
   `javascript:`), así que aunque el saneamiento del servidor tuviera un
   fallo, el contenido seguiría sin poder ejecutarse.

El modal capturado (Fase 4) y los elementos `position: fixed` extraídos
del contenido (`payload.fixed`, añadido tras el MVP como fix de
precisión del cursor — ver `docs/architecture.md`/`docs/decisions.md`)
pasan por el mismo `html_sanitizer::sanitize()` que el contenido
principal — no hay una ruta de saneamiento separada ni más permisiva
para ninguno de los dos. Las URLs de CSS (Fase 4) no se sanean como HTML;
se filtran con una comprobación de prefijo: solo se conservan las que
empiezan literalmente por `$CFG->wwwroot`, cualquier otra se descarta en
silencio antes de guardar el evento.

**`payload.inlineCss` (añadido tras el MVP, mejora de precisión — ver
`docs/decisions.md`) es CSS, no HTML, y se sanea de otra forma.** PHP no
trae un parser de CSS equivalente a `DOMDocument`, así que
`event_manager::sanitize_inline_css()` usa expresiones regulares para
eliminar `@import` (traería una hoja de estilos externa entera al
navegador del profesor) y cualquier `url(...)` (podría hacer que el
navegador del profesor solicitara una URL arbitraria al renderizar la
reconstrucción — imágenes de fondo, `@font-face`, cualquier otro uso
legítimo se pierde con ello). No es un parser real, es una limpieza
basada en texto — defensa en profundidad, no la única barrera: el
`iframe` sandbox sigue bloqueando toda ejecución de scripts
independientemente de lo que contenga el CSS. En el cliente,
`screen_renderer.js` además rompe cualquier secuencia `</style` literal
antes de insertar el texto dentro de una etiqueta `<style>` del
`srcdoc`, para que no pueda cerrarla antes de tiempo e inyectar marcado
arbitrario — el mismo tipo de precaución que ya se aplicaba al escapar
comillas en las URLs de `<link>`.

## Captura: qué se recoge y qué nunca se recoge

Recogido: URL relativa, título, contenido de `#region-main` (o
`main`/`body` si no existe), el modal de Moodle abierto en ese momento
(si lo hay), URLs de hojas de estilo del propio sitio y CSS inline
(añadido tras el MVP), estructura del DOM, dimensiones de viewport,
posición de scroll, posición del cursor del ratón mientras se mueve,
posición de cada clic (ambos añadidos tras el MVP — coordenadas `x`/`y`
de viewport, nunca el texto de lo que se pulsó). La posición de cada
clic no lleva asociado ningún selector ni `id` del elemento pulsado,
solo el punto. La posición del cursor sí, desde la mejora de resaltado
(añadida tras el MVP, ver `docs/decisions.md`): un selector CSS
(`id`, o una ruta estructural corta) del elemento clicable bajo el
ratón, si hay alguno — nunca su texto ni ningún otro contenido, solo
lo necesario para poder volver a localizar ese mismo elemento dentro
del propio DOM ya capturado.

Nunca recogido, ni siquiera antes de sanear: valores de campos de
formulario (se elimina el atributo `value` de todo `<input>` y se vacía
todo `<textarea>`, sin distinguir si el campo es "sensible" o no — es más
simple y más seguro que mantener una lista de qué campos sí se pueden
enviar), contenido de `<iframe>` (se elimina la etiqueta entera, nunca se
desciende dentro de un `iframe` ajeno), contraseñas, cookies, tokens.

## Grabación permanente de la sesión (añadido tras el MVP)

`local_remotesupport_track` guarda de forma permanente (dentro de la
ventana de retención) los mismos eventos `page`/`scroll`/`cursor`/
`student_click`/`chat_message` ya validados y saneados que se
transportan en vivo — nada nuevo pasa por saneado aquí, `track_manager`
reutiliza el payload ya limpio de `event_manager`. Esto significa que
el contenido capturado (HTML principal saneado, nunca valores de campos
de formulario, contraseñas, cookies ni tokens — ver "Captura" y
"Saneamiento de HTML" arriba, más el texto de la conversación de chat
desde que se añadió la reproducción, más la posición del cursor del
ratón y de cada clic desde que se añadieron esas funcionalidades) queda
retenido en base de datos durante semanas o meses, no minutos,
invirtiendo deliberadamente la política de purga rápida que rige el
resto del plugin.

- **`cursor` y `student_click` son excepciones conscientes a "no grabar
  cada movimiento del ratón"** (guía general del documento base del
  proyecto). Se acotó el coste de `cursor` con dos decisiones: solo se
  envía mientras el ratón se está moviendo de verdad (atado al evento
  `mousemove` del navegador, no a un temporizador — un alumno inactivo
  no genera filas), y la tasa de muestreo de un ratón en movimiento es
  un ajuste de administración (`local_remotesupport/cursorsamplems`),
  no un valor fijo agresivo. `student_click` no necesita ninguna de
  esas dos mitigaciones: un clic ya es, por su propia naturaleza, un
  evento discreto e infrecuente — no hay nada que muestrear ni
  atenuar. Ver `docs/decisions.md`.

- **Endpoint de lectura gateado por `:replaysession`, no por
  `:viewsessionhistory`.** `get_session_track` (AJAX), `sessionreplay.php`
  y `sessionchat.php` (añadida después, ver `docs/decisions.md`) exigen
  la capacidad y la propiedad de la sesión — ver "Capacidades" arriba.
  Solo devuelven algo si, además, la sesión está `closed` (una sesión
  activa o pendiente se rechaza: la vista en vivo, con su propia
  autorización, es el camino para eso). `sessionchat.php` no introduce
  ninguna comprobación nueva: reutiliza exactamente
  `permission_manager::require_can_replay_session()`, solo cambia qué
  datos pide (`track_manager::get_chat_for_session()`, filtrado a
  `chat_message`) y cómo los renderiza (PHP/Mustache, sin AMD ni AJAX).
- **El chat se grabó permanentemente a partir de la reproducción**, revisando
  la decisión original de esta sección (grabar solo `page`/`scroll`).
  Sesiones cerradas antes de ese cambio no tienen chat grabado — no es
  que se oculte, es que nunca se guardó. Ver `docs/decisions.md`.
- **Retención administrable, no indefinida**: `local_remotesupport/
  trackretentiondays` (15/30/90/180/365 días), aplicada por la tarea
  `purge_track`.
- **Una solicitud de supresión de datos personales borra la grabación de
  inmediato**, sin esperar a la ventana de retención — ver
  `classes/privacy/provider.php`. No sobrevive, en cambio, a un cierre
  de sesión normal por diseño (`session_manager::close_session()`
  deliberadamente no la toca): esa es la diferencia central respecto a
  `local_remotesupport_event`.
- **Riesgo de reidentificación por volumen**: donde una foto de pantalla
  suelta (Fase 2) revela poco fuera de contexto, semanas de fotos de
  pantalla completas de un mismo alumno, correlacionadas por
  `sessionid`/`timecreated`, son un perfil de actividad mucho más rico
  que cualquier otro dato que este plugin haya conservado hasta ahora.
  No hay mitigación técnica adicional más allá de la retención acotada
  y el borrado por supresión — es una consecuencia directa, y aceptada
  conscientemente, del alcance elegido.

## Elementos prohibidos

No hay política de clic ni de escritura remota que mantener — el
profesor no ejecuta ninguna acción sobre la página del alumno. Lo que
queda es la lista de bloqueo de la propia captura de pantalla (ver
"Captura: qué se recoge y qué nunca se recoge" arriba y "Saneamiento de
HTML"): etiquetas eliminadas por completo tanto en el saneador
autoritativo del servidor (`html_sanitizer::BLOCKED_TAGS`) como en la
limpieza best-effort del cliente (`event_capture.js::BLOCKED_TAGS`) —
`<script>`, `<iframe>`, `<object>`, `<embed>`, `<applet>`, `<noscript>`,
`<link>`, `<meta>` — más los atributos `on*` y los esquemas
`javascript:`, y los valores de `<input>`/`<textarea>`, que nunca se
capturan.

## Redirección a la página de origen sin riesgo de open redirect (añadido tras el MVP)

Al entrar en una sesión, el alumno es redirigido a la página desde la
que pidió asistencia (`local_remotesupport_session.returnurl`, ver
`docs/architecture.md`/`docs/decisions.md`) en vez de a la portada del
curso. Como el destino de una redirección lo determina, en última
instancia, un valor que llegó desde el navegador del alumno, se trata
como una superficie de open redirect y se cierra en dos puntos, no
uno:

1. **Nunca se guarda una URL completa, solo una ruta local.** El
   propio valor que se persiste en base de datos se obtiene con
   `moodle_url::out_as_local_url()` al construir el enlace a
   `request.php` — no hay forma de que llegue a guardarse un dominio
   externo, porque nunca se le pasa la oportunidad de estar ahí.
2. **`PARAM_LOCALURL` en cada punto de entrada** (`request.php`,
   `classes/external/request_assistance.php`) — revalida igualmente el
   valor recibido, por si acaso llegara manipulado directamente sin
   pasar por los enlaces que el propio plugin construye (mismo
   principio que el resto del plugin: no confiar en que el cliente ya
   validó nada). `session.php` reconstruye el destino con
   `new moodle_url($session->returnurl)`, envuelto en un `try`/`catch`
   que cae de vuelta a la portada del curso ante cualquier valor que no
   parezca ya una ruta local válida.

## Riesgos conocidos

- **El token viaja en la URL** (`session.php?id=...&token=...`), por lo
  que puede quedar en el historial del navegador o en logs de acceso del
  servidor si no se usa HTTPS. Mitigación: exigir HTTPS en el sitio
  (responsabilidad del despliegue, no del plugin) y no registrar la
  cadena de consulta completa en logs de aplicación propios del plugin.
- **Enumeración de `sessionid`**: los identificadores son secuenciales,
  pero cada operación exige capacidad + propiedad, así que adivinar un id
  ajeno no concede acceso; como mucho revela que existe una fila con ese
  id (no a quién pertenece, gracias al mensaje de error genérico de
  `permission_manager::require_owner_or_manage()`).
- **Filas con dos titulares**: al no haber tablas separadas para
  solicitud y sesión, una fila de `local_remotesupport_session` nombra
  simultáneamente a alumno y profesor; ver
  [limitations.md](limitations.md) para cómo afecta esto al borrado de
  datos personales.
- **Coste por página de la comprobación `before_footer`**: se ejecuta en
  *toda* petición de *todo* usuario logueado del sitio (no solo de los
  participantes en una sesión), aunque sea una única consulta indexada
  por `studentid+status`. Aceptable para el objetivo de 1–20 sesiones
  simultáneas; revisar si el sitio crece mucho más.
- **Límite de frecuencia solo para `scroll`/`cursor`/`student_click`/`chat_message`/`teacher_highlight`**:
  `page` y `resync_request` no tienen límite de frecuencia propio en el
  servidor — solo el `debounce`/`throttle` del cliente (o, para
  `resync_request`, el hecho de que solo se dispara por una recuperación
  de conexión). Un usuario podría saltarse el límite del cliente
  manipulando la petición directamente; el límite de tamaño por evento y
  el hecho de que solo se pueda escribir en sesiones propias (con el rol
  correcto) acotan el daño a esa única sesión.
- **Ancho de banda por foto completa**: cada evento `page` reenvía el
  contenido principal completo (hasta 150 000 caracteres saneados en
  modo `main`, 400 000 en `fullpage`), no un diff. Es una decisión
  deliberada de simplicidad (ver `docs/decisions.md`), pero implica más
  tráfico que un enfoque incremental si la página es grande y cambia a
  menudo.
- **`resync_request` sin límite de frecuencia**: en teoría un profesor
  (o alguien que consiguiera su sesión) podría disparar resincronizaciones
  completas repetidamente. En la práctica solo se dispara una vez por
  recuperación de conexión desde el cliente oficial, y el coste de cada
  una es el mismo que el latido periódico normal (una foto `page`), así
  que no es una vía de amplificación real.
- **Bypass conocido, actualmente inerte, del filtro `javascript:` de
  `html_sanitizer::clean_attributes()`** (encontrado 2026-08-01, revisión
  adversarial de `docs/tests_todo.md` punto 6): la comprobación
  `preg_match('/^\s*javascript:/i', $attr->value)` se ejecuta sobre el
  valor del atributo tal como lo da `DOMDocument` (antes de serializar),
  así que un tabulador/salto de línea/retorno de carro incrustado dentro
  del propio esquema (`java` + TAB + `script:alert(1)`) no coincide con
  el patrón y el atributo no se elimina — técnica de bypass conocida
  (los navegadores ignoran esos caracteres de control al analizar un
  esquema de URL). Confirmado en vivo: el valor sobrevive la
  comprobación. **No es explotable hoy por dos defensas independientes**:
  (1) `DOMDocument::saveHTML()` percent-codifica esos caracteres de
  control al serializar atributos `href`/`src` (`java%09script:...`),
  lo que deja de ser un esquema `javascript:` válido para cualquier
  parser de URL real; (2) el `iframe` de reconstrucción del profesor
  tiene `pointer-events: none` permanente (nunca se desactiva, ni
  siquiera durante el modo de señalado — los listeners de esa función
  viven en `viewportWrapper`, nunca en el propio `iframe` ni en su
  `contentDocument`) y `sandbox="allow-same-origin"` sin `allow-scripts`,
  así que ningún clic real llega jamás al contenido reconstruido para
  disparar una navegación en primer lugar. Sigue siendo un defecto real
  del contrato de esa función (su propio docblock promete filtrar
  `javascript:` y no lo hace de forma fiable) — pendiente de corrección,
  no urgente dado lo anterior. Corrección previsible: normalizar
  (eliminar caracteres de control) el valor antes de aplicar la regex,
  en vez de confiar en que el serializador los neutralice como efecto
  secundario.
- **`returnurl`/`fromurl` no filtra segmentos `../`** (mismo hallazgo
  2026-08-01): `PARAM_LOCALURL` rechaza correctamente todo lo probado
  que intentara escapar de dominio (`https://evil.example`,
  `//evil.example`, `javascript:`, trucos de `usuario@evil.example` o
  de subdominio-sufijo) — confirmado con una batería de payloads contra
  `clean_param()` en vivo — pero no normaliza ni rechaza `../`, y
  `moodle_url`/`session.php` tampoco lo hacen al reconstruir el destino
  (`new moodle_url($session->returnurl)` es concatenación literal, sin
  resolver `..`). Un `fromurl` como
  `/local/remotesupport/../../otra/ruta` sobrevive intacto y produce una
  redirección real a esa otra ruta. **Nunca cruza a otro dominio**
  (confirmado: sin un `//` que abra una nueva autoridad, `..` solo puede
  cancelar segmentos de ruta dentro del mismo host) — así que no
  contradice, pero sí matiza, la sección "Redirección a la página de
  origen sin riesgo de open redirect" de arriba: esa garantía es válida
  frente a otro dominio, no frente a otra ruta del mismo sitio. Impacto
  acotado: la ruta de destino sigue sujeta a `require_login()`/las
  comprobaciones de capacidad propias de lo que sea que haya ahí.
- **`chat_message` sobrevive más tiempo que el resto de eventos, por
  diseño**: exento de `purge_stale_events()` (la purga de 2 minutos),
  solo desaparece al cerrarse la sesión. Esto significa que, durante una
  sesión larga, el texto de la conversación completa queda en la base de
  datos durante toda su duración, no solo unos minutos — una excepción
  deliberada a la política general del plugin de no acumular datos (ver
  `docs/decisions.md`), aceptada porque un mensaje de chat, a diferencia
  de una foto de pantalla obsoleta, no se puede regenerar si se pierde.
  Sigue sin sobrevivir al cierre de la sesión.
