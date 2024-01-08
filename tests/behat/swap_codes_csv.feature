@tool @tool_solent @sol @javascript @_file_upload
Feature: Upload CSV to swap course codes
  In order to swap course codes
  As an administrator
  I upload a csv file mapping the codes and their codes, settings, and sol queued enrolments are swapped

  Background:
    Given the following "categories" exist:
      | name                                              | category    | idnumber       |
      | Course                                            | 0           | SOL_Courses    |
      | Faculty of Business, Law and Digital Technologies | SOL_Courses | FBLDT          |
      | Course pages                                      | FBLDT       | courses_FBLDT  |
      | Science and Engineering                           | SOL_Courses | SCIENG         |
      | Courses                                           | SCIENG      | courses_SCIENG |
    And the following "categories" exist:
      | name                                         | category    | idnumber       |
      | Faculty of Sport, Health and Social Sciences | SOL_Courses | FSHSS          |
      | Course pages                                 | FSHSS       | courses_FSHSS  |
      | Sport and Health                             | SOL_Courses | SPOHEA         |
      | Courses                                      | SPOHEA      | courses_SPOHEA |
    And the following "course" exists:
      | fullname  | BSc (Hons) Cyber Security Management (BCSM) |
      | idnumber  | BCSM                                        |
      | shortname | BCSM                                        |
      | startdate | ##2020-08-01 00:00:00##                     |
      | enddate   | 0                                           |
      | visible   | 1                                           |
      | category  | courses_FBLDT                               |
    And the following "course" exists:
      | fullname                    | BSc (Hons) Cyber Security Management (XXBACSM01CXN) |
      | idnumber                    | XXBACSM01CXN                                        |
      | shortname                   | XXBACSM01CXN                                        |
      | startdate                   | ##2023-08-01 00:00:00##                             |
      | enddate                     | 0                                                   |
      | visible                     | 0                                                   |
      | category                    | courses_SCIENG                                      |
      | customfield_templateapplied | 0                                                   |
      | customfield_academic_year   | 2023/24                                             |
      | customfield_location_code   | XX                                                  |
      | customfield_location_name   | Solent University                                   |
      | customfield_pagetype        | course                                              |
      | customfield_org_2           | SCIENG                                              |
      | customfield_org_3           | FTCA                                                |
    And the following "course" exists:
      | fullname  | MSc Sport Science and Performance Coaching (MSSPC)   |
      | idnumber  | MSSPC                                                |
      | shortname | MSSPC                                                |
      | startdate | ##2020-08-01 00:00:00##                              |
      | enddate   | 0                                                    |
      | visible   | 1                                                    |
      | category  | courses_FSHSS                                        |
    And the following "course" exists:
      | fullname                    | MSc Sport Science and Performance Coaching (XXMASSC01CXN) |
      | idnumber                    | XXMASSC01CXN                                              |
      | shortname                   | XXMASSC01CXN                                              |
      | startdate                   | ##2023-08-01 00:00:00##                                   |
      | enddate                     | 0                                                         |
      | visible                     | 1                                                         |
      | category                    | courses_SPOHEA                                            |
      | customfield_templateapplied | 0                                                         |
      | customfield_academic_year   | 2023/24                                                   |
      | customfield_location_code   | XX                                                        |
      | customfield_location_name   | Solent University                                         |
      | customfield_pagetype        | course                                                    |
      | customfield_org_2           | SPOHEA                                                    |
      | customfield_org_3           | FYBA                                                      |
    And the following "activities" exist:
      | activity | name                    | course | idnumber    |
      | page     | old course page (BCSM)  | BCSM   | page1_BCSM  |
      | page     | old course page (MSSPC) | MSSPC  | page1_MSSPC |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | One      | student1@example.com |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following sits queued enrolments exist:
      | course       | username | role           |
      | XXBACSM01CXN | teacher1 | editingteacher |
      | XXMASSC01CXN | teacher1 | editingteacher |
      | XXBACSM01CXN | student1 | student        |
      | XXMASSC01CXN | student1 | student        |

  Scenario: Upload CSV file
    Given I log in as "admin"
    # The before state.
    When I am on "BSc (Hons) Cyber Security Management (BCSM)" course homepage
    Then I should see "old course page (BCSM)"
    When I am on "BSc (Hons) Cyber Security Management (XXBACSM01CXN)" course homepage
    Then I should not see "old course page (BCSM)"
    When I am on "MSc Sport Science and Performance Coaching (MSSPC)" course homepage
    Then I should see "old course page (MSSPC)"
    When I am on "MSc Sport Science and Performance Coaching (XXMASSC01CXN)" course homepage
    Then I should not see "old course page (MSSPC)"
    # Now upload the csv to swap codes.
    And I navigate to "Plugins > Admin tools > Solent > Code swap" in site administration
    And I upload "/admin/tool/solent/tests/fixtures/swapcodes.csv" file to "File" filemanager
    When I press "Upload code swap"
    Then I should see "4 new code swaps queued of 4 supplied"
    And I run all adhoc tasks
    # The after state.
    # BCSM and XXBACSM01CXN swap, so BCSM doesn't exist anymore because it's been renamed.
    When I am on the "XXBACSM01CXN#MAP#BCSM" "Course" page
    Then I should not see "old course page (BCSM)"
    When I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then I should see "Student Records System"
    And the field "customfield_academic_year" does not match value "2023/24"
    And the field "customfield_templateapplied" does not match value "1"
    And the field "customfield_location_code" does not match value "XX"
    And the field "customfield_location_name" does not match value "Solent University"
    And the field "customfield_pagetype" does not match value "course"
    And the field "customfield_org_2" does not match value "SCIENG"
    And the field "customfield_org_3" does not match value "FTCA"
    When I am on the "XXBACSM01CXN" "Course" page
    Then I should see "old course page (BCSM)"
    # Check course custom fields
    When I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then I should see "Student Records System"
    And the field "customfield_academic_year" matches value "2023/24"
    And the field "customfield_templateapplied" matches value "1"
    And the field "customfield_location_code" matches value "XX"
    And the field "customfield_location_name" matches value "Solent University"
    And the field "customfield_pagetype" matches value "course"
    And the field "customfield_org_2" matches value "SCIENG"
    And the field "customfield_org_3" matches value "FTCA"
    # MSSPC and XXMASSC01CXN do not swap because XXMASSC01CXN is visible.
    When I am on the "MSSPC" "Course" page
    Then I should see "old course page (MSSPC)"
    When I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then I should see "Student Records System"
    And the field "customfield_academic_year" does not match value "2023/24"
    And the field "customfield_templateapplied" matches value "0"
    And the field "customfield_location_code" does not match value "XX"
    And the field "customfield_location_name" does not match value "Solent University"
    And the field "customfield_pagetype" does not match value "course"
    And the field "customfield_org_2" does not match value "SPOHEA"
    And the field "customfield_org_3" does not match value "FYBA"
    When I am on the "XXMASSC01CXN" "Course" page
    Then I should not see "old course page (MSSPC)"
    When I navigate to "Settings" in current page administration
    And I expand all fieldsets
    Then I should see "Student Records System"
    And the field "customfield_academic_year" matches value "2023/24"
    And the field "customfield_templateapplied" matches value "0"
    And the field "customfield_location_code" matches value "XX"
    And the field "customfield_location_name" matches value "Solent University"
    And the field "customfield_pagetype" matches value "course"
    And the field "customfield_org_2" matches value "SPOHEA"
    And the field "customfield_org_3" matches value "FYBA"
