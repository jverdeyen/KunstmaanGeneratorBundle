<?php

namespace {{ namespace }}\DataFixtures\ORM;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;

use Kunstmaan\NodeBundle\Helper\Services\PageCreatorService;
use Kunstmaan\PagePartBundle\Helper\Services\PagePartCreatorService;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

use {{ namespace }}\Entity\Pages\ContentPage;

/**
 * NavigationFixtures
 */
class NavigationFixtures extends AbstractFixture implements OrderedFixtureInterface, ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    private $container = null;

    /**
     * @var PageCreatorService
     */
    private $pageCreator;

    /**
     * @var PagePartCreatorService
     */
    private $pagePartCreatorService;

    /**
     * Load data fixtures with the passed EntityManager.
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');

        $this->pageCreator = new PageCreatorService();
        $this->pageCreator->setContainer($this->container);

        $this->pagePartCreatorService = new PagePartCreatorService($em);

        // Create level 1 page
        $nodeRepo = $em->getRepository('KunstmaanNodeBundle:Node');
        $homePage = $nodeRepo->findOneBy(array('internalName' => 'homepage'));

        $level1Page = $this->createPage($homePage, '4 Level Navigation', 'navigation', '4_level_navigation', 20);

        // Create level 2 pages
        $catAPage = $this->createPage($level1Page, 'Category A', 'category-a', 'cat_a');
        $catBPage = $this->createPage($level1Page, 'Category B', 'category-b', 'cat_b');
        $catCPage = $this->createPage($level1Page, 'Category C', 'category-c', 'cat_c');

        // Create level 3 pages
        $subCatA1Page = $this->createPage($catAPage, 'Subcategory 1', '1', 'cat_a_1');
        $subCatA2Page = $this->createPage($catAPage, 'Subcategory 2', '2', 'cat_a_2');
        $subCatB1Page = $this->createPage($catBPage, 'Subcategory 1', '1', 'cat_b_1');
        $subCatC1Page = $this->createPage($catCPage, 'Subcategory 1', '1', 'cat_c_1');
        $subCatC2Page = $this->createPage($catCPage, 'Subcategory 2', '2', 'cat_c_2');

        // Create level 4 pages
        $this->createPage($subCatA1Page, 'Item 1', 'item-1', 'cat_a_1_1');
        $this->createPage($subCatA1Page, 'Item 2', 'item-2', 'cat_a_1_2');
        $this->createPage($subCatA2Page, 'Item 1', 'item-1', 'cat_a_2_1');
        $this->createPage($subCatB1Page, 'Item 1', 'item-1', 'cat_b_1_1');
        $this->createPage($subCatB1Page, 'Item 2', 'item-2', 'cat_b_1_2');
        $this->createPage($subCatC1Page, 'Item 1', 'item-1', 'cat_c_1_1');
        $this->createPage($subCatC2Page, 'Item 1', 'item-1', 'cat_c_2_1');
    }

    /**
     * Create a content page.
     *
     * @param PageInterface $parent
     * @param string $title
     * @param string $slug
     * @param string $internalName
     * @param null|int $weight
     * @return \Kunstmaan\NodeBundle\Entity\Node
     */
    public function createPage($parent, $title, $slug, $internalName, $weight = null) {
        $level1Page = new ContentPage();
        $level1Page->setTitle($title);

        $translations = array();
        $translations[] = array('language' => 'en', 'callback' => function($page, $translation, $seo) use ($title, $slug, $weight) {
            $translation->setTitle($title);
            $translation->setSlug($slug);
            if ($weight) {
                $translation->setWeight($weight);
            }
        });

        $options = array(
            'parent' => $parent,
            'page_internal_name' => $internalName,
            'set_online' => true,
            'creator' => 'Admin'
        );

        $page = $this->pageCreator->createPage($level1Page, $translations, $options);
        $this->createPageParts($page, $title);

        return $page;
    }

    /**
     * Create header pagepart for a page.
     *
     * @param HasPagePartsInterface $page
     * @param string $title
     */
    public function createPageParts($page, $title) {
        $pageparts = array('main' => array());
        $pageparts['main'][] = $this->pagePartCreatorService->getCreatorArgumentsForPagePartAndProperties('Kunstmaan\PagePartBundle\Entity\HeaderPagePart',
            array(
                'setTitle' => $title,
                'setNiv'   => 1
            )
        );

        $this->pagePartCreatorService->addPagePartsToPage($page, $pageparts, 'en');
    }

    /**
     * Get the order of this fixture
     *
     * @return int
     */
    public function getOrder()
    {
        return 80;
    }

    /**
     * Sets the Container.
     *
     * @param ContainerInterface $container A ContainerInterface instance
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

}
