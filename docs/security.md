# Seguridad

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

Añadidos en la Fase 3, con cursor y resaltado bidireccionales:

- Un **alumno** intentando empujar eventos `cursor`/`highlight` (es decir,
  "hablar" como si fuera el profesor) — antes de esta fase solo el
  alumno podía empujar nada, así que este riesgo no existía todavía.
- Un **profesor** intentando empujar `page`/`scroll` (suplantar al
  alumno) o leer eventos que en realidad generó él mismo.
- Selectores de resaltado que, al resolverse en la página real del
  alumno, apunten a un elemento distinto del que el profesor creyó estar
  señalando (colisión de estructura entre la foto y la página real).

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

Añadidos en la Fase 5, con clic remoto y niveles de consentimiento — este
es el cambio de fase con más superficie de riesgo nueva hasta ahora,
porque por primera vez el profesor puede hacer que algo ocurra en el
navegador del alumno:

- Un profesor intentando `cursor`/`highlight`/`click_request` **sin que
  el alumno haya concedido el nivel correspondiente** — el riesgo que
  motiva todo el sistema de niveles de esta fase.
- Un elemento de la página real del alumno que resulte destructivo
  (eliminar, enviar un cuestionario, cambiar la contraseña...) y que el
  profesor consiga que se ejecute un clic sobre él sin que el alumno se
  dé cuenta de lo que estaba aceptando.
- Un selector que, al resolverse, apunte a un elemento ambiguo o distinto
  del que el profesor creyó señalar, y que ese clic equivocado tenga
  consecuencias.
- Un `click_result` falso (el alumno, o un cliente manipulado en su
  nombre, reportando `clicked` sin haber ejecutado nada) — bajo impacto
  real porque el profesor solo ve una notificación de texto, no obtiene
  ningún control adicional a partir de ese resultado.

Añadidos en la Fase 6, con escritura remota:

- Un profesor intentando `input_request` **sin que el alumno haya
  concedido el nivel `input`** — mismo riesgo que motivó el sistema de
  niveles en la Fase 5, ahora en su escalón más alto.
- El profesor usando `input_request` para **leer** algo del alumno: no es
  posible por diseño (el payload de `input_request` solo lleva lo que el
  profesor decide escribir, nunca hay un canal de vuelta con el valor
  actual del campo), pero es un riesgo que vale la pena nombrar
  explícitamente porque "escritura remota" suena, a primera vista, como
  si pudiera implicar lectura.
- El profesor escribiendo en un campo sensible (contraseña, correo,
  campo oculto, campo con autocompletado de tarjeta/dirección) si
  `canSetValue()` tuviera un fallo — el riesgo que motiva la lista de
  bloqueo dedicada de esta fase, más estricta que la de clic porque aquí
  el "elemento seguro" no basta: hay que mirar también el *tipo* y el
  *nombre* del campo.
- Escribir en páginas de examen/entrega (intento de cuestionario, envío
  de tarea) aunque el campo individual pareciera inocuo — motivó el
  bloqueo por página completa (`isBlockedPageForInput()`), no solo por
  campo.
- Un `input_result` falso, mismo análisis de bajo impacto que
  `click_result` falso.

Añadido tras completar el MVP, con scroll dirigido por el profesor:

- Un profesor intentando `scroll_request` **sin nivel `pointer`** — mismo
  riesgo que `cursor`/`highlight`, gateado con el mismo mecanismo.
- Un **bucle de eco** entre el scroll que el alumno ya empujaba (Fase 4)
  y el nuevo `scroll_request` del profesor: aplicar el uno podría
  disparar el listener del otro y reenviarlo, generando tráfico
  redundante creciente en vez de estabilizarse. No es un riesgo de
  seguridad (ambos lados exigen rol/nivel correctos para cada dirección),
  pero sí de estabilidad; ver "Guarda anti-eco bidireccional" en
  `docs/architecture.md` para la mitigación.
- Impacto de un `scroll_request` malicioso o descontrolado: bajo — como
  mucho desplaza la ventana del alumno a una posición molesta dentro de
  la misma página, sin ejecutar ninguna acción ni exponer ningún dato
  nuevo.

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

## Consentimiento

Hasta la Fase 4, ninguna acción del profesor requería consentimiento
explícito más allá de aceptar la sesión — el alumno controlaba su propia
solicitud/sesión y podía finalizarla en cualquier momento, pero cursor y
resaltado funcionaban sin ninguna puerta propia.

