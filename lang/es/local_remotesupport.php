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
$string['statusbar_active'] = 'Asistencia activa con {$a}';
$string['hint_pointandclick'] = 'Mueve el ratón sobre la vista previa para señalar; haz clic en un elemento para resaltarlo para el alumno.';
$string['button_clearhighlight'] = 'Quitar resaltado';
$string['connection_connected'] = 'Conectado';
$string['connection_waiting'] = 'Conectando…';
$string['connection_lost'] = 'Conexión perdida';

// Niveles de control.
$string['level_view'] = 'Solo visualización';
$string['level_pointer'] = 'Señalización permitida';
$string['level_click'] = 'Clics permitidos';
$string['level_input'] = 'Escritura permitida';
$string['button_allowpointer'] = 'Permitir señalar';
$string['button_allowclick'] = 'Permitir clics';
$string['button_allowinput'] = 'Permitir escritura';
$string['button_revokeall'] = 'Revocar todo';
$string['controllevelchanged'] = 'Nivel de acceso actualizado.';

// Clic remoto.
$string['button_requestclick'] = 'Solicitar clic';
$string['clickconfirm_message'] = 'El profesor solicita hacer clic en: «{$a}». ¿Lo permites?';
$string['button_allowonce'] = 'Permitir';
$string['button_decline'] = 'Rechazar';
$string['clickresult_clicked'] = 'Clic realizado.';
$string['clickresult_blocked'] = 'Bloqueado: este elemento no está en la lista permitida.';
$string['clickresult_declined'] = 'El alumno ha rechazado el clic.';
$string['clickresult_notfound'] = 'No se ha podido identificar de forma fiable el elemento en la página actual del alumno.';
$string['clickresult_nohighlight'] = 'Resalta un elemento primero.';

// Escritura remota.
$string['inputvalue_placeholder'] = 'Texto a escribir…';
$string['button_setvalue'] = 'Establecer valor';
$string['button_appendtext'] = 'Añadir texto';
$string['button_clearvalue'] = 'Vaciar campo';
$string['inputresult_applied'] = 'El campo se ha actualizado.';
$string['inputresult_blocked'] = 'Bloqueado: este campo no está en la lista permitida.';
$string['inputresult_notfound'] = 'No se ha podido identificar de forma fiable el campo en la página actual del alumno.';

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
$string['errorinvalidcontrollevel'] = 'Nivel de acceso no reconocido.';
$string['errorinsufficientlevel'] = 'El alumno no ha concedido este nivel de acceso.';
$string['errornosupportavailable'] = 'No hay personal de soporte disponible en este curso en este momento.';

// Eventos.
$string['event_request_created'] = 'Solicitud de asistencia creada';
$string['event_request_accepted'] = 'Solicitud de asistencia aceptada';
$string['event_request_cancelled'] = 'Solicitud de asistencia cancelada o caducada';
$string['event_session_started'] = 'Sesión de asistencia iniciada';
$string['event_session_ended'] = 'Sesión de asistencia finalizada';
$string['event_access_denied'] = 'Acceso a asistencia denegado';
$string['event_control_level_changed'] = 'Nivel de acceso de asistencia modificado';
$string['event_remote_click'] = 'Clic remoto de asistencia resuelto';
$string['event_remote_input'] = 'Escritura remota de asistencia resuelta';

// Tareas programadas.
$string['task_expiresessions'] = 'Caducar solicitudes de asistencia remota pendientes';
$string['task_purgeevents'] = 'Depurar eventos de pantalla de asistencia remota obsoletos';

// Ajustes.
$string['setting_requestexpiryseconds'] = 'Tiempo de caducidad de la solicitud';
$string['setting_requestexpiryseconds_desc'] = 'Cuánto tiempo permanece válida una solicitud de asistencia pendiente antes de caducar automáticamente.';
$string['setting_capturemode'] = 'Modo de captura de pantalla';
$string['setting_capturemode_desc'] = 'Cuánto se captura de la pantalla del alumno para la reconstrucción del profesor. Se aplica a todas las sesiones del sitio.';
$string['capturemode_main'] = 'Solo contenido principal (sin navegación, bloques ni pie de página)';
$string['capturemode_fullpage'] = 'Página completa (con navegación, bloques y pie de página, lo más parecido posible a lo que ve realmente el alumno)';

// Privacidad.
$string['privacy:path'] = 'Sesiones de asistencia remota';
$string['privacy:metadata:local_remotesupport_session'] = 'Información sobre solicitudes y sesiones de asistencia remota.';
$string['privacy:metadata:local_remotesupport_session:courseid'] = 'El curso en el que tuvo lugar la sesión de asistencia.';
$string['privacy:metadata:local_remotesupport_session:studentid'] = 'El usuario que solicitó asistencia.';
$string['privacy:metadata:local_remotesupport_session:teacherid'] = 'El usuario que proporcionó asistencia.';
$string['privacy:metadata:local_remotesupport_session:status'] = 'El estado de la solicitud o sesión.';
$string['privacy:metadata:local_remotesupport_session:controllevel'] = 'El nivel de control que el alumno ha concedido al profesor (visualización, señalización o clics).';
$string['privacy:metadata:local_remotesupport_session:reason'] = 'El motivo opcional en texto libre que el alumno indicó al solicitar asistencia.';
$string['privacy:metadata:preference:supportenabled'] = 'Si actualmente aceptas solicitudes de asistencia remota como profesor.';
$string['privacy:metadata:local_remotesupport_session:timecreated'] = 'La fecha en que se creó la solicitud.';
$string['privacy:metadata:local_remotesupport_session:timestarted'] = 'La fecha en que la sesión pasó a estar activa.';
$string['privacy:metadata:local_remotesupport_session:timeended'] = 'La fecha en que finalizó la sesión.';
$string['privacy:metadata:local_remotesupport_event'] = 'Eventos efímeros de reconstrucción de pantalla enviados durante una sesión activa; se eliminan al finalizar la sesión y, como máximo, unos minutos después de generarse.';
$string['privacy:metadata:local_remotesupport_event:sourceuserid'] = 'El usuario (siempre el alumno) cuyo navegador generó el evento.';
$string['privacy:metadata:local_remotesupport_event:eventtype'] = 'El tipo de evento (foto de página o posición de scroll).';
$string['privacy:metadata:local_remotesupport_event:payload'] = 'La foto de la página capturada (URL relativa, título, contenido principal saneado, posición de scroll/viewport), coordenadas de cursor/resaltado/clic, o, en una escritura remota iniciada por el profesor, el texto que el profesor decidió escribir. Nunca incluye valores leídos de los propios campos de formulario del alumno.';
$string['privacy:metadata:local_remotesupport_event:timecreated'] = 'La fecha en que se generó el evento.';
