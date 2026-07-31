<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Cadenas de idioma en español para local_remotesupport.
 *
 * @package    local_remotesupport
 * @copyright  2026 Juan Luis Simón
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Asistencia remota';

// Capacidades.
$string['remotesupport:requestassistance'] = 'Solicitar asistencia remota';
$string['remotesupport:provideassistance'] = 'Proporcionar asistencia remota';
$string['remotesupport:viewactivesessions'] = 'Ver solicitudes y sesiones de asistencia remota';
$string['remotesupport:viewsessionhistory'] = 'Ver sesiones de asistencia remota pasadas';
$string['remotesupport:replaysession'] = 'Reproducir la grabación de una sesión de asistencia remota pasada';
$string['remotesupport:managesessions'] = 'Gestionar cualquier sesión de asistencia remota';
$string['remotesupport:deletesessionhistory'] = 'Eliminar sesiones de asistencia remota pasadas propias';

// Páginas: alumno.
$string['pagetitle_request'] = 'Asistencia remota';
$string['button_requestassistance'] = 'Solicitar asistencia';
$string['button_cancelrequest'] = 'Cancelar solicitud';
$string['button_enter'] = 'Entrar en la sesión';
$string['button_finish'] = 'Finalizar asistencia';
$string['status_requested'] = 'Esperando a que un profesor acepte tu solicitud.';
$string['status_accepted'] = 'Tu solicitud ha sido aceptada por {$a}. Ya puedes entrar en la sesión.';
$string['status_active'] = 'Asistencia activa con {$a}.';
$string['status_none'] = 'No tienes ninguna solicitud de asistencia activa en este curso.';
$string['status_nosupport'] = 'No hay personal de soporte disponible en este curso en este momento.';
$string['requestcreated'] = 'Tu solicitud de asistencia ha sido enviada.';
$string['requestcancelled'] = 'Tu solicitud ha sido cancelada.';
$string['button_viewmyrequest'] = 'Ver mi solicitud';
$string['button_nosupportavailable'] = 'No hay personal de soporte disponible';
$string['info_whatisassistance'] = 'Un profesor podrá ver en tiempo real la página de Moodle que estás usando —no el resto de tu pantalla ni otras pestañas— para ayudarte mejor. Solo observa: no puede hacer clic ni escribir por ti, puedes hablar con él por chat, y puedes finalizar la asistencia cuando quieras.';
$string['label_reason'] = 'Motivo (opcional)';

// Páginas: profesor.
$string['pagetitle_view'] = 'Solicitudes de asistencia';
$string['heading_pending'] = 'Solicitudes pendientes';
$string['heading_active'] = 'Sesiones activas';
$string['col_student'] = 'Alumno';
$string['col_course'] = 'Curso';
$string['col_waitingsince'] = 'Esperando desde';
$string['col_actions'] = 'Acciones';
$string['col_status'] = 'Estado';
$string['col_reason'] = 'Motivo';
$string['button_accept'] = 'Aceptar';
$string['nopending'] = 'No hay solicitudes pendientes.';
$string['noactive'] = 'No tienes sesiones abiertas.';
$string['requestaccepted'] = 'Solicitud aceptada.';
$string['navbar_pendingrequests'] = 'Solicitudes de asistencia pendientes: {$a}';
$string['navbar_myassistance'] = 'Asistencia remota';
$string['navbar_myassistance_disabled'] = 'Asistencia remota (no estás disponible ahora mismo)';
$string['sessionstatus_accepted'] = 'Aceptada, aún sin entrar';
$string['sessionstatus_active'] = 'Activa';
$string['link_mysettings'] = 'Mi configuración';
$string['link_backtorequests'] = 'Volver a las solicitudes';
$string['link_sessionhistory'] = 'Historial de sesiones';

// Páginas: historial de sesiones del profesor.
$string['pagetitle_history'] = 'Historial de sesiones de asistencia';
$string['col_date'] = 'Fecha';
$string['col_duration'] = 'Duración';
$string['col_sessionnumber'] = '#';
$string['col_chat'] = 'Chat';
$string['link_viewchat'] = 'Ver chat';
$string['col_studentfirstname'] = 'Nombre del alumno';
$string['col_studentlastname'] = 'Apellidos del alumno';
$string['durationshort_hours'] = '{$a}h';
$string['durationshort_minutes'] = '{$a}m';
$string['durationshort_seconds'] = '{$a}s';
$string['button_deleteselected'] = 'Eliminar seleccionadas';