Desde la Fase 5, cuatro niveles (`view` < `pointer` < `click` < `input`,
`classes/local/control_level.php`), cada uno incluyendo los anteriores:

- `view` (por defecto, siempre): el profesor solo observa `page`/`scroll`.
- `pointer`: además, cursor, resaltado y (desde la ampliación posterior
  al MVP) que el profesor dirija el scroll de la página real del alumno.
- `click`: además, el profesor puede solicitar un clic (con confirmación
  del alumno en cada uno, sin excepción, en este MVP).
- `input`: además, el profesor puede escribir en un pequeño conjunto de
  campos de texto no sensibles (Fase 6) — sin confirmación explícita por
  escritura individual, a diferencia del clic; ver "Política de escritura
  remota" más abajo para por qué esa diferencia es intencional.

Solo el propio alumno puede subir o bajar su nivel
(`session_manager::set_control_level()`, valida que quien llama sea
`studentid`), desde los botones de la barra de estado persistente, en
cualquier momento durante la sesión, con efecto inmediato — es la
"interfaz de revocación inmediata" que pide el documento base: "Revocar
todo" siempre vuelve a `view` en una sola llamada, no hay que bajar
nivel a nivel.

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
`cursor`, `highlight`, `resync_request`, `click_request`, `click_result`,
`input_request`, `input_result`, `scroll_request`. Cualquier otro valor
(`eval`, `script`, `html`, etc.) es rechazado con `errorinvalideventtype` antes de llegar a
almacenarse. También se valida el tamaño (`MAX_PAYLOAD_BYTES`, 260 000
bytes de JSON) y, para `page`, el propio contenido HTML (y, desde la
Fase 4, el HTML del modal si lo hay, y las URLs de CSS) se sanea/filtra
antes de guardar (ver "Saneamiento de HTML" más abajo). Desde la Fase 6,
`input_request` valida además que `action` sea uno de
`event_manager::INPUT_ACTIONS` (`set_value`, `append_text`, `clear`) —
cualquier otro valor se rechaza igual que un tipo de evento desconocido
— y trunca `value` a `MAX_INPUT_VALUE_LENGTH` (4000 caracteres) en vez de
rechazar el evento entero, porque un texto largo no es un intento de
abuso, es simplemente más de lo que hace falta escribir de una vez. Las
acciones de las páginas (`request`, `cancel`, `accept`, `enter`,
`finish`) siguen validándose igual que en la Fase 1, con `PARAM_ALPHA` y
una lista fija reconocida.

Cada tipo de evento tiene, además, un **rol autorizado a emitirlo**
(`polling_transport::ROLE_EVENT_TYPES`): el alumno empuja
`page`/`scroll`/`click_result`/`input_result`; el profesor
`cursor`/`highlight`/`resync_request`/`click_request`/`input_request`/
`scroll_request`. Que el usuario sea participante de la sesión no
basta — si un alumno intenta empujar un `cursor`, se rechaza igual que
si no perteneciera a la sesión en absoluto, y se registra como
`access_denied` con motivo `wrongrole`.

Desde la Fase 5, `cursor`/`highlight`/`click_request` exigen además el
**nivel de consentimiento** correspondiente (`pointer` o `click`); desde
la Fase 6, `input_request` exige `input`; y `scroll_request` exige
`pointer`, igual que `cursor`/`highlight` (ver "Consentimiento" arriba
para por qué se reutiliza ese nivel en vez de uno nuevo). Sin el nivel
correspondiente se rechaza con `errorinsufficientlevel` y se audita
como `access_denied` con motivo `insufficientlevel` — un motivo
distinto de `wrongrole`, porque la causa es distinta (rol correcto,
consentimiento insuficiente).

## Límite de frecuencia (Fase 3, ampliado en Fase 4 y tras el MVP)

`rate_limiter::is_allowed()` exige al menos 50 ms entre eventos `cursor`
y 150 ms entre eventos `scroll` o `scroll_request` de la misma sesión,
respaldado por una caché de aplicación (no por la tabla de eventos, cuyo
`timecreated` solo tiene resolución de un segundo). Un evento que llega
demasiado pronto **no se guarda ni se lanza un error**: `record_event()`
devuelve `null` y el llamador AJAX responde con éxito de todos modos
(`id: 0`), porque llegar un poco rápido no es un intento de abuso, es
tráfico normal de un ratón en movimiento o de un scroll continuo.
`page`, `highlight` y `resync_request` no tienen límite de frecuencia
propio (el cliente ya limita `page` razonablemente, y los otros dos solo
se disparan por un
clic o por una recuperación de conexión, no continuamente).

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

