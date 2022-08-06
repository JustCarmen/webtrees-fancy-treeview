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
use Fisharebest\Webtrees\Services\TreeService;
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
use Fisharebest\Webtrees\Statistics\Service\CountryService;

class FancyTreeviewModule extends AbstractModule implements ModuleCustomInterface, ModuleGlobalInterface, ModuleTabInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleGlobalTrait;
    use ModuleMenuTrait;
    use ModuleTabTrait;
    use ModuleConfigTrait;

    // Route
    protected const ROUTE_URL = '/tree/{tree}/{module}/{page}/{type}/{pid}/{generations}';

    // Module constants
    public const CUSTOM_AUTHOR = 'JustCarmen';
    public const CUSTOM_VERSION = '2.0.0-dev';
    public const GITHUB_REPO = 'webtrees-fancy-treeview';
    public const AUTHOR_WEBSITE = 'https://justcarmen.nl';
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . '/modules-webtrees-2/fancy-treeview/';

    // Limits
    protected const MINIMUM_GENERATIONS = 2;
    protected const MAXIMUM_GENERATIONS = 20;

    // Image cache dir
    private const CACHE_DIR = Webtrees::DATA_DIR . 'ftv-cache/';

    // Module variables
    public array $pids;
    public int $generation;
    public int $index;
    public string $type; // 'des' or 'anc'

    private Tree $tree;
    private CountryService $country_service;
    private RelationshipService $relationship_service;
    private TreeService $tree_service;

    /**
     * Fancy Treeview constructor.
     *
     * @param RelationshipService $relationship_service
     */
    public function __construct(CountryService $country_service, RelationshipService $relationship_service, TreeService $tree_service)
    {
        $this->country_service = $country_service;
        $this->relationship_service = $relationship_service;
        $this->tree_service = $tree_service;
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
        return I18N::translate('A Fancy overview of the descendants of one family (branch) in a narrative way.');
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
        $pid            = Validator::attributes($request)->string('pid');
        $generations    = Validator::attributes($request)->isBetween(self::MINIMUM_GENERATIONS, self::MAXIMUM_GENERATIONS)->integer('generations');
        $this->type     = Validator::attributes($request)->string('type');

        $page_title = $this->getPreference('page-title');

        if ($this->type === 'anc') {
            $page_body  = $this->printAncestorsPage($pid, $generations);
        } else {
            $page_body  = $this->printDescendantsPage($pid, $generations);
        }

        return $this->viewResponse($this->name() . '::page', [
            'tree'          => $this->tree,
            'title'         => $this->title(),
            'module'        => $this->name(),
            'is_admin'      => Auth::isAdmin(),
            'page_title'    => $page_title,
            'page_body'     => $page_body
        ]);
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
        return (bool) $this->options('fancy-treeview-tab');
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
        $generations = self::MAXIMUM_GENERATIONS;
        $limit       = 3;

        return view($this->name() . '::tab', [
            'tab_content' => $this->printDescendantsPage($xref, $generations, $limit)
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
            'use-fullname'          => '0',
            'numblocks'             => '0',
            'check-relationship'    => '0',
            'show-singles'          => '0',
            'show-places'           => '1',
            'use-gedcom-places'     => '0',
            'country'               => '',
            'show-occu'             => '1',
            'thumb-size'            => '80',
            'crop-thumbs'           => '0',
            'media-type-photo'      => '1', // new option (boolean)
            'fancy-treeview-tab'    => '1'
        };

        return $this->getPreference($option, $default);
    }

    /**
     * Print the Fancy Treeview page without the paging option as in webtrees 1
     * We will implement that later
     *
     * @param string $pid
     * @param int $generations
     *
     * @return string
     */
    public function printDescendantsPage(string $pid, int $generations, int $limit = 0): string
    {
        $this->generation = 1;
        $root_pid         = $pid; // save value for read more link
        $this->pids       = [$pid];
        $this->type       = 'des';

        // check root access
        $this->checkRootAccess($root_pid);

        $html = $this->printGeneration();

        while (count($this->pids) > 0 && $this->generation < $generations) {
            $pids = $this->pids;
            unset($this->pids); // empty the array (will be filled with the next generation)

            foreach ($pids as $pid) {
                $next_gen[] = $this->getNextGen($pid);
            }

            foreach ($next_gen as $descendants) {
                if (count($descendants) > 0) {
                    foreach ($descendants as $descendant) {
                        if ((bool) $this->options('show-singles') || (bool) $descendant['desc']) {
                            $this->pids[] = $descendant['pid'];
                        }
                    }
                }
            }

            if (!empty($this->pids)) {
                if ($this->generation === $limit) {
                    $html .= $this->printReadMoreLink($root_pid);
                    return $html;
                } else {
                    $this->generation++;
                    $html .= $this->printGeneration();
                    unset($next_gen, $descendants, $pids);
                }
            } else {
                return $html;
            }
        }

        return $html;
    }

     /**
     * Print the Fancy Treeview page without the paging option as in webtrees 1
     * We will implement that later
     *
     * @param string $pid
     * @param int $generations
     *
     * @return string
     */
    public function printAncestorsPage(string $pid, int $generations, int $limit = 0): string
    {
        $this->generation = 1;
        $root_pid         = $pid; // save value for read more link
        $this->pids       = [$pid];
        $this->type       = 'anc';

        // check root access
        $this->checkRootAccess($root_pid);

        $html = $this->printGeneration();

        while (count($this->pids) > 0 && $this->generation < $generations) {
            $pids = $this->pids;
            unset($this->pids); // empty the array (will be filled with the next generation)

            foreach ($pids as $pid) {
                $person  = $this->getPerson($pid);
                $parents = $person->childFamilies()->first();;
                if ($parents) {
                    $father     = $parents->husband();
                    $mother     = $parents->wife();
                    if ($father) {
                        $this->pids[] = $father->xref();
                    }
                    if ($mother) {
                        $this->pids[] = $mother->xref();
                    }
                }
            }

            if (!empty($this->pids)) {
                if ($this->generation === $limit) {
                    $html .= $this->printReadMoreLink($root_pid);
                    return $html;
                } else {
                    $this->generation++;
                    $html .= $this->printGeneration();
                    unset($prev_gen, $ancestors, $pids);
                }
            } else {
                return $html;
            }
        }

        return $html;
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
            'pids'          => $this->pids,
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
    protected function printReadMoreLink(string $root): string
    {
        return View($this->name() . '::readmore-link', ['url' => $this->getUrl($this->tree, $root)]);
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
        if ($person->canShow()) {
            $html = '<div class="jc-parents-block">';

            $html .= '<div class="jc-person-block d-flex col">';

            $html .= '<div class="jc-person-block-image mt-1 mb-3">';
            if ($person->findHighlightedMediaFile() !== null && (bool) $this->options('media-type-photo') ? $person->findHighlightedMediaFile()->type() === 'photo' : $person->findHighlightedMediaFile()) {
                $html .= $person->displayImage((int) $this->options('thumb-size'), (int) $this->options('thumb-size'), (bool) $this->options('crop-thumbs') ? 'crop' : 'contain', ['class' => 'jc-ftv-thumbnail']);
            }
            $html .= '</div>';

            $html .= '<div class="jc-person-block-text"><p>' . $this->printNameUrl($person, $person->xref());

            if ((bool) $this->options('show-occu')) {
                $html .= $this->printOccupations($person);
            }

            $html .= $this->printParents($person) . $this->printLifespan($person) . '.</p>';

            $html .= '</div></div>';

            if ($this->type === 'des') {
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

                            $child_family = $this->getFamily($child);

                            // TODO: do not load this part of the code in the fancy treeview tab on the individual page.
                            // if (WT_SCRIPT_NAME !== 'individual.php') { // old code
                            $text_follow = I18N::translate('follow') . ' ' . ($this->generation + 1) . '.' . $this->index;
                            if ($child_family) {
                                $html .= ' - <a class="jc-scroll" href="#' . $child_family->xref() . '">' . $text_follow . '</a>';
                                $this->index++;
                            } elseif ((bool) $this->options('show-singles')) {
                                $html .= ' - <a class="jc-scroll" href="#' . $child->xref() . '">' . $text_follow . '</a>';
                                $this->index++;
                            }
                            // }
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
        if ($place && (bool) $this->options('show-places')) {
            $place     = new Place($place, $this->tree);
            $html     = ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before placesnames', 'in ');
            if ((bool) $this->options('use-gedcom-places')) {
                $html .= $place->shortName();
            } else {
                $country     = $this->options('country');
                $new_place     = array_reverse(explode(", ", $place->gedcomName()));
                if (!empty($country) && $new_place[0] == $country) {
                    unset($new_place[0]);
                    $html .= '<span dir="auto">' . e(implode(', ', array_reverse($new_place))) . '</span>';
                } else {
                    $html .= $place->fullName();
                }
            }
            return $html;
        }

        return null;
    }

    /**
     * Get individual object from PID
     *
     * @param string $pid
     *
     * @return object
     */
    public function getPerson(string $pid): ?object
    {
        return Registry::individualFactory()->make($pid, $this->tree);
    }

    /**
     * Check if the rootperson is accessible
     *
     * @return object
     */
    protected function checkRootAccess($root_pid): object
    {
        return Auth::checkIndividualAccess($this->getPerson($root_pid));
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
     * @param string $pid
     *
     * @return array
     */
    private function getNextGen(string $pid): array
    {
        $person     = $this->getPerson($pid);
        $ng         = [];
        foreach ($person->spouseFamilies() as $family) {
            $children = $family->children();
            if ($children) {
                foreach ($children as $key => $child) {
                    $key              = $family->xref() . '-' . $key; // be sure the index number is unique.
                    $ng[$key]['pid']  = $child->xref();
                    // does this child have descendants?
                    $ng[$key]['desc'] = count($child->spouseFamilies(Auth::PRIV_HIDE)) > 0;
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
            if (in_array($father, $this->pids) || in_array($mother, $this->pids)) {
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
     * TODO: turn $this->pids ($records) into a Collection
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
     * @return array
     */
    public function getCountryList(): array
    {
        $all_countries = $this->country_service->getAllCountries();
        foreach ($all_countries as $country_name) {
            $country_names[$country_name] = $country_name; // set the country name as key to display as option value
        }

        return $country_names;
    }

    /**
     * Get the url for the Fancy treeview page
     *
     * @param string $pid
     *
     * @return string
     */
    private function getUrl(Tree $tree, string $pid): string
    {
        return route(static::class, [
            'tree' => $tree->name(),
            'module' => str_replace("_", "", $this->name()),
            'page' => $this->getslug($this->getPreference('page-title', 'Fancy Treeview Pagina')),
            'pid' =>  $pid,
            'type' => $this->type,
            'generations' => self::MAXIMUM_GENERATIONS
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
