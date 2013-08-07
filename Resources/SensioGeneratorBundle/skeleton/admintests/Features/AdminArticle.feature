@javascript @clean_session
Feature: AdminArticle
  Use the article functionality
  As an admin user
  Create author, newspage, newsoverviewpage

  Scenario: Check newspage before adding overviewpage
    Given I log in as "admin"
    And I am on the news page
    Then I should see "You need to create at least one overview page before you can create a News"

  Scenario: Add a newsoverviewpage
    Given I log in as "admin"
    And I add newsoverviewpage "NewsOverviewPage"
    Then I should see "NewsOverviewPage"

  Scenario: Check newspage after adding overviewpage
    Given I log in as "admin"
    And I am on the news page
    Then I should not see "You need to create at least one overview page before you can create a News"

  Scenario: Create a news page
    Given I log in as "admin"
    And I create a news page "News 1"
    Then I should see "News 1"

  Scenario: Check the adminlist for the news
    Given I log in as "admin"
    And I am on the news page
    Then I should see "News 1"

  Scenario: Create a news author
    Given I log in as "admin"
    And I am on the create new author page
    And I fill in correct author information for author "test"
    When I press "Save"
    Then I should see "test"

  Scenario: Add the newly created author to a news page
    Given I log in as "admin"
    And I set "test" as author of news page "News 1"
    Then I should see "has been edited"

#TODO Delete news author and newsoverviewpage is only possible if nothing is using it.
#Scenario: Delete news author
#  Given I log in as "admin"
#  And I delete author "test"
#  Then I should not see "test"

#Scenario: Delete newsoverviewpage
#  Given I log in as "admin"
#  And I delete page "NewsOverviewPage"
#  Then I should see "The page is deleted"