## Política de clic remoto (Fase 5)

`amd/src/interaction_policy.js::canClick(element)` es la única función
que decide si un clic remoto puede ejecutarse, y solo puede vivir en el
cliente: el servidor nunca recibe el elemento DOM resuelto, solo un
selector de texto, así que no tiene con qué evaluar esta política — ver
"Riesgos conocidos" para la brecha de pruebas que esto implica.

**Lista blanca primero** — debe cumplir al menos una:

- Enlaces (`<a href>`).
- Botones que no envíen un formulario (`type="button"`, o cualquier
  `<button>` fuera de un `<form>`).
- Pestañas (`role="tab"` o dentro de `role="tablist"`).
- Disparadores de acordeón/desplegable/pestaña de Bootstrap
  (`data-toggle`/`data-bs-toggle` con valor `collapse`, `dropdown` o
  `tab`).
- Marcador explícito `data-remotesupport-safe` (reservado para elementos
  que el propio plugin marque en el futuro; hoy no aparece en contenido
  estándar de Moodle).

**Lista de bloqueo después** — basta con cumplir una para bloquear,
aunque estuviera en la lista blanca:

- Cualquier envío de formulario: `type="submit"`, o un `<button>` sin
  `type="button"` dentro de un `<form>` (por defecto es `submit`).
- `input[type="file"]` (subidas).
- El atributo `download` (descargas).
- Enlaces con esquema `javascript:`, o que no sean del mismo origen que
  el sitio, o cuya ruta contenga fragmentos como `/admin/`, `/login/`,
  `delete`, `remove`, `logout`, `unenrol`, `password`, `/user/edit`.
- Texto visible (o `aria-label`/`title`) que contenga palabras clave de
  la lista de bloqueo: *eliminar*, *borrar*, *enviar*, *confirmar
  entrega*, *finalizar intento*, *comprar*, *contraseña*, *correo
  electrónico*, *logout*... (bilingüe, en minúsculas, coincidencia de
  subcadena).
- Cualquier cosa dentro de un `<iframe>` — estructuralmente inalcanzable
  vía `document.querySelector()` sobre el documento superior de todos
  modos, pero comprobado explícitamente igual.

Nada de esto se ejecuta nunca por coordenadas de píxel: siempre
`element.click()` sobre el elemento que resolvió el selector.

## Política de escritura remota (Fase 6)

`amd/src/interaction_policy.js::canSetValue(element)` es la función
equivalente a `canClick()` pero para escritura, y vive en el mismo
módulo por la misma razón: el documento base pide una política
centralizada, no dispersa. También solo puede vivir en el cliente, por
el mismo motivo que `canClick()` (ver "Riesgos conocidos").

**Bloqueo por página completa, antes que por elemento.**
`isBlockedPageForInput()` comprueba primero si la ruta actual contiene
`/mod/quiz/attempt`, `/mod/quiz/summary`, `/mod/quiz/review` o
`/mod/assign/view` — si es así, se bloquea cualquier escritura sin llegar
siquiera a evaluar el elemento. El clic remoto no tiene un bloqueo
equivalente por página porque un clic de navegación (por ejemplo, una
pestaña) sigue siendo razonable ahí; escribir en un campo de esas páginas
no lo es nunca en este MVP, sea el campo que sea.

**Lista blanca, más estrecha que la del clic** — debe cumplir todas:

- Etiqueta `<textarea>`, o `<input>` con `type="text"` o `type="search"`
  (cualquier otro `type` de `<input>` queda fuera por no estar en la
  lista blanca, no solo por la lista de bloqueo).
- No deshabilitado (`disabled`) ni de solo lectura (`readonly`).
- No dentro de un `<iframe>` (`element.closest('iframe')`).

**Lista de bloqueo después** — basta con cumplir una para bloquear:

- `type` en `password`, `email`, `hidden`, `file`, `tel`, `number`
  (defensa en profundidad: ya excluidos por la lista blanca de tipo, pero
  comprobados también explícitamente por si la lista blanca cambiara).