// Página: confirmación de borrado de sesiones.
$string['confirmdeletesessions'] = 'Vas a eliminar {$a} sesión/es de tu historial, junto con toda su grabación de pantalla y chat. Esta acción no se puede deshacer.';
$string['confirmdeletesessions_row'] = 'Sesión #{$a->id} — {$a->date} — {$a->course} — {$a->student}';
$string['notice_sessionsdeleted'] = 'Se ha/n eliminado {$a} sesión/es.';
$string['errornosessionselected'] = 'No se ha seleccionado ninguna sesión.';

// Páginas: reproducción de sesión.
$string['pagetitle_replay'] = 'Reproducción de sesión';
$string['heading_replay'] = 'Reproduciendo sesión de asistencia con {$a}';
$string['link_backtohistory'] = 'Volver al historial de sesiones';
$string['button_play'] = 'Reproducir';
$string['button_pause'] = 'Pausar';
$string['replay_chat_heading'] = 'Transcripción del chat';
$string['info_noreplaytrack'] = 'No hay ninguna grabación disponible para esta sesión.';

// Páginas: chat de sesión (vista solo de chat).
$string['pagetitle_chat'] = 'Transcripción del chat de la sesión';
$string['heading_chat'] = 'Transcripción del chat con {$a}';
$string['info_nochatmessages'] = 'No hubo mensajes de chat en esta sesión.';
$string['link_toreplay'] = 'Ir a la reproducción completa';

// Páginas: ajustes del profesor.
$string['pagetitle_settings'] = 'Mis ajustes de asistencia';
$string['settings_supportenabled'] = 'Disponible para prestar asistencia remota';
$string['button_savesettings'] = 'Guardar';
$string['settingssaved'] = 'Tus ajustes se han guardado.';

// Páginas: sesión.
$string['pagetitle_session'] = 'Sesión de asistencia';
$string['heading_session_student'] = 'Asistencia activa con {$a}';
$string['heading_session_teacher'] = 'Asistiendo a {$a}';
$string['sessionclosed'] = 'La sesión ha finalizado.';
$string['sessionendedbystudent'] = 'El alumno ha finalizado la asistencia.';
$string['sessionendedbyteacher'] = 'El profesor ha finalizado la sesión de asistencia. Gracias.';
$string['button_close'] = 'Cerrar';
$string['statusbar_active'] = 'Asistencia activa con {$a}';
$string['connection_connected'] = 'Conectado';
$string['connection_waiting'] = 'Conectando…';
$string['connection_lost'] = 'Conexión perdida';
$string['button_fullscreen'] = 'Pantalla completa';
$string['button_exitfullscreen'] = 'Salir de pantalla completa';
$string['chat_toggle'] = 'Chat';
$string['chat_heading'] = 'Chat con {$a}';
$string['chat_placeholder'] = 'Escribe un mensaje…';
$string['chat_send'] = 'Enviar';

// Errores.
$string['errornopermission'] = 'No tienes permiso para realizar esta acción.';
$string['errorrequestexists'] = 'Ya tienes una solicitud de asistencia activa en este curso.';
$string['errorsessionnotfound'] = 'No se ha encontrado la sesión de asistencia solicitada.';
$string['errorinvalidstatetransition'] = 'Esta acción no es válida para el estado actual de la sesión.';
$string['errorrequestexpired'] = 'Esta solicitud ha caducado.';
$string['errorinvalidtoken'] = 'El enlace de la sesión no es válido o ha caducado. Vuelve a la página de asistencia para obtener uno nuevo.';
$string['errorsessionnotactive'] = 'Esta acción requiere una sesión activa.';
$string['errorinvalideventtype'] = 'Tipo de evento no reconocido.';
$string['erroreventtoolarge'] = 'Este evento es demasiado grande para enviarse.';
$string['errornosupportavailable'] = 'No hay personal de soporte disponible en este curso en este momento.';

// Eventos.
$string['event_request_created'] = 'Solicitud de asistencia creada';
$string['event_request_accepted'] = 'Solicitud de asistencia aceptada';
$string['event_request_cancelled'] = 'Solicitud de asistencia cancelada o caducada';
$string['event_session_started'] = 'Sesión de asistencia iniciada';
$string['event_session_ended'] = 'Sesión de asistencia finalizada';
$string['event_session_deleted'] = 'Sesión de asistencia eliminada del historial';
$string['event_access_denied'] = 'Acceso a asistencia denegado';

// Tareas programadas.
$string['task_expiresessions'] = 'Caducar solicitudes de asistencia remota pendientes';
$string['task_purgeevents'] = 'Depurar eventos de pantalla de asistencia remota obsoletos';
$string['task_purgetrack'] = 'Depurar grabaciones de sesión más antiguas que el periodo de retención';

