<?php

namespace {{ namespace }}\Features\Context;

use Behat\Behat\Context\BehatContext;
use Behat\Behat\Context\Step;
use Behat\Mink\Exception\ElementNotFoundException;

/**
 * RoleContext
 *
 * Provides the context for the AdminArticle.feature
 */
class ArticleContext extends BehatContext
{

    /**
     * @param string $newsPageName
     *
     * @Given /^I create a news page "([^"]*)"$/
     *
     * @throws ElementNotFoundException
     */
    public function iCreateANewsPage($newsPageName)
    {
        $this->getMainContext()->iAmOnASpecificPage("news");

        $records = array(
            "addpage_title" => $this->getMainContext()->fixStepArgument($newsPageName)
        );

        $this->getMainContext()->clickLink("Add New");

        $this->getMainContext()->iWaitSeconds(2);

        $page = $this->getMainContext()->getSession()->getPage();
        $modals = $page->findAll('xpath', "//div[contains(@id, 'add-subpage-modal')]");

        foreach ($modals as $modal) {
            if ($modal->hasClass('in')) {
                foreach ($records as $field => $value) {
                    $modalField = $modal->findField($field);
                    if (null === $modalField) {
                        throw new ElementNotFoundException(
                            $this->getSession(), 'form field', 'id|name|label|value', $field
                        );
                    }
                    $modalField->setValue($value);
                }
                $this->getMainContext()->findAndClickButton($modal, 'xpath', "//form//button[@type='submit']");

                return;
            }
        }
    }

    /**
     * @Given /^I set "([^"]*)" as author of news page "([^"]*)"$/
     */
    public function iSetAsAuthorOfNewsPage($authorName, $newsPage)
    {
        $this->getMainContext()->iAmOnASpecificPage("admin home");
        // This is need to click the little triangle to show the pages under newsoverviewpage
        $this->getMainContext()->findAndClickButton($this->getMainContext()->getSession()->getPage(), 'xpath', "//li[contains(@class, 'jstree-closed')]//ins[@class='jstree-icon']");
        // Wait a few seconds for the link to appear
        $this->getMainContext()->iWaitSeconds(2);
        // Click on the newsPage
        $this->getMainContext()->clickLink($newsPage);
        // Select the newsAuthor
        $this->getMainContext()->getSession()->getPage()->selectFieldOption("form[main][author]", $this->getMainContext()->fixStepArgument($authorName));
        // Save the page
        $this->getMainContext()->pressButton("Save");
    }

    /**
     * @param string $authorName
     *
     * @Given /^I fill in correct author information for author "([^"]*)"$/
     *
     * @return array
     */
    public function iFillInCorrectAuthorInformationForAuthor($authorName)
    {
        $steps = array();

        $records = array(
            "Newsauthor_form[name]" => $this->getMainContext()->fixStepArgument($authorName),
            "Newsauthor_form[link]" => "http://www.kunstmaan.be"
        );
        foreach ($records as $field => $value) {
            $steps[] = new Step\When("I fill in \"$field\" with \"$value\"");
        }

        return $steps;
    }

    /**
     * @param string $authorName
     *
     * @Given /^I delete author "([^"]*)"$/
     */
    public function iDeleteAuthor($authorName)
    {
        $this->getMainContext()->clickAction($authorName, 'delete', 'authors');

        $page = $this->getMainContext()->getSession()->getPage();
        $modals = $page->findAll('xpath', "//div[contains(@class, 'modal')]");

        //Wait 1 second for the modal to be visible
        //Else we can get a error when running the tests.
        $this->getMainContext()->iWaitSeconds(1);

        // Find the visible modal.
        // Couldn't do this via xpath using : [contains(@class, 'modal') and contains(@class, 'in')]
        foreach ($modals as $modal) {
            if ($modal->hasClass('in')) {
                $this->getMainContext()->findAndClickButton($modal, 'xpath', "//form//button[@type='submit']");

                return;
            }
        }
    }
}
