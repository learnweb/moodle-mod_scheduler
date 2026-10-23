@mod @mod_scheduler @duplicateslots
Feature: Optionally copy slots when duplicating a Scheduler
  In order to reuse a schedule without copying bookings
  As a teacher
  I can choose whether duplication includes existing slots

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
      | student1 | Student   | One      | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity  | name           | intro | course | idnumber   |
      | scheduler | Test scheduler | n     | C1     | scheduler1 |
    And the following "mod_scheduler > slots" exist:
      | scheduler  | starttime         | duration | teacher  | location    | student  |
      | scheduler1 | ##+5 days 10am##  | 30       | teacher1 | Test office | student1 |

  Scenario: Copy slots without copying bookings
    Given I am on the "scheduler1" Activity page logged in as "teacher1"
    And I should see "Test office" in the "slotmanager" "table"
    And I should see "Student One" in the "slotmanager" "table"
    When I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "copyslotsonduplicate" to "Yes"
    And I press "Save and display"
    And I am on the "C1" course page
    And I turn editing mode on
    And I duplicate "Test scheduler" activity
    And I should see "Test scheduler (copy)"
    And I follow "Test scheduler (copy)"
    Then I should see "Test office" in the "slotmanager" "table"
    And I should not see "Student One" in the "slotmanager" "table"

  Scenario: Duplicate without copying slots
    Given I am on the "scheduler1" Activity page logged in as "teacher1"
    And I should see "Test office" in the "slotmanager" "table"
    When I navigate to "Settings" in current page administration
    And I expand all fieldsets
    And I set the field "copyslotsonduplicate" to "No"
    And I press "Save and display"
    And I am on the "C1" course page
    And I turn editing mode on
    And I duplicate "Test scheduler" activity
    And I should see "Test scheduler (copy)"
    And I follow "Test scheduler (copy)"
    Then "slotmanager" "table" should not exist