// Ajustes.
$string['setting_helpheading_title'] = 'Acerca de esta extensión';
$string['setting_helpheading_desc'] = '<details><summary>¿Cómo funciona? (haz clic para ver más)</summary>
<p>Asistencia remota permite a un profesor ayudar a un alumno dentro de Moodle mediante navegación compartida (co-browsing), no mediante control remoto del escritorio: el profesor ve una reconstrucción de la página de Moodle que el alumno está usando, nunca su pantalla real, otras pestañas ni aplicaciones ajenas a Moodle.</p>
<ul>
<li><strong>Flujo básico:</strong> el alumno solicita asistencia, un profesor con permiso en ese curso la acepta, y se abre una sesión temporal entre ambos.</li>
<li><strong>Por defecto la sesión es de solo visualización.</strong> El alumno puede conceder, en cualquier momento y de forma revocable, permiso para que el profesor señale elementos, haga clic en un conjunto reducido de elementos seguros o escriba en campos de texto no sensibles.</li>
<li><strong>Privacidad:</strong> nunca se capturan contraseñas ni el valor de ningún campo de formulario, solo su estructura. El alumno ve siempre una barra visible durante la sesión, con el nombre del profesor y un botón para finalizarla al instante.</li>
<li>Los ajustes de esta página son técnicos (qué se captura de la pantalla, con qué frecuencia se actualiza el cursor, etc.) y no afectan al nivel de permisos, que decide el alumno en cada sesión.</li>
</ul>
</details>
<hr>';
$string['setting_requestexpiryseconds'] = 'Tiempo de caducidad de la solicitud';
$string['setting_requestexpiryseconds_desc'] = 'Cuánto tiempo permanece válida una solicitud de asistencia pendiente antes de caducar automáticamente.';
$string['setting_capturemode'] = 'Modo de captura de pantalla';
$string['setting_capturemode_desc'] = 'Cuánto se captura de la pantalla del alumno para la reconstrucción del profesor. Se aplica a todas las sesiones del sitio.';
$string['capturemode_main'] = 'Solo contenido principal (sin navegación, bloques ni pie de página)';
$string['capturemode_fullpage'] = 'Página completa (con navegación, bloques y pie de página, lo más parecido posible a lo que ve realmente el alumno)';
$string['setting_trackretentiondays'] = 'Periodo de retención de la grabación de sesión';
$string['setting_trackretentiondays_desc'] = 'Durante cuánto tiempo se conserva la grabación permanente de la actividad de pantalla de una sesión, para su reproducción futura por el alumno o el profesor. Se elimina de inmediato, independientemente de este ajuste, si cualquiera de los dos participantes ejerce su derecho de supresión.';
$string['trackretention_15days'] = '15 días';
$string['trackretention_1month'] = '1 mes';
$string['trackretention_3months'] = '3 meses';
$string['trackretention_6months'] = '6 meses';
$string['trackretention_12months'] = '12 meses';
$string['setting_cursorsamplems'] = 'Frecuencia de muestreo de la posición del cursor';
$string['setting_cursorsamplems_desc'] = 'Con qué frecuencia se captura la posición del ratón del alumno y se muestra en la reconstrucción del profesor (en directo y en la reproducción), mientras el ratón se está moviendo realmente. Un valor menor da un movimiento más fluido pero almacena más datos por sesión.';
$string['cursorsamplems_200'] = 'Cada 200 ms (más fluido, más datos almacenados)';
$string['cursorsamplems_500'] = 'Cada 500 ms';
$string['cursorsamplems_1000'] = 'Cada 1 segundo';
$string['cursorsamplems_2000'] = 'Cada 2 segundos (menos datos almacenados)';
$string['setting_clicksound'] = 'Reproducir un sonido cuando el alumno hace clic';
$string['setting_clicksound_desc'] = 'Si el navegador del profesor reproduce un sonido breve cada vez que el alumno hace clic en algo, además de la marca visual. Es el valor por defecto de cada nueva sesión; el profesor puede silenciarlo o activarlo igualmente para la sesión que esté viendo en ese momento.';
$string['button_mutesound'] = 'Silenciar sonido de clic';
$string['button_unmutesound'] = 'Activar sonido de clic';
$string['setting_enableteacherpointer'] = 'Permitir que el profesor señale elementos';
$string['setting_enableteacherpointer_desc'] = 'Si el profesor puede elegir un elemento clicable dentro de su reconstrucción de la pantalla del alumno y dibujar un recuadro temporal alrededor de ese mismo elemento en la pantalla real del alumno, para señalarlo sin actuar sobre él de ninguna forma. Desactivado por defecto. Se aplica a todas las sesiones del sitio.';
$string['setting_teacherpointerttlseconds'] = 'Duración del señalado';
$string['setting_teacherpointerttlseconds_desc'] = 'Cuánto tiempo permanece visible en la pantalla del alumno el recuadro que dibuja el profesor alrededor de un elemento, antes de desaparecer por sí solo.';
$string['button_startpointer'] = 'Señalar elemento';
$string['button_stoppointer'] = 'Dejar de señalar';
$string['teacherpointer_label'] = 'El profesor está señalando esto';

