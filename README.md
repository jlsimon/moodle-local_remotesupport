# local_remotesupport — Asistencia remota

Plugin de Moodle para que un profesor asista remotamente a un alumno dentro
de Moodle, mediante navegación compartida (co-browsing), no escritorio
remoto. Ver [docs/architecture.md](docs/architecture.md) para el diseño,
[docs/security.md](docs/security.md) para el modelo de amenazas y
[docs/limitations.md](docs/limitations.md) para lo que aún no hace.

## Estado

**Solo visualización.** El plugin implementa las Fases 1 y 2 del MVP
definido en `AGENTS.md` (solicitud/sesión, y reconstrucción de pantalla
con scroll/modales/CSS real), pero no las Fases 3, 5 y 6 ni la extensión
de scroll bidireccional que llegaron a implementarse en una versión
anterior: el profesor únicamente observa la navegación del alumno, sin
cursor remoto, resaltado, clic remoto, escritura remota ni capacidad de
mover el scroll del alumno. Esta reducción de alcance fue una decisión
explícita del usuario, documentada en `docs/decisions.md`; el código y
las pruebas de esas capacidades se conservan accesibles en el tag de git
`pre-viewonly-full-featured` por si se necesitan más adelante.

**Ampliación posterior — icono de solicitudes pendientes.** Un icono
junto a mensajes/notificaciones en la barra de navegación avisa al
profesorado de solicitudes de asistencia esperando, y lleva directamente
a la pantalla de gestión al pulsarlo.

**Ampliación posterior — botón flotante de solicitud.** Además del
enlace en el menú del curso, un botón flotante visible en cualquier
página de Moodle permite al alumno solicitar asistencia (o retomar una
solicitud ya abierta) aunque no consiga llegar al menú del curso.

**Ampliación posterior — motivo opcional.** Al solicitar asistencia, el
alumno puede añadir un breve motivo en texto libre (opcional, máx. 255
caracteres) que el profesor ve en su lista de solicitudes pendientes.

**Ampliación posterior — ajustes personales del profesor.** El icono del
navbar ahora se muestra siempre (no solo con solicitudes pendientes) y
da acceso a una pantalla de configuración personal donde el profesor
puede activar o desactivar si acepta asistencia en este momento. Si
ningún profesor de un curso la tiene activada, el alumno ve un aviso de
"sin personal de soporte disponible" en vez del botón de solicitud.

**Ampliación posterior — ciclo de vida de solicitud/sesión sin recarga
de página.** Solicitar, cancelar, aceptar y finalizar ya no dependen de
recargar `request.php`/`view.php` para enterarse de un cambio de
estado; el badge de solicitudes pendientes del navbar también se
actualiza solo. Los formularios/enlaces de siempre siguen funcionando
igual si JavaScript falla o está desactivado — ver
`docs/decisions.md`/`docs/architecture.md` para el diseño.

**Ampliación posterior — modo de captura "página completa".** Ajuste de
administración (`local_remotesupport/capturemode`) para que la
reconstrucción del profesor incluya también la navegación, los bloques
laterales y el pie de página, no solo el contenido principal. Aplica a
todas las sesiones del sitio por igual; por defecto sigue capturando
solo el contenido principal, como hasta ahora.

**Ampliación posterior — pantalla completa.** Un botón en la vista del
profesor amplía la reconstrucción a pantalla completa (Fullscreen API
nativa), reescalándose automáticamente para aprovechar el espacio
disponible. La reconstrucción no reacciona a clics ni se puede
desplazar manualmente por ninguna vía (rueda, teclado...) — su posición
solo cambia por sincronización real con el alumno.

**Ampliación posterior — chat de texto.** Caja flotante de chat entre
profesor y alumno durante una sesión activa, en ambos lados. Los
mensajes persisten mientras la sesión está activa (no solo unos
minutos, como el resto de eventos efímeros) y desaparecen al cerrarla;
no queda ningún transcript más allá de eso. Ver `docs/decisions.md`
para el porqué de este diseño frente a la exclusión de "chat complejo"
del documento base.

**Ampliación posterior — grabación permanente de la sesión (solo
almacenamiento por ahora).** La actividad de pantalla del alumno
(`page`/`scroll`) se guarda de forma permanente durante un periodo de
retención configurable (**Administración del sitio → Extensiones
locales → Asistencia remota**: 15/30/90/180/365 días), para que más
adelante alumno y profesor puedan reproducir sus sesiones pasadas. Esta
fase implementa deliberadamente **solo el almacenamiento**, sin ninguna
pantalla de listado o reproducción todavía. Se elimina de inmediato,
sin esperar a la ventana de retención, si cualquiera de los dos
participantes ejerce su derecho de supresión de datos personales.
Colisiona conscientemente con dos requisitos del documento base
(exclusión de "grabación de sesiones" y de conservar el contenido
completo de las sesiones indefinidamente) — ver `docs/decisions.md`
para el razonamiento completo y las políticas acordadas con el usuario.

