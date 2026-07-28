# Seguridad

**Nota: este plugin es deliberadamente de solo visualización.** El
profesor no puede señalar, hacer clic ni escribir en la página del
alumno. Ver `docs/decisions.md` para el porqué de esta decisión y qué
código/superficie de amenaza se retiró junto con ella.

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

## Capacidades

`local/remotesupport:requestassistance` (alumno, contexto curso),
`:provideassistance` (profesor, contexto curso), `:viewactivesessions`
(profesor, contexto curso), `:managesessions` (manager, contexto sistema).
Todas las comprobaciones pasan por `permission_manager`; ninguna otra
clase llama a `require_capability()`/`has_capability()` directamente sobre
capacidades del plugin.

Además de la capacidad, cada operación sobre una sesión concreta comprueba
la propiedad: `session_manager` exige que quien cancela sea el
`studentid`, que quien acepta tenga la capacidad en el curso de esa
solicitud (no en cualquier curso), y que quien cierra o entra sea el
`studentid`, el `teacherid`, o tenga `managesessions`.

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
`resync_request`, `chat_message`. Cualquier otro valor (`eval`, `script`,
`html`, etc.) es rechazado con `errorinvalideventtype` antes de llegar a
almacenarse. También se valida el tamaño (`MAX_PAYLOAD_BYTES`, 600 000
bytes de JSON) y, para `page`, el propio contenido HTML (y, desde la
Fase 4, el HTML del modal si lo hay, y las URLs de CSS) se sanea/filtra
antes de guardar (ver "Saneamiento de HTML" más abajo). `chat_message`
requiere un campo `message` de texto no vacío (tras recortar espacios),
truncado a `MAX_CHAT_MESSAGE_LENGTH` (1000 caracteres) — siempre texto
plano, nunca pasa por el saneador de HTML porque nunca se interpreta
como HTML: el cliente lo pinta con `textContent`. Las acciones de las
páginas (`request`, `cancel`, `accept`, `enter`, `finish`) siguen
validándose igual que en la Fase 1, con `PARAM_ALPHA` y una lista fija
reconocida.

Cada tipo de evento tiene, además, un **rol autorizado a emitirlo**
(`polling_transport::ROLE_EVENT_TYPES`): el alumno empuja `page`/
`scroll`/`chat_message`; el profesor `resync_request`/`chat_message`.
Que el usuario sea participante de la sesión no basta — si un alumno
intenta empujar un `resync_request`, se rechaza igual que si no
perteneciera a la sesión en absoluto, y se registra como `access_denied`
con motivo `wrongrole`.

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
`scroll` y 300 ms entre eventos `chat_message` de la misma sesión,
respaldado por una caché de aplicación (no por la tabla de eventos,
cuyo `timecreated` solo tiene resolución de un segundo). Un evento que
llega demasiado pronto **no se guarda ni se lanza un error**:
`record_event()` devuelve `null` y el llamador AJAX responde con éxito
de todos modos (`id: 0`), porque llegar un poco rápido no es un intento
de abuso, es tráfico normal de un scroll continuo (o de un doble envío
accidental de chat). `page` y `resync_request` no tienen límite de
frecuencia propio (el cliente ya limita `page` razonablemente, y
`resync_request` solo se dispara por una recuperación de conexión, no
continuamente).

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

El modal capturado (Fase 4) pasa por el mismo `html_sanitizer::sanitize()`
que el contenido principal — no hay una ruta de saneamiento separada ni
más permisiva para él. Las URLs de CSS (Fase 4) no se sanean como HTML;
se filtran con una comprobación de prefijo: solo se conservan las que
empiezan literalmente por `$CFG->wwwroot`, cualquier otra se descarta en
silencio antes de guardar el evento.

## Captura: qué se recoge y qué nunca se recoge

Recogido: URL relativa, título, contenido de `#region-main` (o
`main`/`body` si no existe), el modal de Moodle abierto en ese momento
(si lo hay), URLs de hojas de estilo del propio sitio, estructura del
DOM, dimensiones de viewport, posición de scroll.

Nunca recogido, ni siquiera antes de sanear: valores de campos de
formulario (se elimina el atributo `value` de todo `<input>` y se vacía
todo `<textarea>`, sin distinguir si el campo es "sensible" o no — es más
simple y más seguro que mantener una lista de qué campos sí se pueden
enviar), contenido de `<iframe>` (se elimina la etiqueta entera, nunca se
desciende dentro de un `iframe` ajeno), contraseñas, cookies, tokens.

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
- **Límite de frecuencia solo para `scroll`**: `page` y `resync_request`
  no tienen límite de frecuencia propio en el servidor — solo el
  `debounce`/`throttle` del cliente (o, para `resync_request`, el hecho
  de que solo se dispara por una recuperación de conexión). Un usuario
  podría saltarse el límite del cliente manipulando la petición
  directamente; el límite de tamaño por evento y el hecho de que solo se
  pueda escribir en sesiones propias (con el rol correcto) acotan el
  daño a esa única sesión.
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
- **`chat_message` sobrevive más tiempo que el resto de eventos, por
  diseño**: exento de `purge_stale_events()` (la purga de 2 minutos),
  solo desaparece al cerrarse la sesión. Esto significa que, durante una
  sesión larga, el texto de la conversación completa queda en la base de
  datos durante toda su duración, no solo unos minutos — una excepción
  deliberada a la política general del plugin de no acumular datos (ver
  `docs/decisions.md`), aceptada porque un mensaje de chat, a diferencia
  de una foto de pantalla obsoleta, no se puede regenerar si se pierde.
  Sigue sin sobrevivir al cierre de la sesión.
