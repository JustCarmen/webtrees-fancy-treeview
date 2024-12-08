<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Illuminate\Support\Str;
use Fisharebest\Webtrees\Age;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Note;
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
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Fisharebest\Localization\Translation;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleTabTrait;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Module\ModuleBlockTrait;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleTabInterface;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleBlockInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Services\RelationshipService;
use Fisharebest\Webtrees\Module\ModuleLanguageInterface;
use Fisharebest\Webtrees\Module\RelationshipsChartModule;
use Fisharebest\Webtrees\Statistics\Service\CountryService;

class FancyTreeviewModule extends AbstractModule
implements ModuleCustomInterface, ModuleConfigInterface, ModuleGlobalInterface, ModuleTabInterface,
ModuleMenuInterface, ModuleBlockInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleConfigTrait;
    use ModuleGlobalTrait;
    use ModuleTabTrait;
    use ModuleMenuTrait;
    use ModuleBlockTrait;

    // Route
    protected const ROUTE_URL = '/tree/{tree}/jc-fancy-treeview/{xref}/{name}/{type}/{page}';

    // Module constants
    public const CUSTOM_AUTHOR = 'JustCarmen';
    public const CUSTOM_VERSION = '2.1.0';
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
    public array $pedigree_collapse;

    private Tree $tree;
    private CountryService $country_service;
    private ModuleService $module_service;
    private RelationshipService $relationship_service;

    /**
     * Fancy Treeview constructor.
     *
     * @param CountryService $countryService
     * @param ModuleService $module_service
     * @param RelationshipService $relationship_service
     */
    public function __construct(CountryService $country_service, ModuleService $module_service, RelationshipService $relationship_service)
    {
        $this->country_service = $country_service;
        $this->module_service = $module_service;
        $this->relationship_service = $relationship_service;
        $this->pedigree_collapse = [];
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
        return I18N::translate('A narrative overview of the descendants or ancestors of one family (branch).');
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
        $router_container = Registry::container()->get(RouterContainer::class);
        assert($router_container instanceof RouterContainer);

        $router_container->getMap()
            ->get(static::class, static::ROUTE_URL, $this);

        // Register a namespace for our views.
        View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

         // Temporary code. Due to changes in this version of the module we need to reset the option place format
         if (!in_array($this->getPreference('places-format'), ['custom', 'webtrees', 'none'])) {
            $this->setPreference('places-format', 'custom');
        }
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
     * Raw content, to be added at the end of the <body> element.
     * Typically, this will be <script> elements.
     *
     * https://getbootstrap.com/docs/5.2/components/tooltips/#enable-tooltips
     *
     * @return string
     */
    public function bodyContent(): string
    {
        return '
            <script>
                const tooltipTriggerList = document.querySelectorAll(\'[data-bs-toggle="tooltip"]\')
                const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

                $(\'[data-bs-toggle="tooltip"]\').on(\'click\', function (e) {
                    e.preventDefault();
                });
            </script>';
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
     * Set the default options.
     *
     * @param string $option
     *
     * @return string
     */
    public function options(string $option): string
    {
        $default = [
            'list-type'             => 'descendants', // Type 'Descendants' or 'Ancestors'
            'page-limit'            => '3',  // integer, number of generation blocks per page
            'tab-limit'             => '3',  // integer, number of generation blocks per tab
            'show-singles'          => '0',  // boolean
            'check-relationship'    => '0',  // boolean
            'thumb-size'            => '80', // integer
            'crop-thumbs'           => '0',  // boolean
            'media-type-photo'      => '0',  // boolean
            'places-format'         => 'custom', // string
            'places-format-hc'      => 'full', // string
            'places-format-oc'      => 'full', // string
            'countries-format'      => 'full', // string
            'show-home-country'     => '1', // boolean
            'home-country'          => '', // string
            'show-occupations'      => '1',  // boolean
            'show-agencies'         => '1',  // boolean
            'gedcom-occupation'     => '0',  // boolean
            'level1-notes'          => '0'   // boolean
        ];

        return $this->getPreference($option, $default[$option]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'title'                 => $this->title(),
            'country_list'          => $this->getCountryList(),
            'list_type'             => $this->options('list-type'),
            'page_limit'            => $this->options('page-limit'),
            'tab_limit'             => $this->options('tab-limit'),
            'show_singles'          => $this->options('show-singles'),
            'check_relationship'    => $this->options('check-relationship'),
            'thumb_size'            => $this->options('thumb-size'),
            'crop_thumbs'           => $this->options('crop-thumbs'),
            'media_type_photo'      => $this->options('media-type-photo'),
            'places_format'         => $this->options('places-format'),
            'places_format_hc'      => $this->options('places-format-hc'),
            'places_format_oc'      => $this->options('places-format-oc'),
            'countries_format'      => $this->options('countries-format'),
            'show_home_country'     => $this->options('show-home-country'),
            'home_country'          => $this->options('home-country'),
            'show_occupations'      => $this->options('show-occupations'),
            'show_agencies'         => $this->options('show-agencies'),
            'gedcom_occupation'     => $this->options('gedcom-occupation'),
            'level1_notes'          => $this->options('level1-notes')
        ]);
    }

    /**
     * Save the user preference.
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        if ($params['save'] === '1') {
            $this->setPreference('list-type', $params['list-type']);
            $this->setPreference('page-limit', $params['page-limit']);
            $this->setPreference('tab-limit', $params['tab-limit']);
            $this->setPreference('show-singles',  $params['show-singles']);
            $this->setPreference('check-relationship',  $params['check-relationship']);
            $this->setPreference('thumb-size',  $params['thumb-size']);
            $this->setPreference('crop-thumbs', $params['crop-thumbs']);
            $this->setPreference('media-type-photo', $params['media-type-photo']);
            $this->setPreference('places-format', $params['places-format']);
            $this->setPreference('places-format-hc', $params['places-format-hc']);
            $this->setPreference('places-format-oc', $params['places-format-oc']);
            $this->setPreference('countries-format', $params['countries-format']);
            $this->setPreference('show-home-country', $params['show-home-country']);
            $this->setPreference('home-country', $params['home-country']);
            $this->setPreference('show-occupations', $params['show-occupations']);
            $this->setPreference('show-agencies', $params['show-agencies']);
            $this->setPreference('gedcom-occupation', $params['gedcom-occupation']);
            $this->setPreference('level1-notes', $params['level1-notes']);

            $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
            FlashMessages::addMessage($message, 'success');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAddMenuItemAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $xref = $request->getQueryParams()['xref'];
        $type = $request->getQueryParams()['type'];
        $url  = $request->getQueryParams()['url'];

        $this->tree = $tree;
        $this->type = $type;

        if ($type === 'ancestors') {
            $old_list = $this->getPreference($tree->id() . '-menu-ancestors', '');
            $new_list = $old_list === '' ? $xref : $old_list . ', ' . $xref;

            $this->setPreference($tree->id() . '-menu-ancestors',  implode(', ', array_unique(explode(', ', $new_list))));
        } else {
            $old_list = $this->getPreference($tree->id() . '-menu-descendants', '');
            $new_list = $old_list === '' ? $xref : $old_list . ', ' . $xref;

            $this->setPreference($tree->id() . '-menu-descendants', implode(', ', array_unique(explode(', ', $new_list))));
        }

        return redirect($url);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postRemoveMenuItemAction(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();

        $xref = $request->getQueryParams()['xref'];
        $type = $request->getQueryParams()['type'];
        $url  = $request->getQueryParams()['url'];

        $this->tree = $tree;
        $this->type = $type;

        if ($type === 'ancestors') {
            $items = explode(', ', $this->getPreference($tree->id() . '-menu-ancestors', ''));
            if (($key = array_search($xref, $items)) !== false) {
                unset($items[$key]);
            }
            $this->setPreference($tree->id() . '-menu-ancestors', implode(', ', $items));
        } else {
            $items = explode(', ', $this->getPreference($tree->id() . '-menu-descendants', ''));
            if (($key = array_search($xref, $items)) !== false) {
                unset($items[$key]);
            }
            $this->setPreference($tree->id() . '-menu-descendants', implode(', ', $items));
        }

        return redirect($url);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getHelpTextAction(): ResponseInterface
    {
        $html = view('modals/help', [
            'title' => I18N::translate('Fancy Treeview Help'),
            'text'  => View($this->name() . '::helptext')
        ]);

        return response($html);
    }

    /**
     * A menu, to be added to the main application menu.
     *
     * @param Tree $tree
     *
     * @return Menu|null
     */
    public function getMenu(Tree $tree): ?Menu
    {
        $ancestors   = array_filter(explode(', ', $this->getPreference($tree->id() . '-menu-ancestors')));
        $descendants = array_filter(explode(', ', $this->getPreference($tree->id() . '-menu-descendants')));

        $this->tree = $tree;

        $submenu = [];
        foreach ($ancestors as $xref) {
            $person = $this->getPerson($xref);
            if ($person && $person->canShow()) {
                $submenu[] = new Menu(I18N::translate('Ancestors of %s', $person->fullName()), $this->getUrl($tree, $xref, 'ancestors'), 'menu-fancy-treeview-ancestors', ['rel' => 'nofollow']);
            }
        }

        foreach ($descendants as $xref) {
            $person = $this->getPerson($xref);
            if ($person && $person->canShow()) {
                $submenu[] = new Menu(I18N::translate('Descendants of %s', $person->fullName()), $this->getUrl($tree, $xref, 'descendants'), 'menu-fancy-treeview-descendants', ['rel' => 'nofollow']);
            }
        }

        if (count($submenu) > 0) {
            sort($submenu);
            return new Menu(I18N::translate('Family tree overview'), '#', 'menu-fancy-treeview', ['rel' => 'nofollow'], $submenu);
        } else {
            return null;
        }
    }

    /**
     * Generate the HTML content of this block.
     *
     * @param Tree                 $tree
     * @param int                  $block_id
     * @param string               $context
     * @param array<string,string> $config
     *
     * @return string
     */
    public function getBlock(Tree $tree, int $block_id, string $context, array $config = []): string
    {
        $ancestors   = array_filter(explode(', ', $this->getPreference($tree->id() . '-menu-ancestors')));
        $descendants = array_filter(explode(', ', $this->getPreference($tree->id() . '-menu-descendants')));

        $this->tree = $tree;

        $links = [];
        foreach ($ancestors as $xref) {
            $person = $this->getPerson($xref);
            if ($person && $person->canShow()) {
                $links[] = [
                    'url'   => $this->getUrl($tree, $xref, 'ancestors'),
                    'title' => I18N::translate('Ancestors of %s', $person->fullName()),
                    'image' => $person->displayImage(30, 40, 'crop', ['class' => 'rounded-circle']),
                    'sort'  => $person->getAllNames()[0]['surn']
                ];
            }
        }

        foreach ($descendants as $xref) {
            $person = $this->getPerson($xref);
            if ($person && $person->canShow()) {
                $links[] = [
                    'url'   => $this->getUrl($tree, $xref, 'descendants'),
                    'title' => I18N::translate('Descendants of %s', $person->fullName()),
                    'image' => $person->displayImage(30, 40, 'crop', ['class' => 'rounded-circle']),
                    'sort'  => $person->getAllNames()[0]['surn']
                ];
            }
        }

        $key_values = array_column($links, 'sort');
        array_multisort($key_values, SORT_ASC, $links);

        if (count($links) > 0) {
            $content = view($this->name() . '::blockmodule', ['links' => $links]);
        } else {
            $content = view($this->name() . '::blockmodulehelptext');
        }

        if ($context !== self::CONTEXT_EMBED) {
            return view('modules/block-template', [
                'block'      => Str::kebab($this->name()),
                'id'         => $block_id,
                'config_url' => '',
                'title'      => e(I18N::translate('Family tree overview')),
                'content'    => $content,
            ]);
        }

        return $content;
    }

    /**
     * Should this block load asynchronously using AJAX?
     *
     * Simple blocks are faster in-line, more complex ones can be loaded later.
     *
     * @return bool
     */
    public function loadAjax(): bool
    {
        return false;
    }

    /**
     * Can this block be shown on the user’s home page?
     *
     * @return bool
     */
    public function isUserBlock(): bool
    {
        return false;
    }

    /**
     * Can this block be shown on the tree’s home page?
     *
     * @return bool
     */
    public function isTreeBlock(): bool
    {
        return true;
    }

    /**
     * The text that appears on the tab.
     *
     * @return string
     */
    public function tabTitle(): string
    {
        if ($this->options('list-type') === 'ancestors') {
            return I18N::translate('Ancestors and descendants');
        } else {
            return I18N::translate('Descendants and ancestors');
        }
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
        $request = Registry::container()->get(ServerRequestInterface::class);
        assert($request instanceof ServerRequestInterface);

        $tree   = Validator::attributes($request)->tree();
        $xref   = Validator::attributes($request)->isXref()->string('xref', '');
        $start  = 1; // always start with the current generation in tab view
        $limit  = (int) $this->options('tab-limit');

        $this->tree = $tree;

        return View($this->name() . '::tab', [
            'module'                        => $this,
            'tree'                          => $tree,
            'individual'                    => $individual,
            'list_type'                     => $this->options('list-type'),
            'tab_page_title_descendants'    => $this->printPageTitle($individual, 'descendants'),
            'tab_page_title_ancestors'      => $this->printPageTitle($individual, 'ancestors'),
            'tab_content_descendants'       => $this->printDescendantsPage($xref, $start, $limit),
            'tab_content_ancestors'         => $this->printAncestorsPage($xref, $start, $limit),
            'descendant_generations'        => $this->descendant_generations,
            'ancestor_generations'          => $this->ancestor_generations,
            'limit'                         => $limit,
            'start_page_readmore'           => ceil(($limit + 1) / (int) $this->options('page-limit'))
        ]);
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $xref = Validator::attributes($request)->isXref()->string('xref');
        $type = Validator::attributes($request)->string('type');

        $this->tree = $tree;
        $this->type = $type;

        $page       = $this->getPage();
        $page_title = $this->printPageTitle($this->getPerson($xref), $this->type);

        // determine the generation to start with
        $limit = (int) $this->options('page-limit');
        $start = ($page - 1) * $limit + 1;

        if ($this->type === 'ancestors') {
            $page_body   = $this->printAncestorsPage($xref, $start, $limit);
            $button_url  = $this->getUrl($tree, $xref, 'descendants');
            $button_text = I18N::translate('Show descendants');
            $generations = $this->ancestor_generations;
        } else {
            $page_body   = $this->printDescendantsPage($xref, $start, $limit);
            $button_url  = $this->getUrl($tree, $xref, 'ancestors');
            $button_text =  I18N::translate('Show ancestors');
            $generations = $this->descendant_generations;
        }

        $total_pages = (int) ceil($generations / $limit);

        return $this->viewResponse($this->name() . '::page', [
            'module'            => $this,
            'tree'              => $tree,
            'xref'              => $xref,
            'title'             => $this->title(),
            'page_title'        => $page_title,
            'page_body'         => $page_body,
            'button_url'        => $button_url,
            'button_text'       => $button_text,
            'generations'       => $generations,
            'current_page'      => $page,
            'total_pages'       => $total_pages,
            'limit'             => $limit
        ]);
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
        $this->xrefs = [$xref];
        $this->type  = 'descendants';
        $collection  = new Collection();

        if ($start === 1) {
            $html = $this->printGeneration();
        } else {
            $html = '';
        }

        while (count($this->xrefs) > 0) {

            // Put all xrefs of a generation in this collection.
            // If a combination of xrefs is repeated in another generation, we know we are dealing with an infinite loop.
            $collection->put($this->generation, $this->xrefs);

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
                // Once we have fetched the page we need to know the total number of generations for this individual,
                // but beware of infinite loops
                if ($collection->duplicates()->count() > 0) {
                    break;
                }
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
        $this->xrefs = [$xref];
        $this->type  = 'ancestors';
        $collection  = new Collection();

        if ($start === 1) {
            $html = $this->printGeneration();
        } else {
            $html = '';
        }

        while (count($this->xrefs) > 0) {

            // Put all xrefs of a generation in this collection.
            // If a combination of xrefs is repeated in another generation, we know we are dealing with an infinite loop
            $collection->put($this->generation, $this->xrefs);

            $this->generation++;

            $xrefs = $this->xrefs;
            unset($this->xrefs); // empty the array (will be filled with the next generation)

            foreach ($xrefs as $xref) {
                $person  = $this->getPerson($xref);
                $parents = $person->childFamilies()->first();
                if ($parents) {
                    $father     = $parents->husband();
                    $mother     = $parents->wife();
                    if ($father) {
                        $this->xrefs[] = $father->xref();
                    }
                    if ($mother) {
                        $this->xrefs[] = $mother->xref();
                    }
                    // check relationship and put the generation where a possible pedigree collapse occurs first into the collection
                    if ($this->options('check-relationship') && $father && $mother) {
                        $this->setPedigreeCollapse($this->generation + 1, $father, $mother);
                    }
                }
            }

            if (!empty($this->xrefs)) {
                unset($prev_gen, $ancestors, $xrefs);
                // Once we have fetched the page we need to know the total number of generations for this individual,
                // but beware of infinite loops
                if ($collection->duplicates()->count() > 0) {
                    break;
                }
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
            if ($this->isPage()) {
                return I18N::translate('Ancestors of %s', '<a href="' . e($person->url() . '#tab-' . $this->name()) . '">' . $person->fullName() . '</a>');
            } else {
                return I18N::translate('Ancestors of %s', $person->fullName());
            }
        } else {
            if ($this->isPage()) {
                return I18N::translate('Descendants of %s', '<a href="' . e($person->url() . '#tab-' . $this->name()) . '">' . $person->fullName() . '</a>');
            } else {
                return I18N::translate('Descendants of %s', $person->fullName());
            }
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
            'module'        => $this,
            'tree'          => $this->tree,
            'title'         => $this->title(),
            'generation'    => $this->generation,
            'xrefs'         => $this->xrefs,
            'page'          => $this->getPage(),
            'limit'         => $this->options('page-limit')
        ]);
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
                * The partnercount counts all the partners, married or not.
                */
                $spousecount = 0; $partnercount = 0;
                foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $family) {
                    $spouse = $family->spouse($person);
                    if ($spouse && $spouse->canShow()) {
                        $partnercount++;
                        if ($this->getMarriage($family)) {
                            $spousecount++;
                        }
                    }
                }
                /*
                * Now iterate thru spouses
                * $spouseindex is used for ordinal rather than array index
                * as not all families have a spouse
                * $spousecount is passed rather than doing each time inside function get_spouse
                */
                $spouseindex = 0; $partnerindex = 0; $current = false;
                foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $family) {
                    $spouse = $family->spouse($person);
                    if ($spouse && $spouse->canShow()) {
                        if ($partnerindex === $partnercount - 1) {
                            $current = $this->isCurrentRelation ($person, $spouse);
                        }
                        if ($this->getMarriage($family)) {
                            $html .= $this->printSpouse($family, $person, $spouse, $spouseindex, $spousecount, $current);
                            $spouseindex++;
                        } else {
                            $html .= $this->printPartner($family, $person, $spouse, $current);
                        }
                    }
                    $partnerindex++;
                }

                $html .= '</div></div>';

                // get children for each couple (could be none or just one, $spouse could be empty, includes children of non-married couples)
                foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $family) {
                    $spouse = $family->spouse($person);

                    $html .= $this->printChildren($family, $person, $spouse);
                }
            }

            if ($this->options('level1-notes')) {
                $html .= '<div class="jc-notes-block small">';
                foreach ($person->facts(['NOTE']) as $fact) {
                    $html .= '<div class="jc-note">';
                    if ($this->tree->getPreference('FORMAT_TEXT') === 'markdown') {
                        $html .= Registry::markdownFactory()->markdown($this->printNote($fact));
                    } else {
                        $html .= Registry::markdownFactory()->autolink($this->printNote($fact));
                    }
                    $html .= '</div>';
                }
                $html .= '</div>';
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
    protected function printSpouse(Family $family, Individual $person, Individual $spouse, int $i, int $count, bool $current): string
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
                    $html     .= $current ? I18N::translate('He is married to') : I18N::translate('He married');
                    break;
                case 'F':
                    $html     .= $current ? I18N::translate('She is married to') : I18N::translate('She married');
                    break;
                default:
                    $html     .= $current ? I18N::translate('This individual is married to') : I18N::translate('This individual married');
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
    protected function printPartner(Family $family, Individual $person, Individual $spouse, bool $current): string
    {

        $html = '<p>';

        switch ($person->sex()) {
            case 'M':
                $html     .= $current ? I18N::translate('He has a relationship with') : I18N::translate('He had a relationship with');
                break;
            case 'F':
                $html     .= $current ? I18N::translate('She has a relationship with') : I18N::translate('She had a relationship with');
                break;
            default:
                $html     .= $current ? I18N::translate('This individual has a relationship with') : I18N::translate('This individual had a relationship with');
                break;
        }

        $html .= ' ' . $this->printNameUrl($spouse);
        $html .= $this->printRelationship($person, $spouse);
        $html .= $this->printParents($spouse);

        if ($this->printLifespan($spouse, true)) {
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
                if ($person->isDead() || $spouse->isDead()) { // Past tense if at least one is dead
                    $html .= I18N::translateContext('Two parents/one child', 'had');
                } else {
                    $html .= I18N::translateContext('Two parents/one child', 'have');
                }
            } else {
                if ($person->isDead()) {
                    $html .= I18N::translateContext('One parent/one child', 'had');
                } else {
                    $html .= I18N::translateContext('One parent/one child', 'has');
                }
            }
            $html .= ' ' . I18N::translate('no children') . '.</p></div>';
        } else {
            $children = $family->children();
            if ($children->isNotEmpty()) {
                if ($this->checkPrivacy($children)) {
                    $html .= '<div class="children"><p>' . $this->printName($person) . ' ';
                    // needs multiple translations for the word 'had' to serve different languages.
                    if ($spouse && $spouse->canShow()) {
                        $html .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse) . ' ';
                        if (count($children) > 1) {
                            if ($person->isDead() || $spouse->isDead()) {
                                $html .= I18N::translateContext('Two parents/multiple children', 'had');
                            } else {
                                $html .= I18N::translateContext('Two parents/multiple children', 'have');
                            }
                        } else {
                            if ($person->isDead() || $spouse->isDead()) {
                                $html .= I18N::translateContext('Two parents/one child', 'had');
                            } else {
                                $html .= I18N::translateContext('Two parents/one child', 'have');
                            }
                        }
                    } else {
                        if (count($children) > 1) {
                            if ($person->isDead()) {
                                $html .= I18N::translateContext('One parent/multiple children', 'had');
                            } else {
                                $html .= I18N::translateContext('One parent/multiple children', 'has');
                            }
                        } else {
                            if ($person->isDead()) {
                                $html .= I18N::translateContext('One parent/one child', 'had');
                            } else {
                                $html .= I18N::translateContext('One parent/one child', 'has');
                            }
                        }
                    }
                    $html .= ' ' . /* I18N: %s is a number */ I18N::plural('%s child', '%s children', count($children), count($children)) . '.</p></div>';
                } else {
                    $html .= '<div class="jc-children-block mb-3"><p class="mb-1">' . I18N::translate('Children of ') . $this->printName($person);
                    if ($spouse && $spouse->canShow()) {
                        $html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse);
                    }
                    $html .= ':<ol>';

                    foreach ($children as $child) {
                        if ($child->canShow()) {
                            $html .= '<li class="jc-child-li">' . $this->printNameUrl($child);
                            $pedi = $this->checkPedi($child, $family);

                            if ($pedi) {
                                $html .= ' <span class="pedi fst-italic">';
                                switch ($pedi) {
                                    case 'FOSTER':
                                        switch ($child->sex()) {
                                            case 'F':
                                                $html     .= I18N::translateContext('FEMALE', 'foster child');
                                                break;
                                            default:
                                                $html     .= I18N::translateContext('MALE', 'foster child');
                                                break;
                                        }
                                        break;
                                    case 'ADOPTED':
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
                            $this->index++;
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
                    if ($pedi === 'FOSTER') {
                        $html .= ', ' . I18N::translate('foster son of') . ' ';
                    } elseif ($pedi === 'ADOPTED') {
                        $html .= ', ' . I18N::translate('adopted son of') . ' ';
                    } else {
                        $html .= ', ' . I18N::translate('son of') . ' ';
                    }
                    break;
                case 'F':
                    if ($pedi === 'FOSTER') {
                        $html .= ', ' . I18N::translate('foster daughter of') . ' ';
                    } elseif ($pedi === 'ADOPTED') {
                        $html .= ', ' . I18N::translate('adopted daughter of') . ' ';
                    } else {
                        $html .= ', ' . I18N::translate('daughter of') . ' ';
                    }
                    break;
                default:
                    if ($pedi === 'FOSTER') {
                        $html .= ', ' . I18N::translate('foster child of') . ' ';
                    } elseif ($pedi === 'ADOPTED') {
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

        if ($this->options('show-occupations')) {
            $occupations = $person->facts(['OCCU'], true);
            $count       = count($occupations);
            foreach ($occupations as $key => $fact) {
                if ($key > 0 && $key === $count - 1) {
                    $html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
                } else {
                    $html .= ', ';
                }

                $html .= rtrim($this->options('gedcom-occupation') ? $fact->value() : lcfirst($fact->value()), ".");

                if ($this->options('show-agencies') && $fact->attribute('AGNC') !== '') {
                    $fact->value() === '' ? $html .= I18N::translate('employed with') : $html .= ' ' . /* I18N: in the context 'employed with' */ I18N::translate('with');
                    $html .= ' ' . $fact->attribute('AGNC');
                }

                $date = $this->printDate($fact);
                if ($date) {
                    $html .= ' (' . trim($date) . ')';
                }
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
                        $person->sex() == 'F' ? $html     .= I18N::translateContext('PRESENT (FEMALE)', 'was baptized') : $html     .= I18N::translateContext('PRESENT (MALE)', 'was baptized');
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
        $html      = '';
        if ($bdate->isOK() && $ddate->isOK()) {
            $ageAtDeath = new Age($bdate, $ddate);

            // Add the text 'approximately' to calculated ages from a date qualifier.
            $html .= !$this->isDateDMY($bfact) || !$this->isDateDMY($dfact) ? ' ' . I18N::translate('approximately') : '';

            $days   = $ageAtDeath->ageDays();
            $months = (int)($ageAtDeath->ageDays()/30);
            $years  = $ageAtDeath->ageYears();

            // We need to separate the singular form (1) for a correct German translation. The default webtrees form is not applicable here.
            // Put the number 1 in the translatable part of the string to give translators the choice to use text in stead of a number.
            if ($days === 1 || $months === 1 || $years === 1) {
                $html .= ' ' . /* I18N: %s is a string without a number e.g. day/month/year */ I18N::translate('at the age of 1 %s', substr($ageAtDeath->__toString(), 2));
            } else {
                $html .= ' ' . /* I18N: %s a string with a number e.g. 10 days/months/years */ I18N::translate('at the age of %s', $ageAtDeath);
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
            if ($this->options('places-format') === 'none') {
                return null;
            }

            $place = new Place($this->formatPlaceNames($place), $this->tree);

            if ($this->options('places-format') === 'webtrees') {
                $place = $place->shortName();
            } else {
                $place = $place->fullName();
            }

            $html = $place ? ' ' . I18N::translate('in %s', $place) : '';

            return $html;
        }

        return null;
    }

    /**
     * @param Fact $fact
     *
     * @return string
     */
    protected function printNote(Fact $fact): string
    {
        if ($fact instanceof Fact) {
            // Link to note object
            $note = $fact->target();
            if ($note instanceof Note) {
                return $note->getNote();
            }

            // Inline note
            return $fact->value();
        }

        return '';
    }

    /**
     * @param Individual $child
     *
     * @return string
     */
    protected function printFollowLink(Individual $child): string
    {
        $request = Registry::container()->get(ServerRequestInterface::class);
        assert($request instanceof ServerRequestInterface);

        $xref  = Validator::attributes($request)->string('xref');

        $text = I18N::translate('follow') . ' ' . ($this->generation + 1) . '.' . $this->index;

        if ($this->isPage()) {
            $page  = Validator::attributes($request)->integer('page');

            $limit = $this->options('page-limit');

            if ($this->generation === $page * $limit) {
                $page = $page + 1;
            }
            $url  = $this->getUrl($this->tree, $xref, $this->type, $page);
        } else {
            $limit = $this->options('tab-limit');
            if ($this->generation === (int) $limit) {
                $page = (int) ceil(($limit + 1) / $this->options('page-limit'));
                $url  = $this->getUrl($this->tree, $xref, $this->type, $page);
            } else {
                $url = $this->getPerson($xref)->url();
            }
        }

        $child_family = $this->getFamily($child);
        if ($this->options('show-singles') === '1' || $child_family) {
            $this->index++;
            return ' - <a class="jc-scroll" href="' . $url . '#' . $child->xref() . '">' . $text . '</a>';
        } else {
            return '';
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
     * Countrylist used on the Fancy Treeview configuration page
     *
     * @return array
     */
    private function getCountryList(): array
    {
        $countries = $this->country_service->getAllCountries();
        $countries['???'] = '';
        asort($countries);

        return $countries;
    }

     /**
     * Format the placenames as configured.
     *
     * @param string $place
     *
     * @return string
     */
    private function formatPlaceNames(string $place): string
    {
        $parts = collect(explode(', ', $place));

        if($parts->count() === 1) return $parts->first();

        $country = $parts->last();

        $iso3 = array_search ($country, $this->getCountryList()) ?: $country;
        $iso2 = $this->country_service->iso3166()[$iso3] ?? $iso3;

        if ($this->options('countries-format') === 'iso2') {
            $parts = $parts->slice(0, -1)->push($iso2);
        }

        if ($this->options('countries-format') === 'iso3') {
            $parts = $parts->slice(0, -1)->push($iso3);
        }

        if ($this->options('home-country') === $iso3) {
            if ($this->options('show-home-country') === '0') {
                $parts = $parts->slice(0, -1);
            }

            if ($this->options('places-format-hc') === 'highlow') {
                $parts = collect([$parts->first(), $parts->last()]);
            }

            if ($this->options('places-format-hc') === 'low') {
                $parts = collect([$parts->first()]);
            }
        } else {
            if ($this->options('places-format-oc') === 'highlow') {
                $parts = collect([$parts->first(), $parts->last()]);
            }
        }

        return $parts->implode(', ');
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
     * Check if this relationship is the current relationship
     * It should be the latest relationship of both living partners
     */
    /**
     * @param mixed $person
     * @param mixed $spouse
     *
     * @return bool
     */
    private function isCurrentRelation ($person, $spouse): bool
    {
        // first check if both persons are alive
        if ($person->isDead() || $spouse->isDead()) {
            return false;
        }

        // determine if the current spouse is also the latest partner of the person
        $partnercount = 0;
        foreach ($spouse->spouseFamilies(Auth::PRIV_HIDE) as $family) {
            $partner = $family->spouse($spouse);
            if ($partner && $partner->canShow()) {
                $partnercount++;
            }
        }

        $partnerindex = 0;
        foreach ($spouse->spouseFamilies(Auth::PRIV_HIDE) as $family) {
            $partner = $family->spouse($spouse);
            if ($partner && $partner->canShow()) {
                if ($partnerindex === $partnercount - 1 && $person === $partner) {
                    return true;
                }
            }
            $partnerindex++;
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

        $language = Registry::container()->get(ModuleService::class)
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
     * Check if there is a relationship between partners through there joint ancestors, if any.
     * If found, add the generation number where a pedigree collapse first occurs to the collection
     *
     * See: app\Module\RelationshipsChartModule.php
     *
     * @param Individual $person
     * @param Individual $spouse
     *
     * @return void
     */
    private function setPedigreeCollapse(int $generation, Individual $person, Individual $spouse): void
    {
        $tree = $person->tree();
        $paths = $this->calculateRelationships($person, $spouse);

        foreach ($paths as $path) {
            $nodes = Collection::make($path)
                ->map(static function (string $xref, int $key) use ($tree): GedcomRecord {
                    if ($key % 2 === 0) {
                        return Registry::individualFactory()->make($xref, $tree);
                    }
                    return  Registry::familyFactory()->make($xref, $tree);
                });

            $pattern = function ($nodes) {
                return Registry::container()->get(RelationshipService::class)->components($nodes);
            };

            $pattern = $pattern->call(Registry::container()->get(RelationshipService::class), $nodes->toArray());

            if ($pattern) {
                $occurences = array_count_values($pattern);

                $fat = array_key_exists('fat', $occurences) ? $occurences['fat'] : 0;
                $mot = array_key_exists('mot', $occurences) ? $occurences['mot'] : 0;

                $generations_up = $generation + $fat + $mot;
                $this->pedigree_collapse[] = $generations_up;
            }
        }
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
            return Registry::container()->get(RelationshipsChartModule::class)->calculateRelationships($individual1, $individual2, 0, true);
        };

        return $calculateRelationships->call(Registry::container()->get(RelationshipsChartModule::class), $individual1, $individual2);
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
            return Registry::container()->get(RelationshipsChartModule::class)->oldStyleRelationshipPath($tree, $path);
        };

        return $oldStyleRelationshipPath->call(Registry::container()->get(RelationshipsChartModule::class), $tree, $path);
    }

    /**
     * Check if this is a private record
     * $records can be an array of xrefs or an array of objects
     *
     * @param mixed $records
     * @param bool $xrefs
     *
     * @return bool
     */
    public function checkPrivacy($records, bool $xrefs = false): bool
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
            if ($fact instanceof Fact && $fact->target() === $parents) {
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
        $request = Registry::container()->get(ServerRequestInterface::class);
        assert($request instanceof ServerRequestInterface);

        $route = Validator::attributes($request)->route();

        if ($route->name === static::class) {
            return true;
        }

        return false;
    }

    /**
     * @return int
     */
    private function getPage(): int
    {
        if ($this->isPage()) {
            $request = Registry::container()->get(ServerRequestInterface::class);
            assert($request instanceof ServerRequestInterface);

            $page  = Validator::attributes($request)->integer('page');
        } else {
            $page = 1;
        }
        return $page;
    }

    public function isMenuItem(Tree $tree, string $xref, string $type): bool
    {
        if ($type === 'ancestors') {
            $items = explode(', ', $this->getPreference($tree->id() . '-menu-ancestors', ''));
        } else {
            $items = explode(', ', $this->getPreference($tree->id() . '-menu-descendants', ''));
        }

        return in_array($xref, $items) ? true : false;
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