- `autocomplete` con un valor sensible: `current-password`,
  `new-password`, `cc-number`, `cc-csc`, `cc-exp`, `email`, `tel`.
- `name` o `id` que contenga una palabra clave bloqueada: `password`,
  `pass`, `email`, `token`, `secret`, `key`, `card`, `csrf`, `sesskey`
  (bilingüe/en minúsculas, coincidencia de subcadena — misma heurística
  que la lista de palabras clave del clic, con las mismas limitaciones).

A diferencia del clic, no hay lista blanca de "acciones seguras conocidas
por texto visible": el criterio aquí es enteramente estructural (tipo de
campo + nombre + página), porque el texto visible de una etiqueta
(`<label>`) no dice nada fiable sobre si el campo en sí es sensible.

## Elementos prohibidos

Ver "Política de clic remoto" arriba para la lista de bloqueo del clic
remoto, y "Política de escritura remota" para la de escritura. A nivel de
captura, ver la lista más arriba en este documento.

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
- **Límite de frecuencia solo para `cursor` y `scroll`**: `page`,
  `highlight` y `resync_request` no tienen límite de frecuencia propio en
  el servidor — solo el `debounce`/`throttle` del cliente (o, para
  `highlight`/`resync_request`, el hecho de que solo se disparan por un
  clic o una recuperación de conexión). Un usuario podría saltarse el
  límite del cliente manipulando la petición directamente; el límite de
  tamaño por evento y el hecho de que solo se pueda escribir en sesiones
  propias (con el rol correcto) acotan el daño a esa única sesión.
- **Ancho de banda por foto completa**: cada evento `page` reenvía el
  contenido principal completo (hasta 200 000 caracteres saneados), no un
  diff. Es una decisión deliberada de simplicidad (ver
  `docs/decisions.md`), pero implica más tráfico que un enfoque
  incremental si la página es grande y cambia a menudo.
- **`resync_request` sin límite de frecuencia**: en teoría un profesor
  (o alguien que consiguiera su sesión) podría disparar resincronizaciones
  completas repetidamente. En la práctica solo se dispara una vez por
  recuperación de conexión desde el cliente oficial, y el coste de cada
  una es el mismo que el latido periódico normal (una foto `page`), así
  que no es una vía de amplificación real.
- **Ni la política de clic remoto ni la de escritura remota tienen
  equivalente en PHPUnit**: es la brecha de pruebas más importante del
  plugin, no una más. `interaction_policy.js` solo puede evaluarse con el
  elemento DOM real resuelto, que el servidor nunca ve — tanto
  `click_request` como `input_request` llegan al servidor como un
  selector de texto (más, en `input_request`, una `action` y un `value`),
  sin contexto para decidir nada sobre el elemento. Toda la garantía de
  "un conjunto pequeño y claramente definido de clics/escrituras seguros"
  descansa en este único archivo cliente, sin red de seguridad
  automatizada. Ver `docs/limitations.md`.
- **`audit_manager::remote_click()` estuvo definido pero nunca invocado
  durante toda la Fase 5**: el resultado de un clic remoto nunca se
  registró en el log de auditoría permanente pese a que el código para
  hacerlo ya existía, porque ningún llamador lo invocaba. Se detectó y
  corrigió al construir la Fase 6 (`polling_transport::push_event()`
  ahora llama a `remote_click()`/`remote_input()` justo después de
  guardar con éxito un `click_result`/`input_result`), con una prueba de
  regresión explícita (`test_click_result_triggers_remote_click_audit_event`,
  `test_input_result_triggers_remote_input_audit_event`) que redirige
  eventos Moodle y comprueba que se disparan. Vale la pena tenerlo en
  cuenta como patrón de riesgo: un método de auditoría definido no implica
  que esté conectado a ningún flujo real.
- **Heurística de palabras clave, no semántica**: el bloqueo por texto
  visible es una coincidencia de subcadena sobre una lista fija; un
  botón "Eliminar" en inglés como "Delete" está cubierto, pero un tema o
  idioma con una palabra distinta para la misma acción destructiva no lo
  estaría. Es una red de seguridad adicional sobre las comprobaciones
  estructurales, no la única defensa.
- **Confirmación de clic sin caducidad de sesión visible para el
  alumno**: si el alumno no responde en 15 segundos, se rechaza en
  silencio (`declined`) — no hay ninguna notificación adicional de que
  "se te pasó el tiempo", solo desaparece el diálogo.
