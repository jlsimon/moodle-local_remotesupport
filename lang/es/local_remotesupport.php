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
$string['col_studentfirstname'] = 'Nombre del alumno';
$string['col_studentlastname'] = 'Apellidos del alumno';
$string['durationshort_hours'] = '{$a}h';
$string['durationshort_minutes'] = '{$a}m';
$string['durationshort_seconds'] = '{$a}s';

// Páginas: reproducción de sesión.
$string['pagetitle_replay'] = 'Reproducción de sesión';
$string['heading_replay'] = 'Reproduciendo sesión de asistencia con {$a}';
$string['link_backtohistory'] = 'Volver al historial de sesiones';
$string['button_play'] = 'Reproducir';
$string['button_pause'] = 'Pausar';
$string['replay_chat_heading'] = 'Transcripción del chat';
$string['info_noreplaytrack'] = 'No hay ninguna grabación disponible para esta sesión.';

// Páginas: ajustes del profesor.
$string['pagetitle_settings'] = 'Mis ajustes de asistencia';
$string['settings_supportenabled'] = 'Disponible para prestar asistencia remota';
$string['button_savesettings'] = 'Guardar';
$string['settingssaved'] = 'Tus ajustes se han guardado.';

// Páginas: sesión.
$string['pagetitle_session'] = 'Sesión de asistencia';
$string['heading_session_student'] = 'Asistencia activa con {$a}';
$string['heading_session_teacher'] = 'Asistiendo a {$a}';
$string['info_studentcontinuebrowsing'] = 'Puedes seguir navegando por el curso con normalidad. Una barra de estado te acompañará en cada página mientras el profesor esté observando.';
$string['button_continuebrowsing'] = 'Ir al curso';
$string['sessionclosed'] = 'La sesión ha finalizado.';
$string['sessionendedbystudent'] = 'El alumno ha finalizado la asistencia.';
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
$string['event_access_denied'] = 'Acceso a asistencia denegado';

// Tareas programadas.
$string['task_expiresessions'] = 'Caducar solicitudes de asistencia remota pendientes';
$string['task_purgeevents'] = 'Depurar eventos de pantalla de asistencia remota obsoletos';
$string['task_purgetrack'] = 'Depurar grabaciones de sesión más antiguas que el periodo de retención';

// Ajustes.
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

// Privacidad.
$string['privacy:path'] = 'Sesiones de asistencia remota';
$string['privacy:metadata:local_remotesupport_session'] = 'Información sobre solicitudes y sesiones de asistencia remota.';
$string['privacy:metadata:local_remotesupport_session:courseid'] = 'El curso en el que tuvo lugar la sesión de asistencia.';
$string['privacy:metadata:local_remotesupport_session:studentid'] = 'El usuario que solicitó asistencia.';
$string['privacy:metadata:local_remotesupport_session:teacherid'] = 'El usuario que proporcionó asistencia.';
$string['privacy:metadata:local_remotesupport_session:status'] = 'El estado de la solicitud o sesión.';
$string['privacy:metadata:local_remotesupport_session:reason'] = 'El motivo opcional en texto libre que el alumno indicó al solicitar asistencia.';
$string['privacy:metadata:preference:supportenabled'] = 'Si actualmente aceptas solicitudes de asistencia remota como profesor.';
$string['privacy:metadata:preference:sessionhistoryperpage'] = 'El número de filas por página que has elegido para el listado de historial de sesiones.';
$string['privacy:metadata:local_remotesupport_session:timecreated'] = 'La fecha en que se creó la solicitud.';
$string['privacy:metadata:local_remotesupport_session:timestarted'] = 'La fecha en que la sesión pasó a estar activa.';
$string['privacy:metadata:local_remotesupport_session:timeended'] = 'La fecha en que finalizó la sesión.';
$string['privacy:metadata:local_remotesupport_event'] = 'Eventos efímeros de reconstrucción de pantalla y de chat enviados durante una sesión activa. Los eventos de pantalla se eliminan, como máximo, unos minutos después de generarse; los mensajes de chat duran mientras la sesión esté activa. Ambos se eliminan siempre al finalizar la sesión.';
$string['privacy:metadata:local_remotesupport_event:sourceuserid'] = 'El usuario (el alumno o, en una solicitud de resincronización o un mensaje de chat, el profesor) cuyo navegador generó el evento.';
$string['privacy:metadata:local_remotesupport_event:eventtype'] = 'El tipo de evento (foto de página, posición de scroll, solicitud de resincronización o mensaje de chat).';
$string['privacy:metadata:local_remotesupport_event:payload'] = 'La foto de la página capturada (URL relativa, título, contenido principal saneado, posición de scroll/viewport), o el contenido en texto plano de un mensaje de chat. Nunca incluye valores leídos de los propios campos de formulario del alumno.';
$string['privacy:metadata:local_remotesupport_event:timecreated'] = 'La fecha en que se generó el evento.';
$string['privacy:metadata:local_remotesupport_track'] = 'Una grabación permanente de la actividad de pantalla (fotos de página y posiciones de scroll) y de la conversación de chat de una sesión, conservada durante el periodo de retención configurado para poder reproducir la sesión más adelante. Se elimina de inmediato si el alumno o el profesor ejercen su derecho de supresión, independientemente del periodo de retención.';
$string['privacy:metadata:local_remotesupport_track:sourceuserid'] = 'El usuario (el alumno para una foto de página o posición de scroll, cualquiera de los dos participantes para un mensaje de chat) cuyo navegador generó el evento grabado. Nulo en eventos grabados antes de que esto se registrara.';
$string['privacy:metadata:local_remotesupport_track:eventtype'] = 'El tipo de evento grabado (foto de página, posición de scroll o mensaje de chat).';
$string['privacy:metadata:local_remotesupport_track:payload'] = 'La foto de la página capturada (URL relativa, título, contenido principal saneado, posición de scroll/viewport), o el contenido en texto plano de un mensaje de chat. Nunca incluye valores leídos de los propios campos de formulario del alumno.';
$string['privacy:metadata:local_remotesupport_track:timecreated'] = 'La fecha en que se generó el evento grabado.';