// Privacidad.
$string['privacy:path'] = 'Sesiones de asistencia remota';
$string['privacy:metadata:local_remotesupport_session'] = 'Información sobre solicitudes y sesiones de asistencia remota.';
$string['privacy:metadata:local_remotesupport_session:courseid'] = 'El curso en el que tuvo lugar la sesión de asistencia.';
$string['privacy:metadata:local_remotesupport_session:studentid'] = 'El usuario que solicitó asistencia.';
$string['privacy:metadata:local_remotesupport_session:teacherid'] = 'El usuario que proporcionó asistencia.';
$string['privacy:metadata:local_remotesupport_session:status'] = 'El estado de la solicitud o sesión.';
$string['privacy:metadata:local_remotesupport_session:reason'] = 'El motivo opcional en texto libre que el alumno indicó al solicitar asistencia.';
$string['privacy:metadata:local_remotesupport_session:returnurl'] = 'La página del sitio en la que estaba el alumno cuando solicitó asistencia, para poder devolverle ahí cuando la sesión empiece.';
$string['privacy:metadata:preference:supportenabled'] = 'Si actualmente aceptas solicitudes de asistencia remota como profesor.';
$string['privacy:metadata:preference:sessionhistoryperpage'] = 'El número de filas por página que has elegido para el listado de historial de sesiones.';
$string['privacy:metadata:local_remotesupport_session:timecreated'] = 'La fecha en que se creó la solicitud.';
$string['privacy:metadata:local_remotesupport_session:timestarted'] = 'La fecha en que la sesión pasó a estar activa.';
$string['privacy:metadata:local_remotesupport_session:timeended'] = 'La fecha en que finalizó la sesión.';
$string['privacy:metadata:local_remotesupport_event'] = 'Eventos efímeros de reconstrucción de pantalla y de chat enviados durante una sesión activa. Los eventos de pantalla se eliminan, como máximo, unos minutos después de generarse; los mensajes de chat duran mientras la sesión esté activa. Ambos se eliminan siempre al finalizar la sesión.';
$string['privacy:metadata:local_remotesupport_event:sourceuserid'] = 'El usuario (el alumno o, en una solicitud de resincronización o un mensaje de chat, el profesor) cuyo navegador generó el evento.';
$string['privacy:metadata:local_remotesupport_event:eventtype'] = 'El tipo de evento (foto de página, posición de scroll, posición del cursor del ratón, posición de un clic, solicitud de resincronización o mensaje de chat).';
$string['privacy:metadata:local_remotesupport_event:payload'] = 'La foto de la página capturada (URL relativa, título, contenido principal saneado, posición de scroll/viewport), la posición del cursor del ratón o de un clic del alumno, o el contenido en texto plano de un mensaje de chat. Nunca incluye valores leídos de los propios campos de formulario del alumno.';
$string['privacy:metadata:local_remotesupport_event:timecreated'] = 'La fecha en que se generó el evento.';
$string['privacy:metadata:local_remotesupport_track'] = 'Una grabación permanente de la actividad de pantalla (fotos de página, posiciones de scroll, la posición del cursor del ratón del alumno mientras se mueve, y dónde hizo clic el alumno) y de la conversación de chat de una sesión, conservada durante el periodo de retención configurado para poder reproducir la sesión más adelante. Se elimina de inmediato si el alumno o el profesor ejercen su derecho de supresión, independientemente del periodo de retención.';
$string['privacy:metadata:local_remotesupport_track:sourceuserid'] = 'El usuario (el alumno para una foto de página, posición de scroll, posición del cursor o posición de un clic, cualquiera de los dos participantes para un mensaje de chat) cuyo navegador generó el evento grabado. Nulo en eventos grabados antes de que esto se registrara.';
$string['privacy:metadata:local_remotesupport_track:eventtype'] = 'El tipo de evento grabado (foto de página, posición de scroll, posición del cursor del ratón, posición de un clic, o mensaje de chat).';
$string['privacy:metadata:local_remotesupport_track:payload'] = 'La foto de la página capturada (URL relativa, título, contenido principal saneado, posición de scroll/viewport), la posición del cursor del ratón o de un clic del alumno, o el contenido en texto plano de un mensaje de chat. Nunca incluye valores leídos de los propios campos de formulario del alumno.';
$string['privacy:metadata:local_remotesupport_track:timecreated'] = 'La fecha en que se generó el evento grabado.';
