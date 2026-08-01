@local @local_remotesupport
Feature: Remote assistance session lifecycle
  In order to get or give remote assistance
  As a student or a teacher
  I need to request, accept, use and end an assistance session

  # Scope note: this covers the Fase 1 lifecycle (request, accept, active
  # session, end) end to end through plain server-rendered pages, without
  # a JavaScript driver. It deliberately does not cover the teacher-pointer
  # feature (Fase 3): pointing is a global admin setting rather than a
  # per-session student consent flow in the current implementation, and
  # the pointer overlay itself is rendered by JavaScript inside a
  # reconstructed iframe, which is not worth automating visually yet (see
  # docs/tests_todo.md and CLAUDE.md's own "no intentar automatizar toda
  # la sincronización visual desde el comienzo").

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | student1 | Student   | One      | student1@example.com  |
      | teacher1 | Teacher   | One      | teacher1@example.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: A student requests assistance and cancels it before anyone accepts
    Given I am on the "C1" "local_remotesupport > Request assistance" page logged in as "student1"
    When I set the field "Reason (optional)" to "Stuck on the second question"
    And I press "Request assistance"
    Then I should see "Waiting for a teacher to accept your request."
    And I should see "Cancel request"
    When I click on "Cancel request" "link"
    Then I should see "You have no active assistance request in this course."
    And I should see "Request assistance"

  Scenario: A student requests assistance, a teacher accepts it, and the student ends the session
    Given I am on the "C1" "local_remotesupport > Request assistance" page logged in as "student1"
    When I press "Request assistance"
    Then I should see "Waiting for a teacher to accept your request."

    Given I log in as "teacher1"
    And I visit "/local/remotesupport/view.php"
    Then I should see "Pending requests"
    And I should see "Student One"
    And I should see "Course 1"
    When I click on "Accept" "link" in the "Student One" "table_row"
    Then I should see "Assisting Student One"

    # Accepting takes the teacher straight into session.php, which marks the
    # session active on arrival (see session_manager::enter_session()) — so
    # the student's next page load already shows the active state, not the
    # transient "accepted, not yet entered by anyone" one.
    Given I am on the "C1" "local_remotesupport > Request assistance" page logged in as "student1"
    Then I should see "Assistance active with Teacher One"
    And I should see "Enter session"
    When I click on "Enter session" "link"
    Then I should see "Assistance active with Teacher One"

    Given I am on the "C1" "local_remotesupport > Request assistance" page
    Then I should see "Assistance active with Teacher One"
    And I should see "End assistance"
    When I click on "End assistance" "link"
    Then I should see "The session has ended."
    And I should see "You have no active assistance request in this course."

  Scenario: A teacher ends an active session from the session page
    Given I am on the "C1" "local_remotesupport > Request assistance" page logged in as "student1"
    When I press "Request assistance"
    Then I should see "Waiting for a teacher to accept your request."

    Given I log in as "teacher1"
    And I visit "/local/remotesupport/view.php"
    When I click on "Accept" "link" in the "Student One" "table_row"
    Then I should see "Assisting Student One"
    When I click on "End assistance" "link"
    Then I should see "The session has ended."
    And I should see "You have no open sessions."
