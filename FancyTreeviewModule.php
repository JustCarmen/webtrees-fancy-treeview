<?php

declare(strict_types=1);

namespace JustCarmen\Webtrees\Module\FancyTreeview;

use Fisharebest\Webtrees\Age;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Fact;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Menu;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\View;
use Fisharebest\Webtrees\Media;
use Aura\Router\RouterContainer;
use Fisharebest\Webtrees\Family;
use Fisharebest\Webtrees\Gedcom;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\FlashMessages;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Services\ChartService;
use Fisharebest\Webtrees\Module\ModuleMenuTrait;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleMenuInterface;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Http\RequestHandlers\ModulesMenusAction;

Class FancyTreeviewModule extends AbstractModule implements ModuleCustomInterface, ModuleMenuInterface, ModuleConfigInterface, RequestHandlerInterface
{
    use ModuleCustomTrait;
    use ModuleMenuTrait;
    use ModuleConfigTrait;

    protected const ROUTE_URL = '/tree/{tree}/{module}/{menu}/{page}/{pid}/{generations}';

     /**
     * @var string
     */
    public const CUSTOM_AUTHOR = 'JustCarmen';

    /**
     * @var string
     */
    public const CUSTOM_VERSION = '2.0-dev';
     /**
     * @var string
     */
    public const GITHUB_REPO = 'webtrees-fancy-treeview';

     /**
     * @var string
     */
    public const AUTHOR_WEBSITE = 'https://justcarmen.nl';

     /**
     * @var string
     */
    public const CUSTOM_SUPPORT_URL = self::AUTHOR_WEBSITE . '/modules-webtrees-2/fancy-treeview/';

    // Limits
    protected const MINIMUM_GENERATIONS = 2;
    protected const MAXIMUM_GENERATIONS = 10;

	/**
	* @var string
	*/
   private const CACHE_DIR = Webtrees::DATA_DIR . 'ftv-cache/';


    /** var array of xrefs (individual id's) */
	public $pids;

	/** var integer generation number */
	public $generation;

	/** var integer used for follow index */
	public $index;

     /** @var ChartService */
     private $chart_service;

     private Tree $tree;

     /**
      * FancyTreeviewModule constructor.
      *
      * @param ChartService $chart_service
      */
     public function __construct(ChartService $chart_service)
     {
         $this->chart_service = $chart_service;
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
        /* I18N: Description of the “Simple Menu” module */
        return I18N::translate('Easily add an extra mainmenu item and page to your webtrees website.');
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
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'title' => $this->title(),
            'menu_title' => $this->getPreference('menu-title'),
            'page_title' => $this->getPreference('page-title'),
            'page_body'  => $this->getPreference('page-body'),
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

        $this->setPreference('menu-title', $params['menu-title']);
        $this->setPreference('page-title', $params['page-title']);
        $this->setPreference('page-body', $params['page-body']);

        $message = I18N::translate('The preferences for the module “%s” have been updated.', $this->title());
        FlashMessages::addMessage($message, 'success');

        return redirect(route(ModulesMenusAction::class));
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
     * TODO: MAKE THIS A MULTILEVEL MENU
     * TODO: MAKE THE XREF DYNAMIC
     *
     * A menu, to be added to the main application menu.     *
     *
     * @param Tree $tree
     *
     * @return Menu|null
     */
    public function getMenu(Tree $tree): ?Menu
    {
        if ($tree === null) {
            return '';
        }

        $page_title = $this->getPreference('page-title');
        $menu_title = $this->getPreference('menu-title');

        $pid = 'I8';

        $url = route(static::class, [
            'tree' => $tree->name(),
            'module' => str_replace("_", "", $this->name()),
            'menu' => $this->getslug($menu_title),
            'page' => $this->getslug($page_title),
            'pid' => $pid,
            'generations' => '10'
        ]);

        return new Menu($menu_title, e($url), 'jc-fancy-treeview-' . e(strtolower($menu_title)));
    }

     /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->tree = $request->getAttribute('tree');
        assert($this->tree instanceof Tree);

        $pid = $request->getAttribute('pid');
        assert(is_string($pid));

        $generations = Validator::attributes($request)->isBetween(self::MINIMUM_GENERATIONS, self::MAXIMUM_GENERATIONS)->integer('generations');

        $page_title = $this->getPreference('page-title');
        $page_body  = $this->printPage($this->tree, $pid, $generations);

        return $this->viewResponse($this->name() . '::page', [
            'tree'          => $this->tree,
            'title'         => $this->title(),
            'module'        => $this->name(),
            'is_admin'      => Auth::isAdmin(),
            'page_title'    => $page_title,
            'page_body'     => $page_body
        ]);
    }

	public function options(string $option) : string
	{
		$default = match ($option) {
			'use-fullname' 			=> '0',
			'numblocks' 			=> '0',
			'check-relationship' 	=> '0',
			'show-singles'			=> '0',
			'show-places' 			=> '1',
			'use-gedcom-places' 	=> '0',
			'country'				=> '',
			'show-occu' 			=> '1',
			'resize-thumbs'			=> '1',
			'thumb-size' 			=> '60',
			'thumb-resize-format'	=> '2',
			'use-square-thumbs'		=> '1',
			'show-userform'			=> '2',
			'ftv-tab' 				=> '1'
		};

		return $this->getPreference($option, $default);
	}

    /**
	 * Print the Fancy Treeview page
	 *
	 * @return html
	 */
	public function printPage(Tree $tree, string $pid, int $generations) {
		$html				 = '';
		$this->generation	 = 1;
		$root				 = $pid; // save value for read more link
		$this->pids			 = [$pid];

		$html .= $this->printGeneration();

		while (count($this->pids) > 0 && $this->generation < $generations) {
			$pids = $this->pids;
			unset($this->pids); // empty the array (will be filled with the next generation)

			foreach ($pids as $pid) {
				$next_gen[] = $this->getNextGen($pid);
			}

			foreach ($next_gen as $descendants) {
				if (count($descendants) > 0) {
					foreach ($descendants as $descendant) {
						if ($this->options('show-singles') == true || $descendant['desc'] == 1) {
							$this->pids[] = $descendant['pid'];
						}
					}
				}
			}

			if (!empty($this->pids)) {
				if ($this->generation === 3) {
					$html .= $this->printReadMoreLink($root);
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
	 * Print a generation
	 *
	 * @param type $i
	 * @return string
	 */
	protected function printGeneration() {
		// reset the index
		$this->index = 1;

		// added data attributes to retrieve values easily with jquery (for scroll reference en next generations).
		$html = '<li class="block generation-block" data-gen="' . $this->generation . '" data-pids="' . implode('|', $this->pids) . '">' .
			$this->printBlockHeader();

		if ($this->checkPrivacy($this->pids, true)) {
			$html .= $this->printPrivateBlock();
		} else {
			$html .= $this->printBlockContent();
		}

		$html .= '</li>';

		return $html;
	}

	/**
	 * Print the header of each generation block
	 *
	 * @param type $i
	 * @return string
	 */
	protected function printBlockHeader() {
		return
			'<div class="blockheader ui-state-default">' .
			'<span class="header-title">' . I18N::translate('Generation') . ' ' . $this->generation . '</span>' .
			$this->printBackToTopLink() .
			'</div>';
	}

	/**
	 *
	 * @return string
	 */
	protected function printBlockContent() {
		$html = '<ol class="blockcontent generation">';
		foreach (array_unique($this->pids) as $pid) {
			$person = $this->getPerson($pid);
			if (!$this->hasParentsInSameGeneration($person)) {
				$family = $this->getFamily($person);
				if (!empty($family)) {
					$pid = $family->xref();
				}
				$class = $person->canShow() ? 'family' : 'family private';

				$html .= '<li id="' . $pid . '" class="' . $class . '">' . $this->printIndividual($person) . '</li>';
			}
		}
		$html .= '</ol>';
		return $html;
	}

	/**
	 * Print back-to-top link
	 *
	 * @param type $i
	 * @return string
	 */
	protected function printBackToTopLink() {
		if ($this->generation > 1) {
			return '<a href="#fancy_treeview-page" class="header-link scroll">' . I18N::translate('back to top') . '</a>';
		}
	}

	/**
	 * Print read-more link
	 *
	 * @param type $root
	 * @return string
	 */
	protected function printReadMoreLink($root) {
		return
			'<div id="read-more-link">' .
			'<a href="module.php?mod=' . $this->name() . '&amp;mod_action=page&rootid=' . $root . '&amp;ged=' . $this->tree->name() . '">' .
			I18N::translate('Read more') .
			'</a>' .
			'</div>';
	}

	/**
	 * Print private block content
	 *
	 * @return string
	 */
	protected function printPrivateBlock() {
		return
			'<div class="blockcontent generation private">' .
			I18N::translate('The details of this generation are private.') .
			'</div>';
	}

	/**
	 * Print the content for one individual
	 *
	 * @return string (html)
	 */
	protected function printIndividual(Individual $person) {

		if ($person->canShow()) {
			$html = '<div class="parents">' . $this->printThumbnail($person) . '<p class="desc">' . $this->printNameUrl($person, $person->xref());
			if ($this->options('show-occu')) {
				$html .= $this->printOccupations($person);
			}

			$html .= $this->printParents($person) . $this->printLifespan($person) . '.';

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

			$html .= '</p></div>';

			// get children for each couple (could be none or just one, $spouse could be empty, includes children of non-married couples)
			foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $family) {
				$spouse = $family->spouse($person);

				$html .= $this->printChildren($family, $person, $spouse);
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
	 * @return string
	 */
	protected function printSpouse(Family $family, Individual $person, Individual $spouse, int $i, int $count) {

		$html = ' ';

		if ($count > 1) {
			// we assume no one married more then ten times.
			$wordcount = [
				/* I18N: first marriage  */ I18N::translate('first'),
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
					$html	 .= I18N::translate('He married');
					break;
				case 'F':
					$html	 .= I18N::translate('She married');
					break;
				default:
					$html	 .= I18N::translate('This individual married');
					break;
			}
		}

		$html	 .= ' ' . $this->printNameUrl($spouse);
		// $html	 .= $this->printRelationship($person, $spouse);
		$html	 .= $this->printParents($spouse);

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
		return $html;
	}

	/**
	 * Print the content for a non-married partner
	 * @return string
	 */
	protected function printPartner(Family $family, Individual $person, Individual $spouse) {

		$html = ' ';

		switch ($person->sex()) {
			case 'M':
				$html	 .= I18N::translate('He had a relationship with');
				break;
			case 'F':
				$html	 .= I18N::translate('She had a relationship with');
				break;
			default:
				$html	 .= I18N::translate('This individual had a relationship with');
				break;
		}

		$html	 .= ' ' . $this->printNameUrl($spouse);
		// $html	 .= $this->printRelationship($person, $spouse);
		$html	 .= $this->printParents($spouse);

		if ($family->facts(['_NMR'])->first() && $this->printLifespan($spouse, true)) {
			$html .= $this->printLifespan($spouse, true);
		}

		return '. ' . $html;
	}

	/**
	 * Print the childrens list
	 *
	 * @return string
	 */
	protected function printChildren(Family $family, Individual $person, Individual $spouse) {
		$html = '';

		$match = null;
		if (preg_match('/\n1 NCHI (\d+)/', $family->gedcom(), $match) && $match[1] == 0) {
			$html .= '<div class="children"><p>' . $this->printName($person) . ' ';
			if ($spouse && $spouse->canShow()) {
				$html	 .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse) . ' ';
				$html	 .= I18N::translateContext('Two parents/one child', 'had');
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
					$html .= '<div class="children"><p>' . I18N::translate('Children of ') . $this->printName($person);
					if ($spouse && $spouse->canShow()) {
						$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse);
					}
					$html .= ':<ol>';

					foreach ($children as $child) {
						if ($child->canShow()) {
							$html	 .= '<li class="child">' . $this->printNameUrl($child);
							$pedi	 = $this->checkPedi($child, $family);

							if ($pedi) {
								$html .= ' <span class="pedi">';
								switch ($pedi) {
									case 'foster':
										switch ($child->sex()) {
											case 'F':
												$html	 .= I18N::translateContext('FEMALE', 'foster child');
												break;
											default:
												$html	 .= I18N::translateContext('MALE', 'foster child');
												break;
										}
										break;
									case 'adopted':
										switch ($child->sex()) {
											case 'F':
												$html	 .= I18N::translateContext('FEMALE', 'adopted child');
												break;
											default:
												$html	 .= I18N::translateContext('MALE', 'adopted child');
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
									$html .= ' - <a class="scroll" href="#' . $child_family->xref() . '">' . $text_follow . '</a>';
									$this->index++;
								} elseif ($this->options('show_singles') == true) {
									$html .= ' - <a class="scroll" href="#' . $child->xref() . '">' . $text_follow . '</a>';
									$this->index++;
								}
							// }
							$html .= '</li>';
						} else {
							$html .= '<li class="child private">' . I18N::translate('Private') . '</li>';
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
	 * @param type $person
	 * @return string
	 */
	protected function printParents(Individual $person) {
		$parents = $person->getPrimaryChildFamily();
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

			$father	 = $parents->husband();
			$mother	 = $parents->wife();

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
	}

	/**
	 * Print the full name of a person
	 *
	 * @return string
	 */
	protected function printName(Individual $person) {
		return $person->fullname();
	}

	/**
	 * Print the name of a person with the link to the individual page
	 *
	 * @return string
	 */
	protected function printNameUrl(Individual $person, $xref = '') {
		return '<a href="' . $person->url() . '">' . $person->fullname() . '</a>';
	}

	/**
	 * Print occupations
	 *
	 * @return string
	 */
	protected function printOccupations(Individual $person) {
		$html		 = '';
		$occupations = $person->facts(['OCCU'], true);
		$count		 = count($occupations);
		foreach ($occupations as $num => $fact) {
			if ($num > 0 && $num === $count - 1) {
				$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
			} else {
				$html .= ', ';
			}

			// In the Gedcom file most occupations are probably written with a capital (as a single word)
			// but use lcase/ucase to be sure the occupation is spelled the right way since we are using
			// it in the middle of a sentence.
			// In German all occupations are written with a capital.
			// Are there any other languages where this is the case?
			foreach (I18N::activeLocales() as $locale) {
				if ($locale->languageTag() === 'de') {
					$html .= rtrim(ucfirst($fact->value()), ".");
				} else {
					$html .= rtrim(lcfirst($fact->value()), ".");
				}
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
	 * @return string
	 */
	protected function printLifespan(Individual $person, bool $is_spouse = false) {
		$html = '';

		$is_bfact = false;
		foreach (Gedcom::BIRTH_EVENTS as $event) {
			$bfact = $person->facts($event)->first();
			if ($bfact) {
				$bdate	 = $this->printDate($bfact);
				$bplace	 = $this->printPlace($bfact);

				if ($bdate || $bplace) {
					$is_bfact	 = true;
					$html		 .= $this->printBirthText($person, $event, $is_spouse) . $bdate . $bplace;
					break;
				}
			}
		}

		$is_dfact = false;
		foreach (Gedcom::DEATH_EVENTS as $event) {
			$dfact = $person->facts($event)->first();
			if ($dfact) {
				$ddate	 = $this->printDate($dfact);
				$dplace	 = $this->printPlace($dfact);

				if ($ddate || $dplace) {
					$is_dfact	 = true;
					$html		 .= $this->printDeathText($person, $event, $is_bfact) . $ddate . $dplace;
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
	 */
	/* protected function printRelationship($person, $spouse) {
		$html = '';
		if ($this->options('check_relationship')) {
			$relationship = $this->checkRelationship($person, $spouse);
			if ($relationship) {
				$html .= ' (' . $relationship . ')';
			}
		}
		return $html;
	} */

	/**
	 * Print the Fancy thumbnail for this individual
	 *
	 * @param type $person
	 * @return thumbnail
	 */
	protected function printThumbnail(Individual $person) {
		$mediaobject = $person->findHighlightedMediaFile();
		if ($mediaobject) {
			$cache_filename = $this->getThumbnail($mediaobject);
			if (is_file($cache_filename)) {
				$imgsize = getimagesize($cache_filename);
				$image	 = '<img' .
					' dir="' . 'auto' . '"' . // For the tool-tip
					' src="module.php?mod=' . $this->name() . '&amp;mod_action=thumbnail&amp;mid=' . $mediaobject->xref() . '&amp;thumb=2&amp;cb=' . $mediaobject->getEtag() . '"' .
					' alt="' . strip_tags($person->getFullName()) . '"' .
					' title="' . strip_tags($person->getFullName()) . '"' .
					' data-cachefilename="' . basename($cache_filename) . '"' .
					' ' . $imgsize[3] . // height="yyy" width="xxx"
					'>';
				return
					'<a' .
					' class="' . 'gallery' . '"' .
					' href="' . $mediaobject->getHtmlUrlDirect() . '"' .
					' type="' . $mediaobject->mimeType() . '"' .
					' data-obje-url="' . $mediaobject->getHtmlUrl() . '"' .
					' data-obje-note="' . e($mediaobject->getNote()) . '"' .
					' data-title="' . strip_tags($person->getFullName()) . '"' .
					'>' . $image . '</a>';
			} else {
				return $mediaobject->displayImage();
			}
		}
	}

	/**
	 * Print the birth text (born or baptized)
	 *
	 * @return string
	 */
	protected function printBirthText(Individual $person, $event, bool $is_spouse = false) {
		$html = '';
		switch ($event) {
			case 'BIRT':
				if ($is_spouse == true) {
					$html .= '. ';
					if ($person->isDead()) {
						$person->sex() == 'F' ? $html	 .= I18N::translateContext('PAST', 'She was born') : $html	 .= I18N::translateContext('PAST', 'He was born');
					} else {
						$person->sex() == 'F' ? $html	 .= I18N::translateContext('PRESENT', 'She was born') : $html	 .= I18N::translateContext('PRESENT', 'He was born');
					}
				} else {
					$this->printParents($person) || $this->printOccupations($person) ? $html	 .= ', ' : $html	 .= ' ';
					if ($person->isDead()) {
						$person->sex() == 'F' ? $html	 .= I18N::translateContext('PAST (FEMALE)', 'was born') : $html	 .= I18N::translateContext('PAST (MALE)', 'was born');
					} else {
						$person->sex() == 'F' ? $html	 .= I18N::translateContext('PRESENT (FEMALE)', 'was born') : $html	 .= I18N::translateContext('PRESENT (MALE)', 'was born');
					}
				}
				break;
			case 'BAPM':
			case 'CHR':
				if ($is_spouse == true) {
					$html .= '. ';
					if ($person->isDead()) {
						$person->sex() == 'F' ? $html	 .= I18N::translateContext('PAST', 'She was baptized') : $html	 .= I18N::translateContext('PAST', 'He was baptized');
					} else {
						$person->sex() == 'F' ? $html	 .= I18N::translateContext('PRESENT', 'She was baptized') : $html	 .= I18N::translateContext('PRESENT', 'He was baptized');
					}
				} else {
					$this->printParents($person) || $this->printOccupations($person) ? $html	 .= ', ' : $html	 .= ' ';
					if ($person->isDead()) {
						$person->sex() == 'F' ? $html	 .= I18N::translateContext('PAST (FEMALE)', 'was baptized') : $html	 .= I18N::translateContext('PAST (MALE)', 'was baptized');
					} else {
						$person->sex() == 'F' ? $html	 .= I18N::translateContext('PRESENT (FEMALE)', 'was baptized') : $html	 .= I18N::translateContext('PRESENT (MALE)', 'was bapitized');
					}
				}
				break;
		}
		return $html;
	}

	/**
	 * Print the death text (death or buried)
	 *
	 * @return string
	 */
	protected function printDeathText(Individual $person, $event, bool $is_bfact) {
		$html = '';
		switch ($event) {
			case 'DEAT':
				if ($is_bfact) {
					$html	 .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
					$person->sex() == 'F' ? $html	 .= I18N::translateContext('FEMALE', 'died') : $html	 .= I18N::translateContext('MALE', 'died');
				} else {
					$person->sex() == 'F' ? $html	 .= '. ' . I18N::translate('She died') : $html	 .= '. ' . I18N::translate('He died');
				}
				break;
			case 'BURI':
				if ($is_bfact) {
					$html	 .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
					$person->sex() == 'F' ? $html	 .= I18N::translateContext('FEMALE', 'was buried') : $html	 .= I18N::translateContext('MALE', 'was buried');
				} else {
					$person->sex() == 'F' ? $html	 .= '. ' . I18N::translate('She was buried') : $html	 .= '. ' . I18N::translate('He was buried');
				}
				break;
			case 'CREM':
				if ($is_bfact) {
					$html	 .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
					$person->sex() == 'F' ? $html	 .= I18N::translateContext('FEMALE', 'was cremated') : $html	 .= I18N::translateContext('MALE', 'was cremated');
				} else {
					$person->sex() == 'F' ? $html	 .= '. ' . I18N::translate('She was cremated') : $html	 .= '. ' . I18N::translate('He was cremated');
				}
				break;
		}
		return $html;
	}

	/**
	 * Print the age at death/bury
	 * @param type $bfact
	 * @param type $dfact
	 * @return string
	 */
	protected function printAgeAtDeath($bfact, $dfact) {
		$bdate	 = $bfact->getDate();
		$ddate	 = $dfact->getDate();
		$html	 = '';
		if ($bdate->isOK() && $ddate->isOK() && $this->isDateDMY($bfact) && $this->isDateDMY($dfact)) {
			$ageAtdeath = (int) new Age($bdate, $ddate);
			if ($ageAtdeath < 2) {
				$html .= ' ' . /* I18N: %s is the age of death in days/months; %s is a string, e.g. at the age of 2 months */ I18N::translateContext('age in days/months', 'at the age of %s', $ageAtdeath);
			} else {
				$html .= ' ' . /* I18N: %s is the age of death in years; %s is a number, e.g. at the age of 40. If necessary add the term 'years' (always plural) to the string */ I18N::translateContext('age in years', 'at the age of %s', filter_var($ageAtDeath, FILTER_SANITIZE_NUMBER_INT));
			}
		}
		return $html;
	}

	/**
	 * Function to print dates with the right syntax
	 * @return string
	 */
	protected function printDate(Fact $fact) {
		$date = $fact->date();
		if ($date && $date->isOK()) {
			if (preg_match('/^(FROM|BET|TO|AND|BEF|AFT|CAL|EST|INT|ABT) (.+)/', $fact->attribute('DATE'))) {
				return ' ' . /* I18N: Date prefix for date qualifications, like estimated, about, calculated, from, between etc. Leave the string empty if your language don't need such a prefix. If you do need this prefix, add an extra space at the end of the string to separate the prefix from the date. It is correct the source text is empty, because the source language (en-US) does not need this string. */ I18N::translateContext('prefix before dates with date qualifications, followed right after the words birth, death, married, divorced etc. Read the comment for more details.', ' ') . $date->Display();
			}
			if ($date->minimumDate()->d > 0) {
				return ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before dateformat dd-mm-yyyy', 'on ') . $date->Display();
			}
			if ($date->minimumDate()->m > 0) {
				return ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before dateformat mmm yyyy', 'in ') . $date->Display();
			}
			if ($date->minimumDate()->y > 0) {
				return ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before dateformat yyyy', 'in ') . $date->Display();
			}
		}
	}

	/**
	 * Print places
	 *
	 * @param Place $place
	 * @param type $tree
	 * @return string
	 */
	protected function printPlace($fact) {
		global $WT_TREE;
		$place = $fact->getAttribute('PLAC');
		if ($place && $this->options('show_places') == true) {
			$place	 = new Place($place, $WT_TREE);
			$html	 = ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before placesnames', 'in ');
			if ($this->options('use_gedcom_places') == true) {
				$html .= $place->getShortName();
			} else {
				$country	 = $this->options('country');
				$new_place	 = array_reverse(explode(", ", $place->getGedcomName()));
				if (!empty($country) && $new_place[0] == $country) {
					unset($new_place[0]);
					$html .= '<span dir="auto">' .e(implode(', ', array_reverse($new_place))) . '</span>';
				} else {
					$html .= $place->getFullName();
				}
			}
			return $html;
		}
	}

	/**
	 * Get individual object from PID
	 *
	 * @param string $pid
	 * @return object
	 */
	protected function getPerson(string $pid) {
		return Registry::individualFactory()->make($pid, $this->tree);
	}

	/**
	 * Get object of the rootperson of this tree
	 *
	 * @return object
	 */
	protected function getRootPerson() {
		return $this->getPerson($this->rootId());
	}

	/**
	 * Get the family object of an individual
	 *
	 * @param type $person
	 * @return object
	 */
	private function getFamily(Individual $person) {
		foreach ($person->spouseFamilies(Auth::PRIV_HIDE) as $family) {
			return $family;
		}
	}

	/**
	 * Get an array of xrefs for the next descendant generation of this person
	 *
	 * @param type $pid
	 * @return array of xrefs
	 */
	private function getNextGen($pid) {
		$person	 = $this->getPerson($pid);
		$ng		 = [];
		foreach ($person->spouseFamilies() as $family) {
			$children = $family->children();
			if ($children) {
				foreach ($children as $key => $child) {
					$key				 = $family->xref() . '-' . $key; // be sure the key is unique.
					$ng[$key]['pid']	 = $child->xref();
					$child->spouseFamilies(Auth::PRIV_HIDE) ? $ng[$key]['desc']	 = 1 : $ng[$key]['desc']	 = 0;
				}
			}
		}
		return $ng;
	}

	/**
	 * check if a person has parents in the same generation
	 * this function prevents listing the same person twice
	 *
	 * @param type $person
	 * @return boolean
	 */
	private function hasParentsInSameGeneration(Individual $person) {
		$parents = $person->childFamilies()->first();;
		if ($parents) {
			$father	 = $parents->husband();
			$mother	 = $parents->wife();
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
	}

	/**
	 * check if this date has any date qualifiers. Return true if no date qualifiers are found.
	 *
	 * @param type $fact
	 * @return boolean
	 */
	private function isDateDMY($fact) {
		if ($fact && !preg_match('/^(FROM|BET|TO|AND|BEF|AFT|CAL|EST|INT|ABT) (.+)/', $fact->getAttribute('DATE'))) {
			return true;
		}
	}

	/**
	 * check (blood) relationship between partners	 *
	 */
	/* private function checkRelationship($person, $spouse) {
		$controller	 = new RelationshipController();
		$paths		 = $controller->calculateRelationships($person, $spouse, 1);
		foreach ($paths as $path) {
			$relationships = $controller->oldStyleRelationshipPath($path);
			if (empty($relationships)) {
				// Cannot see one of the families/individuals, due to privacy;
				continue;
			}
			foreach (array_keys($path) as $n) {
				if ($n % 2 === 1) {
					switch ($relationships[$n]) {
						case 'sis':
						case 'bro':
						case 'sib':
							return Functions::getRelationshipNameFromPath(implode('', $relationships), $person, $spouse);
					}
				}
			}
		}
	} */

	/**
	 * Check if this is a private record
	 * $records can be an array of xrefs or an array of objects
	 *
	 * @param type $record
	 * @param type $xrefs
	 * @return boolean
	 */
	private function checkPrivacy($records, $xrefs = false) {
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
	}

	/**
	 * Determine if the family parents are married.
	 *
	 * Don't use the default function because we want to privatize the record but display the name
	 * and the parents of the spouse if the spouse him/herself is not private.
	 *
	 * @param type $family
	 * @return boolean
	 */
	private function getMarriage($family) {
		$record = GedcomRecord::getInstance($family->xref(), $this->tree());
		foreach ($record->getFacts('MARR', false, Auth::PRIV_HIDE) as $fact) {
			if ($fact) {
				return true;
			}
		}
	}

	/**
	 * Check if this person is an adopted or foster child
	 *
	 * @param type $person
	 * @param type $parents
	 * @return attribute
	 */
	private function checkPedi($person, $parents) {
		$pedi = "";
		foreach ($person->getFacts('FAMC') as $fact) {
			if ($fact->getTarget() === $parents) {
				$pedi = $fact->getAttribute('PEDI');
				break;
			}
		}
		return $pedi;
	}

	/**
	 * Get the filename of the cached image
	 *
	 * @param Media $mediaobject
	 * @return filename
	 */
	public function cacheFileName(Media $mediaobject) {
		return $this->cacheDir() . $this->tree()->getTreeId() . '-' . $mediaobject->xref() . '-' . filemtime($mediaobject->getServerFilename()) . '.' . $mediaobject->extension();
	}

	/**
	 * remove all old cached files
	 */
	protected function emptyCache() {
		foreach (glob($this->cacheDir() . '*') as $cache_file) {
			if (is_file($cache_file)) {
				unlink($cache_file);
			}
		}
	}

	/**
	 * Check if thumbnails from cache should be recreated
	 *
	 * @param type $mediaobject
	 * @return string filename
	 */
	private function getThumbnail(Media $mediaobject)
	{
		if (!file_exists(self::CACHE_DIR)) {
			mkdir(self::CACHE_DIR);
		}

		if (file_exists($mediaobject->getServerFilename())) {
			$cache_filename = $this->cacheFileName($mediaobject);

			if (!is_file($cache_filename)) {
				if ($this->options('resize_thumbs')) {
					$thumbnail	 = $this->fancyThumb($mediaobject);
					$mimetype	 = $mediaobject->mimeType();
					if ($mimetype === 'image/jpeg') {
						imagejpeg($thumbnail, $cache_filename);
					} elseif ($mimetype === 'image/png') {
						imagepng($thumbnail, $cache_filename);
					} elseif ($mimetype === 'image/gif') {
						imagegif($thumbnail, $cache_filename);
					} else {
						return;
					}
				} else {
					// if we are using the original webtrees thumbnails, copy them to the ftv_cache folder
					// so we can cache them either and output them in the same way we would output the fancy thumbnail.
					try {
						copy($mediaobject->getServerFilename('thumb'), $cache_filename);
					} catch (Exception $ex) {
						// something went wrong while copying the default webtrees image to the ftv cache folder
						// there is a fallback in the function printThumbnail(): output $mediaobject->displayImage();
					}
				}
			}
			return $cache_filename;
		}
	}

	/**
	 * Get the Fancy thumbnail (highlighted image)
	 *
	 * @param type $mediaobject
	 * @return image
	 */
	private function fancyThumb($mediaobject) {
		// option 1 = percentage of original webtrees thumbnail
		// option 2 = size in pixels
		$resize_format = $this->options('thumb_resize_format');
		if ($resize_format === '1') {
			$mediasrc = $mediaobject->getServerFilename('thumb');
		} else {
			$mediasrc = $mediaobject->getServerFilename('main');
		}

		if (is_file($mediasrc)) {
			$thumbsize	 = $this->options('thumb_size');
			$thumbwidth	 = $thumbheight = $thumbsize;

			$mimetype = $mediaobject->mimeType();
			if ($mimetype === 'image/jpeg' || $mimetype === 'image/png' || $mimetype === 'image/gif') {

				if (!list($imagewidth, $imageheight) = getimagesize($mediasrc)) {
					return null;
				}

				switch ($mimetype) {
					case 'image/jpeg':
						$image	 = imagecreatefromjpeg($mediasrc);
						break;
					case 'image/png':
						$image	 = imagecreatefrompng($mediasrc);
						break;
					case 'image/gif':
						$image	 = imagecreatefromgif($mediasrc);
						break;
				}

				// fallback if image is in the database but not on the server
				if (isset($imagewidth) && isset($imageheight)) {
					$ratio = $imagewidth / $imageheight;
				} else {
					$ratio = 1;
				}

				if ($resize_format === '1') {
					$thumbwidth	 = $thumbwidth / 100 * $imagewidth;
					$thumbheight = $thumbheight / 100 * $imageheight;
				}

				$square = $this->options('use_square_thumbs');
				if ($square == true) {
					$thumbheight = $thumbwidth;
					if ($ratio < 1) {
						$new_height	 = $thumbwidth / $ratio;
						$new_width	 = $thumbwidth;
					} else {
						$new_width	 = $thumbheight * $ratio;
						$new_height	 = $thumbheight;
					}
				} else {
					if ($resize_format === '1') {
						$new_width	 = $thumbwidth;
						$new_height	 = $thumbheight;
					} elseif ($imagewidth > $imageheight) {
						$new_height	 = $thumbheight / $ratio;
						$new_width	 = $thumbwidth;
					} elseif ($imageheight > $imagewidth) {
						$new_width	 = $thumbheight * $ratio;
						$new_height	 = $thumbheight;
					} else {
						$new_width	 = $thumbwidth;
						$new_height	 = $thumbheight;
					}
				}
				$process = imagecreatetruecolor(round($new_width), round($new_height));
				if ($mimetype == 'image/png') { // keep transparancy for png files.
					imagealphablending($process, false);
					imagesavealpha($process, true);
				}
				imagecopyresampled($process, $image, 0, 0, 0, 0, $new_width, $new_height, $imagewidth, $imageheight);

				if ($square) {
					$thumb = imagecreatetruecolor($thumbwidth, $thumbheight);
				} else {
					$thumb = imagecreatetruecolor($new_width, $new_height);
				}

				if ($mimetype == 'image/png') {
					imagealphablending($thumb, false);
					imagesavealpha($thumb, true);
				}
				imagecopyresampled($thumb, $process, 0, 0, 0, 0, $thumbwidth, $thumbheight, $thumbwidth, $thumbheight);

				imagedestroy($process);
				imagedestroy($image);

				return $thumb;
			}
		}
	}


     /**
     * Get the url slug for this page
     */
    public function getSlug($string): String
    {
        return preg_replace('/\s+/', '-', strtolower(preg_replace("/&([a-z])[a-z]+;/i", "$1", htmlentities($string))));
    }
};
