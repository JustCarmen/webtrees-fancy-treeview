<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Fisharebest\Webtrees\Age;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Place;
use Aura\Router\RouterContainer;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Webtrees;
use Illuminate\Support\Collection;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\GedcomRecord;
use Psr\Http\Message\ResponseInterface;
use Fisharebest\Localization\Translation;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Services\RelationshipService;
use Fisharebest\Webtrees\Module\ModuleLanguageInterface;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;

class FancyTreeviewModule extends AbstractModule implements ModuleCustomInterface, ModuleGlobalInterface, ModuleTabInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleGlobalTrait;
    use ModuleMenuTrait;
    use ModuleTabTrait;
    use ModuleConfigTrait;

    // Route
    protected const ROUTE_URL = '/tree/{tree}/{module}/{xref}/{name}/{type}/{page}';

    // Module constants
    public const CUSTOM_AUTHOR = 'JustCarmen';
    public const CUSTOM_VERSION = '2.0.0-dev';
    public const GITHUB_REPO = 'webtrees-fancy-treeview';
    public const AUTHOR_WEBSITE = 'https://justcarmen.nl';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . '/modules-webtrees-2/fancy-treeview/';

    // Image cache dir
    private const CACHE_DIR = Webtrees::DATA_DIR . 'ftv-cache/';

    // Module variables
    public array $xrefs;
    public int $generation;
    public int $ancestor_generations;
    public int $descendant_generations;
    public int $index;
    public string $type; // 'descendants' or 'ancestors'

    private Tree $tree;
    private RelationshipService $relationship_service;

    /**
     * Fancy Treeview constructor.
     *
     * @param RelationshipService $relationship_service
     */
    public function __construct(RelationshipService $relationship_service)
    {
        $this->relationship_service = $relationship_service;
    }

    /**
     * How should this module be identified in the control panel, etc.?
     *
     * @return string
     */
    public function title(): string
    {

        /* I18N: Name of a module */
        return I18N::translate('Fancy Treeview');
    }

    /**
     * A sentence describing what this module does.
     *
     * @return string
     */
    public function description(): string
    {
        /* I18N: Description of the “Fancy Treeview” module */
        return I18N::translate('A Fancy overview of the descendants or ancestors of one family (branch) in a narrative way.');
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * A URL that will provide the latest stable version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/main/latest-version.txt';
    }

    /**
     * Fetch the latest version of this module.
     *
     * @return string
     */
    public function customModuleLatestVersion(): string
    {
        return 'https://github.com/' . self::CUSTOM_AUTHOR . '/' . self::GITHUB_REPO . '/releases/latest';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return self::CUSTOM_SUPPORT_URL;
    }

    /**
     * Bootstrap the module
     */
    public function boot(): void
    {
        $router_container = app(RouterContainer::class);
        assert($router_container instanceof RouterContainer);

        $router_container->getMap()
            ->get(static::class, static::ROUTE_URL, $this);

        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');
    }

    /**
     * Where does this module store its resources
     *
     * @return string
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * Raw content, to be added at the end of the <head> element.
     * Typically, this will be <link> and <meta> elements.
     *
     * @return string
     */
    public function headContent(): string
    {
        return '<link rel="stylesheet" href="' . e($this->assetUrl('css/style.css')) . '">';
    }

    /**
     * Additional/updated translations.
     *
     * @param string $language
     *
     * @return string[]
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * The default position for this menu.  It can be changed in the control panel.
     *
     * @return int
     */
    public function defaultMenuOrder(): int
    {
        return 99;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->tree     = Validator::attributes($request)->tree();
        $xref           = Validator::attributes($request)->string('xref');
        $this->type     = Validator::attributes($request)->string('type');
        $page           = Validator::attributes($request)->integer('page');

        $page_title     = $this->printPageTitle($this->getPerson($xref), $this->type);

        // determine the generation to start with
        $limit = (int) $this->options('page-limit');
        $start = ($page - 1) * $limit + 1;

        if ($this->type === 'ancestors') {
            $page_body   = $this->printAncestorsPage($xref, $start, $limit);
            $button_url  = $this->getUrl($this->tree, $xref, 'descendants');
            $button_text = I18N::translate('Show') . ' ' . strtolower(I18N::translate('Descendants'));
            $generations = $this->ancestor_generations;
        } else {
            $page_body   = $this->printDescendantsPage($xref, $start, $limit);
            $button_url  = $this->getUrl($this->tree, $xref, 'ancestors');
            $button_text =  I18N::translate('Show') . ' ' . strtolower(I18N::translate('Ancestors'));
            $generations = $this->descendant_generations;
        }

        $total_pages = (int) ceil($generations / $limit);

        return $this->viewResponse($this->name() . '::page', [
            'tree'              => $this->tree,
            'title'             => $this->title(),
            'page_title'        => $page_title,
            'xref'              => $xref,
            'page_body'         => $page_body,
            'button_url'        => $button_url,
            'button_text'       => $button_text,
            'generations'       => $generations,
            'current_page'      => $page,
            'total_pages'       => $total_pages,
            'limit'             => $limit,
            'module'            => $this
        ]);
    }

    /**
     * The text that appears on the tab.
     *
     * @return string
     */
    public function tabTitle(): string
    {
        return I18N::translate('Descendants') . ' ' . I18N::translate('and') . ' ' . strtolower(I18N::translate('Ancestors'));
    }

    /**
     * Is this tab empty? If so, we don't always need to display it.
     *
     * @param Individual $individual
     *
     * @return bool
     */
    public function hasTabContent(Individual $individual): bool
    {
        return true;
    }

    /**
     * Can this tab load asynchronously?
     *
     * @return bool
     */
    public function canLoadAjax(): bool
    {
        return false;
    }

    /**
     * A greyed out tab has no actual content, but may perhaps have
     * options to create content.
     *
     * @param Individual $individual
     *
     * @return bool
     */
    public function isGrayedOut(Individual $individual): bool
    {
        return false;
    }

    /**
     * Generate the HTML content of this tab.
     *
     * @param Individual $individual
     *
     * @return string
     */
    public function getTabContent(Individual $individual): string
    {
        $request = app(ServerRequestInterface::class);
        assert($request instanceof ServerRequestInterface);

        $this->tree  = Validator::attributes($request)->tree();
        $xref        = Validator::attributes($request)->isXref()->string('xref', '');
        $start       = 1; // always start with the current generation in tab view
        $limit       = 3; // always limit the number of generations to 3 in tab view

        return view($this->name() . '::tab', [
            'module'                        => $this,
            'tree'                          => $this->tree,
            'xref'                          => $xref,
            'tab_page_title_descendants'    => $this->printPageTitle($individual, 'descendants'),
            'tab_page_title_ancestors'      => $this->printPageTitle($individual, 'ancestors'),
            'tab_content_descendants'       => $this->printDescendantsPage($xref, $start, $limit),
            'tab_content_ancestors'         => $this->printAncestorsPage($xref, $start, $limit),
            'descendant_generations'        => $this->descendant_generations,
            'ancestor_generations'          => $this->ancestor_generations,
            'limit'                         => $limit
        ]);
    }

    /**
     * Set the default options.
     * This is php-8 code. We should have an alternative for php 7.4 users.
     *
     * @param string $option
     *
     * @return string
     */
    public function options(string $option): string
    {
        $default = match ($option) {
            'check-relationship'    => '1',
            'show-singles'          => '0',
            'thumb-size'            => '80',
            'crop-thumbs'           => '0',
            'media-type-photo'      => '1', // new option (boolean)
            'page-limit'            => '3' // new option (integer, number of generation blocks per page)
        };

        return $this->getPreference($option, $default);
    }

    /**
     * Print the Fancy Treeview descendants page
     *
     * @param string $xref
     * @param int $start
     * @param int $limit
     *
     * @return string
     */
    public function printDescendantsPage(string $xref, int $start, int $limit): string
    {
        $this->generation = 1;
        $root_xref        = $xref; // save value for read more link
        $this->xrefs      = [$xref];
        $this->type       = 'descendants';

        // check root access
        $this->checkRootAccess($root_xref);

        if ($start === 1) {
            $html = $this->printGeneration();
        } else {
            $html = '';
        }

        while (count($this->xrefs) > 0) {

            $this->generation++;

            $xrefs = $this->xrefs;
            unset($this->xrefs); // empty the array (will be filled with the next generation)

            foreach ($xrefs as $xref) {
                $next_gen[] = $this->getNextGen($xref);
            }

            foreach ($next_gen as $descendants) {
                if (count($descendants) > 0) {
                    foreach ($descendants as $descendant) {
                        if ((bool) $this->options('show-singles') || (bool) $descendant['descendants']) {
                            $this->xrefs[] = $descendant['xref'];
                        }
                    }
                }
            }

            if (!empty($this->xrefs)) {
                unset($next_gen, $descendants, $xrefs);
                // Once we have fetched the page we need to know the total number of generations for this individual
                if ($this->generation > $start + $limit) {
                    $this->descendant_generations = $this->generation;
                } else {
                    if ($this->generation < $start) {
                    continue;
                    } elseif ($this->generation === $start + $limit) {
                        continue; // continue to get the total number of generations.
                    } else {
                        $html .= $this->printGeneration();
                    }
                }
            } else {
                break;
            }
        }

        $this->descendant_generations = $this->generation - 1;

        return $html;
    }

     /**
     * Print the Fancy Treeview ancestors page
     *
     * @param string $xref
     * @param int $start
     * @param int $limit
     *
     * @return string
     */
    public function printAncestorsPage(string $xref, int $start, int $limit): string
    {
        $this->generation = 1;
        $root_xref        = $xref; // save value for read more link
        $this->xrefs      = [$xref];
        $this->type       = 'ancestors';

        // check root access
        $this->checkRootAccess($root_xref);

        if ($start === 1) {
            $html = $this->printGeneration();
        } else {
            $html = '';
        }

        while (count($this->xrefs) > 0) {

            $this->generation++;

            $xrefs = $this->xrefs;
            unset($this->xrefs); // empty the array (will be filled with the next generation)

            foreach ($xrefs as $xref) {
                $person  = $this->getPerson($xref);
                $parents = $person->childFamilies()->first();;
                if ($parents) {
                    $father     = $parents->husband();
                    $mother     = $parents->wife();
                    if ($father) {
                        $this->xrefs[] = $father->xref();
                    }
                    if ($mother) {
                        $this->xrefs[] = $mother->xref();
                    }
                }
            }

            if (!empty($this->xrefs)) {
                unset($prev_gen, $ancestors, $xrefs);
                // Once we have fetched the page we need to know the total number of generations for this individual
                if ($this->generation > $start + $limit) {
                    $this->ancestor_generations = $this->generation;
                } else {
                    if ($this->generation < $start) {
                    continue;
                    } elseif ($this->generation === $start + $limit) {
                        continue; // continue to get the total number of generations
                    } else {
                        $html .= $this->printGeneration();
                    }
                }
            } else {
                break;
            }
        }

        $this->ancestor_generations = $this->generation - 1;

        return $html;
    }

     /**
     * Print page/tab title
     *
     * @param Individual $person
     *
     * @return string
     */
    protected function printPageTitle(Individual $person, string $type): string
    {
        if ($type === 'ancestors') {
            return I18N::translate('Ancestors of %s', $person->fullName());
        } else {
            return I18N::translate('Descendants of %s', $person->fullName());
        }
    }

    /**
     * Print a generation
     *
     * @return string
     */
    protected function printGeneration(): string
    {
        // reset the index
        $this->index = 1;

        return View($this->name() . '::block', [
            'generation'    => $this->generation,
            'module'        => $this,
            'xrefs'         => $this->xrefs,
            'title'         => $this->title(),
            'tree'          => $this->tree,
        ]);
    }

    /**
     * Print read-more link
     *
     * @param string $root
     *
     * @return string
     */
    public function printReadMoreLink(string $xref): string
    {
        return View($this->name() . '::readmore-link', ['url' => $this->getUrl($this->tree, $xref)]);
    }

    /**
     * Print the content for one individual
     *
     * We are not going to convert the html output to views from here.
     * Besides it is a hell of a job, it gives unwanted results (extra spaces before punctuation marks is one thing),
     *
     * @param Individual $person
     *
     * @return string
     */
    public function printIndividual(Individual $person): string
    {
        $html = '<span id="' . $person->xref() . '" name="' . $person->xref() . '"></span>'; // scroll
        if ($person->canShow()) {
            $html .= '<div class="jc-parents-block">';

            $html .= '<div class="jc-person-block d-flex col">';

            $html .= '<div class="jc-person-block-image mt-1 mb-3">';
            if ($person->findHighlightedMediaFile() !== null && (bool) $this->options('media-type-photo') ? strcasecmp($person->findHighlightedMediaFile()->type(), 'photo') === 0 : $person->findHighlightedMediaFile()) {
                $html .= $person->displayImage((int) $this->options('thumb-size'), (int) $this->options('thumb-size'), (bool) $this->options('crop-thumbs') ? 'crop' : 'contain', ['class' => 'jc-ftv-thumbnail']);
            }
            $html .= '</div>';

            $html .= '<div class="jc-person-block-text"><p>' . $this->printNameUrl($person, $person->xref());

            $html .= $this->printOccupations($person);

            $html .= $this->printParents($person) . $this->printLifespan($person) . '.</p>';

            $html .= '</div></div>';

            if ($this->type === 'descendants') {
                $html .= '<div class="jc-spouse-block">';

                // get a list of all the spouses
                /*
                * First, determine the true number of spouses by checking the family gedcom
                */
                $spousecount = 0;
                foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $i => $family) {
                    $spouse = $family->spouse($person);
                    if ($spouse && $spouse->canShow() && $this->getMarriage($family)) {
                        $spousecount++;
                    }
                }
                /*
                * Now iterate thru spouses
                * $spouseindex is used for ordinal rather than array index
                * as not all families have a spouse
                * $spousecount is passed rather than doing each time inside function get_spouse
                */
                if ($spousecount > 0) {
                    $spouseindex = 0;
                    foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $i => $family) {
                        $spouse = $family->spouse($person);
                        if ($spouse && $spouse->canShow()) {
                            if ($this->getMarriage($family)) {
                                $html .= $this->printSpouse($family, $person, $spouse, $spouseindex, $spousecount);
                                $spouseindex++;
                            } else {
                                $html .= $this->printPartner($family, $person, $spouse);
                            }
                        }
                    }
                }

                $html .= '</div></div>';

                // get children for each couple (could be none or just one, $spouse could be empty, includes children of non-married couples)
                foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $family) {
                    $spouse = $family->spouse($person);

                    $html .= $this->printChildren($family, $person, $spouse);
                }
            }
            return $html;
        } else {
            if ($person->tree()->getPreference('SHOW_PRIVATE_RELATIONSHIPS')) {
                return I18N::translate('The details of this family are private.');
            }
        }
    }

    /**
     * Print the content for a spouse
     *
     * @param Family $family
     * @param Individual $person
     * @param Individual $spouse
     * @param int $i
     * @param int $count
     *
     * @return string
     */
    protected function printSpouse(Family $family, Individual $person, Individual $spouse, int $i, int $count): string
    {

        $html = '<p>';

        if ($count > 1) {
            // we assume no one married more then ten times.
            $wordcount = [
                /* I18N: first marriage  */
                I18N::translate('first'),
                /* I18N: second marriage  */ I18N::translate('second'),
                /* I18N: third marriage  */ I18N::translate('third'),
                /* I18N: fourth marriage  */ I18N::translate('fourth'),
                /* I18N: fifth marriage  */ I18N::translate('fifth'),
                /* I18N: sixth marriage  */ I18N::translate('sixth'),
                /* I18N: seventh marriage  */ I18N::translate('seventh'),
                /* I18N: eighth marriage  */ I18N::translate('eighth'),
                /* I18N: ninth marriage  */ I18N::translate('ninth'),
                /* I18N: tenth marriage  */ I18N::translate('tenth'),
            ];
            switch ($person->sex()) {
                case 'M':
                    if ($i == 0) {
                        $html .= /* I18N: %s is a number  */ I18N::translate('He married %s times', $count) . '. ';
                    }
                    $html .= /* I18N: %s is an ordinal */ I18N::translate('The %s time he married', $wordcount[$i]);
                    break;
                case 'F':
                    if ($i == 0) {
                        $html .= /* I18N: %s is a number  */ I18N::translate('She married %s times', $count) . '. ';
                    }
                    $html .= /* I18N: %s is an ordinal */ I18N::translate('The %s time she married', $wordcount[$i]);
                    break;
                default:
                    if ($i == 0) {
                        $html .= /* I18N: %s is a number  */ I18N::translate('This individual married %s times', $count) . '. ';
                    }
                    $html .= /* I18N: %s is an ordinal */ I18N::translate('The %s time this individual married', $wordcount[$i]);
                    break;
            }
        } else {
            switch ($person->sex()) {
                case 'M':
                    $html     .= I18N::translate('He married');
                    break;
                case 'F':
                    $html     .= I18N::translate('She married');
                    break;
                default:
                    $html     .= I18N::translate('This individual married');
                    break;
            }
        }

        $html .= ' ' . $this->printNameUrl($spouse);
        $html .= $this->printRelationship($person, $spouse);
        $html .= $this->printParents($spouse);

        if (!$family->getMarriage()) { // use the default privatized function to determine if marriage details can be shown.
            $html .= '.';
        } else {
            // use the facts below only on none private records.
            if ($this->printParents($spouse)) {
                $html .= ',';
            }

            $marriage = $family->facts(['MARR'])->first();
            if ($marriage) {
                $html .= $this->printDate($marriage) . $this->printPlace($marriage);
            }

            if ($this->printLifespan($spouse, true)) {
                $html .= $this->printLifespan($spouse, true);
            }
            $html .= '. ';

            $divorce = $family->facts(['DIV'])->first();
            if ($divorce) {
                $html .= $this->printName($person) . ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse) . ' ' . I18N::translate('were divorced') . $this->printDate($divorce) . '.';
            }
        }

        $html .= '</p>';

        return $html;
    }

    /**
     * Print the content for a non-married partner
     *
     * @param Family $family
     * @param Individual $person
     * @param Individual $spouse
     *
     * @return string
     */
    protected function printPartner(Family $family, Individual $person, Individual $spouse): string
    {

        $html = '<p>';

        switch ($person->sex()) {
            case 'M':
                $html     .= I18N::translate('He had a relationship with');
                break;
            case 'F':
                $html     .= I18N::translate('She had a relationship with');
                break;
            default:
                $html     .= I18N::translate('This individual had a relationship with');
                break;
        }

        $html .= ' ' . $this->printNameUrl($spouse);
        $html .= $this->printRelationship($person, $spouse);
        $html .= $this->printParents($spouse);

        if ($family->facts(['_NMR'])->first() && $this->printLifespan($spouse, true)) {
            $html .= $this->printLifespan($spouse, true);
        }

        $html .= '</p>';

        return $html;
    }

    /**
     * Print the childrens list
     *
     * @param Family $family
     * @param Individual $person
     * @param Individual $spouse
     *
     * @return string
     */
    protected function printChildren(Family $family, Individual $person, Individual $spouse = null): string
    {
        $html = '';

        $match = null;
        if (preg_match('/\n1 NCHI (\d+)/', $family->gedcom(), $match) && $match[1] == 0) {
            $html .= '<div class="children"><p>' . $this->printName($person) . ' ';
            if ($spouse && $spouse->canShow()) {
                $html     .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse) . ' ';
                $html     .= I18N::translateContext('Two parents/one child', 'had');
            } else {
                $html .= I18N::translateContext('One parent/one child', 'had');
            }
            $html .= ' ' . I18N::translate('none') . ' ' . I18N::translate('children') . '.</p></div>';
        } else {
            $children = $family->children();
            if ($children) {
                if ($this->checkPrivacy($children)) {
                    $html .= '<div class="children"><p>' . $this->printName($person) . ' ';
                    // needs multiple translations for the word 'had' to serve different languages.
                    if ($spouse && $spouse->canShow()) {
                        $html .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse) . ' ';
                        if (count($children) > 1) {
                            $html .= I18N::translateContext('Two parents/multiple children', 'had');
                        } else {
                            $html .= I18N::translateContext('Two parents/one child', 'had');
                        }
                    } else {
                        if (count($children) > 1) {
                            $html .= I18N::translateContext('One parent/multiple children', 'had');
                        } else {
                            $html .= I18N::translateContext('One parent/one child', 'had');
                        }
                    }
                    $html .= ' ' . /* I18N: %s is a number */ I18N::plural('%s child', '%s children', count($children), count($children)) . '.</p></div>';
                } else {
                    $html .= '<div class="jc-children-block mb-2"><p class="mb-1">' . I18N::translate('Children of ') . $this->printName($person);
                    if ($spouse && $spouse->canShow()) {
                        $html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse);
                    }
                    $html .= ':<ol>';

                    foreach ($children as $child) {
                        if ($child->canShow()) {
                            $html     .= '<li class="jc-child-li">' . $this->printNameUrl($child);
                            $pedi     = $this->checkPedi($child, $family);

                            if ($pedi) {
                                $html .= ' <span class="pedi">';
                                switch ($pedi) {
                                    case 'foster':
                                        switch ($child->sex()) {
                                            case 'F':
                                                $html     .= I18N::translateContext('FEMALE', 'foster child');
                                                break;
                                            default:
                                                $html     .= I18N::translateContext('MALE', 'foster child');
                                                break;
                                        }
                                        break;
                                    case 'adopted':
                                        switch ($child->sex()) {
                                            case 'F':
                                                $html     .= I18N::translateContext('FEMALE', 'adopted child');
                                                break;
                                            default:
                                                $html     .= I18N::translateContext('MALE', 'adopted child');
                                                break;
                                        }
                                        break;
                                }
                                $html .= '</span>';
                            }

                            if ($child->getBirthDate()->isOK() || $child->getDeathdate()->isOK()) {
                                $html .= '<span class="lifespan"> (' . $child->lifespan() . ')</span>';
                            }

                            $html .= $this->printFollowLink($child);

                            $html .= '</li>';
                        } else {
                            $html .= '<li class="jc-child-li jc-private">' . I18N::translate('Private') . '</li>';
                        }
                    }
                    $html .= '</ol></div>';
                }
            }
        }
        return $html;
    }

    /**
     * Print the parents
     *
     * @param Individual $person
     *
     * @return string
     */
    protected function printParents(Individual $person): ?string
    {
        $parents = $person->childFamilies()->first();
        if ($parents) {
            $pedi = $this->checkPedi($person, $parents);

            $html = '';
            switch ($person->sex()) {
                case 'M':
                    if ($pedi === 'foster') {
                        $html .= ', ' . I18N::translate('foster son of') . ' ';
                    } elseif ($pedi === 'adopted') {
                        $html .= ', ' . I18N::translate('adopted son of') . ' ';
                    } else {
                        $html .= ', ' . I18N::translate('son of') . ' ';
                    }
                    break;
                case 'F':
                    if ($pedi === 'foster') {
                        $html .= ', ' . I18N::translate('foster daughter of') . ' ';
                    } elseif ($pedi === 'adopted') {
                        $html .= ', ' . I18N::translate('adopted daughter of') . ' ';
                    } else {
                        $html .= ', ' . I18N::translate('daughter of') . ' ';
                    }
                    break;
                default:
                    if ($pedi === 'foster') {
                        $html .= ', ' . I18N::translate('foster child of') . ' ';
                    } elseif ($pedi === 'adopted') {
                        $html .= ', ' . I18N::translate('adopted child of') . ' ';
                    } else {
                        $html .= ', ' . I18N::translate('child of') . ' ';
                    }
            }

            $father     = $parents->husband();
            $mother     = $parents->wife();

            if ($father) {
                $html .= $this->printName($father);
            }
            if ($father && $mother) {
                $html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
            }
            if ($mother) {
                $html .= $this->printName($mother);
            }

            return $html;
        }

        return null;
    }

    /**
     * Print the full name of a person
     *
     * @param Individual $person
     *
     * @return string
     */
    protected function printName(Individual $person): string
    {
        return $person->fullname();
    }

    /**
     * Print the name of a person with the link to the individual page
     *
     * @return string
     */
    protected function printNameUrl(Individual $person, $xref = '')
    {
        return '<a href="' . $person->url() . '">' . $person->fullname() . '</a>';
    }

    /**
     * Print occupations
     *
     * @param Individual $person
     *
     * @return string
     */
    protected function printOccupations(Individual $person): string
    {
        $html        = '';
        $occupations = $person->facts(['OCCU'], true);
        $count       = count($occupations);
        foreach ($occupations as $key => $fact) {
            if ($key > 0 && $key === $count - 1) {
                $html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
            } else {
                $html .= ', ';
            }

            // In the Gedcom file most occupations are probably written with a capital (as a single word)
            // but use lcase/ucase to be sure the occupation is spelled correctly since we are using
            // it in the middle of a sentence.
            // In German all occupations are written with a capital.
            // Are there any other languages where this is the case?
            if (I18N::languageTag() === 'de') {
                $html .= rtrim(ucfirst($fact->value()), ".");
            } else {
                $html .= rtrim(lcfirst($fact->value()), ".");
            }

            $date = $this->printDate($fact);
            if ($date) {
                $html .= ' (' . trim($date) . ')';
            }
        }
        return $html;
    }

    /**
     * Print the lifespan of this person
     *
     * @param Individual $person
     * @param bool $is_spouse
     *
     * @return string
     */
    protected function printLifespan(Individual $person, bool $is_spouse = false): string
    {
        $html = '';

        $is_bfact = false;
        foreach (Gedcom::BIRTH_EVENTS as $event) {
            $bfact = $person->facts([$event])->first();
            if ($bfact) {
                $bdate     = $this->printDate($bfact);
                $bplace     = $this->printPlace($bfact);

                if ($bdate || $bplace) {
                    $is_bfact     = true;
                    $html         .= $this->printBirthText($person, $event, $is_spouse) . $bdate . $bplace;
                    break;
                }
            }
        }

        $is_dfact = false;
        foreach (Gedcom::DEATH_EVENTS as $event) {
            $dfact = $person->facts([$event])->first();
            if ($dfact) {
                $ddate     = $this->printDate($dfact);
                $dplace     = $this->printPlace($dfact);

                if ($ddate || $dplace) {
                    $is_dfact     = true;
                    $html         .= $this->printDeathText($person, $event, $is_bfact) . $ddate . $dplace;
                    break;
                }
            }
        }

        if ($is_bfact && $is_dfact && isset($bdate) && isset($ddate)) {
            $html .= $this->printAgeAtDeath($bfact, $dfact);
        }

        return $html;
    }

    /**
     * Print the relationship between spouses (optional)
     *
     * @param Individual $person
     * @param Individual $spouse
     *
     * @return string
     */
    protected function printRelationship(Individual $person, Individual $spouse): string
    {
        $html = '';
        if ($this->options('check-relationship')) {
            $relationship = $this->checkRelationship($person, $spouse);
            if ($relationship) {
                $html .= ' (' . $relationship . ')';
            }
        }
        return $html;
    }

    /**
     * Print the birth text (born or baptized)
     *
     * @param Individual $person
     * @param mixed $event
     * @param bool $is_spouse
     *
     * @return string
     */
    protected function printBirthText(Individual $person, $event, bool $is_spouse = false): string
    {
        $html = '';
        switch ($event) {
            case 'BIRT':
                if ($is_spouse == true) {
                    $html .= '. ';
                    if ($person->isDead()) {
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PAST', 'She was born') : $html     .= I18N::translateContext('PAST', 'He was born');
                    } else {
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PRESENT', 'She was born') : $html     .= I18N::translateContext('PRESENT', 'He was born');
                    }
                } else {
                    $this->printParents($person) || $this->printOccupations($person) ? $html     .= ', ' : $html     .= ' ';
                    if ($person->isDead()) {
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PAST (FEMALE)', 'was born') : $html     .= I18N::translateContext('PAST (MALE)', 'was born');
                    } else {
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PRESENT (FEMALE)', 'was born') : $html     .= I18N::translateContext('PRESENT (MALE)', 'was born');
                    }
                }
                break;
            case 'BAPM':
            case 'CHR':
                if ($is_spouse == true) {
                    $html .= '. ';
                    if ($person->isDead()) {
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PAST', 'She was baptized') : $html     .= I18N::translateContext('PAST', 'He was baptized');
                    } else {
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PRESENT', 'She was baptized') : $html     .= I18N::translateContext('PRESENT', 'He was baptized');
                    }
                } else {
                    $this->printParents($person) || $this->printOccupations($person) ? $html     .= ', ' : $html     .= ' ';
                    if ($person->isDead()) {
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PAST (FEMALE)', 'was baptized') : $html     .= I18N::translateContext('PAST (MALE)', 'was baptized');
                    } else {
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PRESENT (FEMALE)', 'was baptized') : $html     .= I18N::translateContext('PRESENT (MALE)', 'was bapitized');
                    }
                }
                break;
        }
        return $html;
    }

    /**
     * Print the death text (death or buried)
     *
     * @param Individual $person
     * @param string $event
     * @param bool $is_bfact
     *
     * @return string
     */
    protected function printDeathText(Individual $person, string $event, bool $is_bfact): string
    {
        $html = '';
        switch ($event) {
            case 'DEAT':
                if ($is_bfact) {
                    $html     .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
                    $person->sex() == 'F' ? $html     .= I18N::translateContext('FEMALE', 'died') : $html     .= I18N::translateContext('MALE', 'died');
                } else {
                    $person->sex() == 'F' ? $html     .= '. ' . I18N::translate('She died') : $html     .= '. ' . I18N::translate('He died');
                }
                break;
            case 'BURI':
                if ($is_bfact) {
                    $html     .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
                    $person->sex() == 'F' ? $html     .= I18N::translateContext('FEMALE', 'was buried') : $html     .= I18N::translateContext('MALE', 'was buried');
                } else {
                    $person->sex() == 'F' ? $html     .= '. ' . I18N::translate('She was buried') : $html     .= '. ' . I18N::translate('He was buried');
                }
                break;
            case 'CREM':
                if ($is_bfact) {
                    $html     .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
                    $person->sex() == 'F' ? $html     .= I18N::translateContext('FEMALE', 'was cremated') : $html     .= I18N::translateContext('MALE', 'was cremated');
                } else {
                    $person->sex() == 'F' ? $html     .= '. ' . I18N::translate('She was cremated') : $html     .= '. ' . I18N::translate('He was cremated');
                }
                break;
        }
        return $html;
    }

    /**
     * Print the age at death/bury
     *
     * @param Fact $bfact
     * @param Fact $dfact
     *
     * @return string
     */
    protected function printAgeAtDeath(Fact $bfact, Fact $dfact): string
    {
        $bdate     = $bfact->date();
        $ddate     = $dfact->date();
        $html     = '';
        if ($bdate->isOK() && $ddate->isOK() && $this->isDateDMY($bfact) && $this->isDateDMY($dfact)) {
            $ageAtDeath = (string) new Age($bdate, $ddate);
            if ($ageAtDeath < 2) {
                $html .= ' ' . /* I18N: %s is the age of death in days/months; %s is a string, e.g. at the age of 2 months */ I18N::translateContext('age in days/months', 'at the age of %s', $ageAtDeath);
            } else {
                $html .= ' ' . /* I18N: %s is the age of death in years; %s is a number, e.g. at the age of 40. If necessary add the term 'years' (always plural) to the string */ I18N::translateContext('age in years', 'at the age of %s', filter_var($ageAtDeath, FILTER_SANITIZE_NUMBER_INT));
            }
        }
        return $html;
    }

    /**
     * Function to print dates with the right syntax
     *
     * @param Fact $fact
     *
     * @return string
     */
    protected function printDate(Fact $fact): ?string
    {
        $date = $fact->date();
        if ($date && $date->isOK()) {
            if (preg_match('/^(FROM|BET|TO|AND|BEF|AFT|CAL|EST|INT|ABT) (.+)/', $fact->attribute('DATE'))) {
                return ' ' . /* I18N: Date prefix for date qualifications, like estimated, about, calculated, from, between etc.
				Leave the string empty if your language don't need such a prefix. If you do need this prefix, add an extra space at the end
				of the string to separate the prefix from the date. It is correct the source text is empty, because the source language (en-US)
				does not need this string. */
                    I18N::translateContext('prefix before dates with date qualifications, followed right after the words birth, death, married, divorced etc. Read the comment for more details.', ' ') . $date->Display();
            }
            if ($date->minimumDate()->day > 0) {
                return ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before dateformat dd-mm-yyyy', 'on ') . $date->Display();
            }
            if ($date->minimumDate()->month > 0) {
                return ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before dateformat mmm yyyy', 'in ') . $date->Display();
            }
            if ($date->minimumDate()->year > 0) {
                return ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before dateformat yyyy', 'in ') . $date->Display();
            }
        }

        return null;
    }

    /**
     * Print places
     *
     * @param Fact $fact
     *
     * @return string
     */
    protected function printPlace(Fact $fact): ?string
    {
        $place = $fact->attribute('PLAC');
        if ($place) {
            $place = new Place($place, $this->tree);
            $html  = ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before placesnames', 'in ');
            $html .= $place->fullName();

            return $html;
        }

        return null;
    }

    protected function printFollowLink(Individual $child): string
    {
        if ($this->isPage()) {

            $request = app(ServerRequestInterface::class);
            assert($request instanceof ServerRequestInterface);

            $xref  = Validator::attributes($request)->string('xref');
            $page  = Validator::attributes($request)->integer('page');

            $limit = $this->options('page-limit');

            if ($this->generation === $page * $limit) {
                $page = $page + 1;
            }

            $child_family = $this->getFamily($child);

            $text = I18N::translate('follow') . ' ' . ($this->generation + 1) . '.' . $this->index;
            $url  = $this->getUrl($this->tree, $xref, $this->type, $page);

            if ($child_family) {
                $this->index++;
                return ' - <a class="jc-scroll" href="' . $url . '#' . $child->xref() . '">' . $text . '</a>';
            } else {
                return '';
            }
        }
        return '';
    }

    /**
     * Get individual object from xref
     *
     * @param string $xref
     *
     * @return object
     */
    public function getPerson(string $xref): ?object
    {
        return Registry::individualFactory()->make($xref, $this->tree);
    }

    /**
     * Check if the rootperson is accessible
     *
     * @return object
     */
    protected function checkRootAccess($root_xref): object
    {
        return Auth::checkIndividualAccess($this->getPerson($root_xref));
    }

    /**
     * Get the family object of an individual
     *
     * @param Individual $person
     *
     * @return object
     */
    public function getFamily(Individual $person): ?object
    {
        foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $family) {
            return $family;
        }

        return null;
    }

    /**
     * Get an array of xrefs for the next descendant generation of this person
     *
     * @param string $xref
     *
     * @return array
     */
    private function getNextGen(string $xref): array
    {
        $person     = $this->getPerson($xref);
        $ng         = [];
        foreach ($person->spouseFamilies() as $family) {
            $children = $family->children();
            if ($children) {
                foreach ($children as $key => $child) {
                    $key              = $family->xref() . '-' . $key; // be sure the index number is unique.
                    $ng[$key]['xref']  = $child->xref();
                    // does this child have descendants?
                    $ng[$key]['descendants'] = count($child->spouseFamilies(Auth::PRIV_HIDE)) > 0;
                }
            }
        }
        return $ng;
    }

    /**
     * check if a person has parents in the same generation
     * this function prevents the same person from being listed twice
     *
     * @param Individual $person
     *
     * @return bool
     */
    public function hasParentsInSameGeneration(Individual $person): bool
    {
        $parents = $person->childFamilies()->first();;
        if ($parents) {
            $father     = $parents->husband();
            $mother     = $parents->wife();
            if ($father) {
                $father = $father->xref();
            }
            if ($mother) {
                $mother = $mother->xref();
            }
            if (in_array($father, $this->xrefs) || in_array($mother, $this->xrefs)) {
                return true;
            }
        }

        return false;
    }

    /**
     * check if this date has any date qualifiers. Return true if no date qualifiers are found.
     *
     * @param Fact $fact
     *
     * @return bool
     */
    private function isDateDMY(Fact $fact): bool
    {
        if ($fact && !preg_match('/^(FROM|BET|TO|AND|BEF|AFT|CAL|EST|INT|ABT) (.+)/', $fact->attribute('DATE'))) {
            return true;
        }

        return false;
    }

    /**
     * Check (blood) relationship between partners
     * See: app\Module\RelationshipsChartModule.php
     *
     * @param Individual $person
     * @param Individual $spouse
     *
     * @return string
     */
    private function checkRelationship(Individual $person, Individual $spouse): string
    {
        $tree = $person->tree();
        $paths = $this->calculateRelationships($person, $spouse);

        $language = app(ModuleService::class)
            ->findByInterface(ModuleLanguageInterface::class, true)
            ->first(fn (ModuleLanguageInterface $language): bool => $language->locale()->languageTag() === I18N::languageTag());

        foreach ($paths as $path) {
            $relationships = $this->oldStyleRelationshipPath($tree, $path);

            $nodes = Collection::make($path)
                ->map(static function (string $xref, int $key) use ($tree): GedcomRecord {
                    if ($key % 2 === 0) {
                        return Registry::individualFactory()->make($xref, $tree);
                    }
                    return  Registry::familyFactory()->make($xref, $tree);
                });

            foreach ($path as $n => $xref) {
                if ($n % 2 === 1) {
                    switch ($relationships[$n]) {
                        case 'sis':
                        case 'bro':
                        case 'sib':
                            return $this->relationship_service->nameFromPath($nodes->all(), $language);
                    }
                }
                unset($xref);
            }
        }

        return '';
    }

    /**
     * Calculate the shortest paths - or all paths - between two individuals.
     *
     * Retrieve the private function from app/RelationshipsChartModule
     * https://stackoverflow.com/a/40441769
     *
     * @param Individual $individual1
     * @param Individual $individual2
     *
     * @return string[][]
     */
    private function calculateRelationships(Individual $individual1, Individual $individual2): array
    {
        $calculateRelationships = function ($individual1, $individual2) {
            return app(RelationshipsChartModule::class)->calculateRelationships($individual1, $individual2, 0, true);
        };

        return $calculateRelationships->call(app(RelationshipsChartModule::class), $individual1, $individual2);
    }

    /**
     * Convert a path (list of XREFs) to an "old-style" string of relationships.
     * Return an empty array, if privacy rules prevent us viewing any node.
     *
     * Retrieve the private function from app/RelationshipsChartModule
     * https://stackoverflow.com/a/40441769
     *
     * @param Tree     $tree
     * @param string[] $path Alternately Individual / Family
     *
     * @return string[]
     */
    private function oldStyleRelationshipPath(Tree $tree, array $path): array
    {
        $oldStyleRelationshipPath = function ($tree, $path) {
            return app(RelationshipsChartModule::class)->oldStyleRelationshipPath($tree, $path);
        };

        return $oldStyleRelationshipPath->call(app(RelationshipsChartModule::class), $tree, $path);
    }

    /**
     * Check if this is a private record
     * $records can be an array of xrefs or an array of objects
     *
     * TODO: turn $this->xrefs ($records) into a Collection
     *
     * @param mixed $records
     * @param bool $xrefs
     *
     * @return bool
     */
    public function checkPrivacy(mixed $records, bool $xrefs = false): bool
    {
        $count = 0;
        foreach ($records as $person) {
            if ($xrefs) {
                $person = $this->getPerson($person);
            }
            if ($person->canShow()) {
                $count++;
            }
        }
        if ($count < 1) {
            return true;
        }

        return false;
    }

    /**
     * Determine if the family parents are married.
     *
     * Don't use the default function because we want to privatize the record but display the name
     * and the parents of the spouse if the spouse him/herself is not private.
     *
     * @param Family $family
     *
     * @return bool
     */
    private function getMarriage(Family $family): bool
    {
        $record = Registry::gedcomRecordFactory()->make($family->xref(), $this->tree);
        foreach ($record->facts(['MARR'], false, Auth::PRIV_HIDE) as $fact) {
            if ($fact) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if this person is an adopted or foster child
     *
     * @param Individual $person
     * @param Family $parents
     *
     * @return string
     */
    private function checkPedi(Individual $person, Family $parents): string
    {
        $pedi = "";
        foreach ($person->facts(['FAMC']) as $fact) {
            if ($fact->target() === $parents) {
                $pedi = $fact->attribute('PEDI');
                break;
            }
        }
        return $pedi;
    }

    /**
     * Determine if we are on the Fancy Treeview Page (and not on the individual page tab)
     * @return bool
     */
    private function isPage(): bool
    {
        $request = app(ServerRequestInterface::class);
        assert($request instanceof ServerRequestInterface);

        $route = Validator::attributes($request)->route();

        if ($route->name === static::class) {
            return true;
        }

        return false;
    }

    /**
     * Get the url for the Fancy treeview page
     *
     * @param Tree $tree
     * @param string $xref
     * @param string $type
     * @param int $page
     *
     * @return string
     */
    public function getUrl(Tree $tree, string $xref, string $type = '', int $page = 1): string
    {
        if ($type === '') {
            $type = $this->type;
        }

        return route(static::class, [
            'tree'          => $tree->name(),
            'module'        => str_replace("_", "", $this->name()),
            'name'          => $this->getslug(strip_tags($this->printName($this->getPerson($xref)))),
            'xref'          => $xref,
            'type'          => $type,
            'page'          => $page
        ]);
    }

    /**
     * Get the url slug for this page
     *
     * @param string $string
     *
     * @return string
     */
    public function getSlug(string $string): string
    {
        return preg_replace('/\s+/', '-', strtolower(preg_replace("/&([a-z])[a-z]+;/i", "$1", htmlentities($string))));
    }
};