**Ampliación posterior — historial de sesiones del profesor.** Nueva
página (`sessionhistory.php`, enlazada desde `view.php`) con un listado
ordenable y paginado (fecha, curso, nombre y apellidos del alumno,
duración) de las sesiones cerradas de un profesor, usando el
`\table_sql` estándar de Moodle, con un selector de elementos por página
(10/20/50/100, recordado como preferencia de usuario) igual al que usa
el propio núcleo de Moodle. Incluye una columna "#" (el id de la
sesión) que enlaza a su reproducción.

**Ampliación posterior — reproducción de sesiones grabadas.** Pulsando
la columna "#" del historial, el profesor puede reproducir una sesión
cerrada (`sessionreplay.php`): ve todas las pantallas capturadas del
alumno junto con la conversación de chat, sincronizadas en el tiempo,
con controles de reproducir/pausar, velocidad (1x/2x/4x/8x) y una barra
de progreso para saltar a cualquier punto. Requiere la capacidad
`local/remotesupport:replaysession`, aparte de `viewsessionhistory` —
ver un momento antes solo permite ver que la sesión existió; reproducirla
expone su contenido completo. El chat solo se grabó de forma permanente
a partir de esta ampliación: las sesiones cerradas antes no tienen
conversación que reproducir, solo pantalla. Ver `docs/decisions.md` para
el razonamiento completo.

**Ampliación posterior — vista de solo chat en el historial.** Junto a
la columna "#" de reproducción, `sessionhistory.php` tiene ahora una
columna "Chat" con un enlace "Ver chat" por fila, que lleva a
`sessionchat.php`: la transcripción completa de la conversación de una
sesión, sola, sin cargar ni reproducir la pantalla — una alternativa
más ligera cuando solo interesa repasar lo que se habló. Misma
capacidad y autorización que la reproducción completa.

## Requisitos

- Moodle 4.1 o posterior.
- PHP 8.0 o posterior.

## Instalación

1. Copiar (o enlazar) este directorio en `local/remotesupport` dentro del
   `dirroot` de Moodle.
2. Visitar `admin/index.php` como administrador para completar la
   instalación (crea las tablas `local_remotesupport_session`,
   `local_remotesupport_event` y `local_remotesupport_track`, las
   capacidades, los once servicios AJAX, las tareas programadas y la
   caché de límite de frecuencia).
3. Asignar las capacidades si el instalador no las deja como se espera:
   - `local/remotesupport:requestassistance` — alumnado (por defecto, vía
     el arquetipo `student`).
   - `local/remotesupport:provideassistance`,
     `local/remotesupport:viewactivesessions`,
     `local/remotesupport:viewsessionhistory` y
     `local/remotesupport:replaysession` — profesorado (por defecto, vía
     `teacher`/`editingteacher`).
   - `local/remotesupport:managesessions` — administración (por defecto,
     vía `manager`).
4. Opcional: ajustar en **Administración del sitio → Extensiones locales →
   Asistencia remota** el tiempo de caducidad de las solicitudes pendientes
   (15 minutos por defecto).

## Uso básico

- El alumno accede a la página **Asistencia remota** desde el menú del
  curso (`local/remotesupport/request.php?id=COURSEID`), o desde el botón
  flotante visible en cualquier página, para solicitar asistencia, ver el
  estado de su solicitud, cancelarla o entrar en la sesión una vez
  aceptada.
- El profesor accede a **Solicitudes de asistencia**
  (`local/remotesupport/view.php`) para ver las solicitudes pendientes de
  sus cursos, aceptarlas y entrar o finalizar sus sesiones abiertas.
  Desde ahí (o desde el icono del navbar, siempre visible) llega a **Mis
  ajustes** (`local/remotesupport/teachersettings.php`) para activar o
  desactivar si acepta asistencia en este momento.
- Cualquiera de los dos puede finalizar la sesión en cualquier momento
  desde su propia página.

## Pruebas

Ver [docs/testing.md](docs/testing.md) para el procedimiento de pruebas
automáticas y manuales.

## Limitaciones conocidas

Ver [docs/limitations.md](docs/limitations.md).
