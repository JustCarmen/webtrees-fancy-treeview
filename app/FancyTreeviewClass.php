<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2017 webtrees development team
 * Copyright (C) 2017 JustCarmen
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */
namespace JustCarmen\WebtreesAddOns\FancyTreeview;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Controller\RelationshipController;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\File;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\Functions;
use Fisharebest\Webtrees\Functions\FunctionsDate;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Soundex;
use Fisharebest\Webtrees\Tree;
use PDO;

/**
 * Class FancyTreeview
 */
class FancyTreeviewClass extends FancyTreeviewModule {

  /** var array of xrefs (individual id's) */
	public $pids;

	/** var integer generation number */
	public $generation;

	/** var integer used for follow index */
	public $index;

	/**
	 * Set the default module options
	 *
	 * @param type $key
	 * @return string
	 */
	private function setDefault($key) {
		$FTV_DEFAULT = [
		'USE_FULLNAME'       => '0',
		'GENERATIONS'        => '0',
		'CHECK_RELATIONSHIP' => '0',
		'SHOW_SINGLES'       => '0',
		'SHOW_PLACES'        => '1',
		'USE_GEDCOM_PLACES'  => '0',
		'COUNTRY'            => '',
		'SHOW_OCCU'          => '1',
		'RESIZE_THUMBS'      => '1',
		'THUMBNAIL_WIDTH'    => '60',
		'CROP_THUMBNAILS'    => '0',
		'SHOW_USERFORM'      => '2',
		'FTV_TAB'            => '1',
	];
		return $FTV_DEFAULT[$key];
	}

	/**
	 * Get module options
	 * @param type $k
	 * @return type
	 */
	protected function options($k) {
		$FTV_OPTIONS = unserialize($this->getPreference('FTV_OPTIONS'));
		$key         = strtoupper($k);

		if (empty($FTV_OPTIONS[$this->tree()->getTreeId()]) || (is_array($FTV_OPTIONS[$this->tree()->getTreeId()]) && !array_key_exists($key, $FTV_OPTIONS[$this->tree()->getTreeId()]))) {
			return $this->setDefault($key);
		} else {
			return($FTV_OPTIONS[$this->tree()->getTreeId()][$key]);
		}
	}

	/**
	 * Get Indis from surname input - see: WT\Controller\Branches.php - loadIndividuals
	 *
	 * @param type $surname
	 * @param type $russell
	 * @param type $daitchMokotoff
	 * @return type
	 */
	protected function indisArray($surname, $russell, $daitchMokotoff) {
		$sql = "SELECT DISTINCT i_id AS xref, i_file AS tree_id, i_gedcom AS gedcom" .
		" FROM `##individuals`" .
		" JOIN `##name` ON (i_id = n_id AND i_file = n_file)" .
		" WHERE n_file = :tree_id" .
		" AND n_type != '_MARNM'" .
		" AND (n_surn = :surname1 OR n_surname = :surname2";
		$args = [
		'tree_id'  => $this->tree()->getTreeId(),
		'surname1' => $surname,
		'surname2' => $surname
	];
		if ($russell) { // works only with latin letters. For other letters it outputs the code '0000'.
			foreach (explode(':', Soundex::russell($surname)) as $value) {
				if ($value != '0000') {
					$sql .= " OR n_soundex_surn_std LIKE CONCAT('%', '" . $value . "', '%')";
				}
			}
		}
		if ($daitchMokotoff) { // works only with predefined letters and lettercombinations. Fot other letters it outputs the code '000000'.
			foreach (explode(':', Soundex::daitchMokotoff($surname)) as $value) {
				if ($value != '000000') {
					$sql .= " OR n_soundex_surn_dm LIKE CONCAT('%', '" . $value . "', '%')";
				}
			}
		}
		$sql .= ')';

		$rows = Database::prepare($sql)
		->execute($args)
		->fetchAll();
		$data = [];
		foreach ($rows as $row) {
			$tree   = Tree::findById($row->tree_id);
			$data[] = Individual::getInstance($row->xref, $tree, $row->gedcom);
		}
		return $data;
	}

	/**
	 * Get surname from pid
	 *
	 * @param type $pid
	 * @return type
	 */
	public function getSurname($pid) {
		$sql  = "SELECT n_surname AS surname FROM `##name` WHERE n_file = :tree_id AND n_id = :pid AND n_type = 'NAME'";
		$args = [
		'tree_id' => $this->tree()->getTreeId(),
		'pid'     => $pid
	];
		$data = Database::prepare($sql)->execute($args)->fetchOne();
		return $data;
	}

	/**
	 * Search within a multiple dimensional array
	 *
	 * @param type $array
	 * @param type $key
	 * @param type $value
	 * @return results
	 */
	protected function searchArray($array, $key, $value) {
		$results = [];
		if (is_array($array)) {
			if (isset($array[$key]) && $array[$key] == $value) {
				$results[] = $array;
			}
			foreach ($array as $subarray) {
				$results = array_merge($results, $this->searchArray($subarray, $key, $value));
			}
		}
		return $results;
	}

	/**
	 * Sort the array according to the $key['SORT'] input.
	 *
	 * @param type $array
	 * @param type $sort_by
	 * @return array values
	 */
	protected function sortArray($array, $sort_by) {
		$array_keys = ['tree', 'surname', 'pid', 'access_level', 'sort'];

		foreach ($array as $pos => $val) {
			$tmp_array[$pos] = $val[$sort_by];
		}
		asort($tmp_array);

		$return_array = [];
		foreach ($tmp_array as $pos => $val) {
			foreach ($array_keys as $key) {
				$key                      = strtoupper($key);
				$return_array[$pos][$key] = $array[$pos][$key];
			}
		}
		return array_values($return_array);
	}

	/**
	 * Get the page link to store in the database
	 *
	 * @param type $pid
	 * @return string
	 */
	protected function getPageLink($pid) {
		$link = '<a href="module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;ged=' . $this->tree()->getNameHtml() . '&amp;rootid=' . $pid . '" target="_blank">';

		if ($this->options('use_fullname') == true) {
			$link .= I18N::translate('Descendants of %s', Individual::getInstance($pid, $this->tree())->getFullName());
		} else {
			$link .= I18N::translate('Descendants of the %s family', $this->getSurname($pid));
		}

		$link .= '</a>';

		return $link;
	}

	/**
	 * Get a list of all used countries
	 *
	 * @return list
	 */
	protected function getCountryList() {
		$sql  = "SELECT SQL_CACHE p_place as country FROM `##places` WHERE p_parent_id=:parent_id AND p_file=:tree_id";
		$args = [
		'parent_id' => '0',
		'tree_id'   => $this->tree()->getTreeId()
	];

		$countries = Database::prepare($sql)->execute($args)->fetchAll(PDO::FETCH_ASSOC);

		$list = [];
		foreach ($countries as $country) {
			$country_name        = $country['country'];
			$list[$country_name] = $country_name; // set the country name as key to display as option value.
		}
		return $list;
	}

	/**
	 * Since we can't use Flashmessages here, use our own message system
	 *
	 * @param type $id
	 * @param type $level
	 * @param type $hidden
	 * @param type $message
	 * @return type
	 */
	protected function addMessage($id, $level, $hidden, $message = '') {
		$style = $hidden ? ' style="display:none"' : '';

		return
		'<div id="' . $id . '" class="alert-message"' . $style . '>' .
		'<div class="alert alert-' . $level . ' alert-dismissible" role="alert">' .
		'<button type="button" class="close" data-dismiss="alert" aria-label="' . I18N::translate('close') . '">' .
		'<span aria-hidden="true">&times;</span>' .
		'</button>' .
		'<span class="message">' . $message . '</span>' .
		'</div></div>';
	}

	/**
	 * Get the root ID
	 * @return ID
	 */
	protected function rootId() {
		if ($this->isTab()) {
			return Filter::get('pid', WT_REGEX_XREF);
		} else {
			return Filter::get('rootid', WT_REGEX_XREF);
		}
	}

	/**
	 * Set the number of generations to display
	 * @return integer
	 */
	protected function generations() {
		if ($this->isTab()) {
			$generations = 3;
		} else {
			$generations = $this->options('generations');

			if ($generations === 0 || $this->action === 'full_pdf') {
				$generations = 99;
			} else {
				$generations = $generations + (Filter::getBool('readmore') ? 3 : 0);
			}
		}

		return $generations;
	}

	/**
	 * Print the Fancy Treeview page
	 *
	 * @return html
	 */
	public function printPage() {
		$this->generation = Filter::get('gen', WT_REGEX_INTEGER);
		$this->pids       = explode('|', Filter::get('pids'));
		$generations      = $this->generations();

		$html = '';
		if (!$this->generation && !array_filter($this->pids)) {
			$this->generation = 1;
			$generations      = $generations - 1;
			$this->pids       = [$this->rootId()];

			$html .= $this->printGeneration();
		}

		$lastblock = $this->generation + $generations + 1; // + 1 to get one hidden block.
		while (count($this->pids) > 0 && $this->generation < $lastblock) {
			$pids = $this->pids;
			unset($this->pids); // empty the array (will be filled with the next generation)

			foreach ($pids as $pid) {
				$next_gen[] = $this->getNextGen($pid);
			}

			foreach ($next_gen as $descendants) {
				if (count($descendants) > 0) {
					foreach ($descendants as $descendant) {
						if ($this->options('show_singles') == true || $descendant['desc'] == 1) {
							$this->pids[] = $descendant['pid'];
						}
					}
				}
			}

			if (!empty($this->pids)) {
				if ($this->isTab() && $this->generation > $generations) {
					$html .= $this->printReadMoreLink();
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

		$class_hidden  = $this->generations() > 0 && $this->generation > $this->generations() ? ' generation-block-hidden' : '';
		$class_bmargin = $this->isTab() ? 'mb-2' : 'mb-4';

		// add data attributes to retrieve values easily with jquery (for scroll reference en next generations)
		$html = '<li class="generation-block mb-4' . $class_hidden . '" data-gen="' . $this->generation . '" data-pids="' . implode('|', $this->pids) . '">' .
		'<div class="card wt-block ' . $class_bmargin . '">' . $this->printBlockHeader();

		if ($this->checkPrivacy($this->pids, true)) {
			$html .= $this->printPrivateBlock();
		} else {
			$html .= $this->printBlockContent();
		}

		$html .= '</div></li>';

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
		'<div class="card-header wt-block-header">' .
		'<div class="header-title-container d-flex justify-content-between">' .
		'<span class="header-title">' . I18N::translate('Generation') . ' ' . $this->generation . '</span>' .
		$this->printBackToTopLink() .
		'</div></div>';
	}

	/**
	 *
	 * @return string
	 */
	protected function printBlockContent() {
		$html = '<div class="card-body wt-block-content">' .
		'<ol class="generation p-0">';

		foreach (array_unique($this->pids) as $pid) {
			$person = $this->getPerson($pid);
			if (!$this->hasParentsInSameGeneration($person)) {
				$family = $this->getFamily($person);
				if (!empty($family)) {
					$pid = $family->getXref();
				}
				$class = $person->canShow() ? 'family' : 'family private';

				$html .= '<li id="' . $pid . '" class="' . $class . ' d-flex flex-wrap">' . $this->printIndividual($person) . '</li>';
			}
		}
		$html .= '</ol></div>';
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
			return '<a href="#fancy-treeview-page" class="small text-muted back-to-top scroll">' . I18N::translate('back to top') . '</a>';
		}
	}

	/**
	 * Print read-more link
	 *
	 * @param type $root
	 * @return string
	 */
	protected function printReadMoreLink() {
		return
		'<div class="read-more text-right">' .
		'<a href="module.php?mod=' . $this->getName() . '&amp;mod_action=page&rootid=' . $this->rootId() . '&amp;ged=' . rawurlencode(Tree::findById($this->tree()->getTreeId())->getName()) . '&readmore=true">' .
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
		'<div class="card-body wt-block-content">' .
		'<div class="generation private">' .
		I18N::translate('The details of this generation are private.') .
		'</div></div>';
	}

	/**
	 * Print the content for one individual
	 *
	 * @param type $person
	 * @return string (html)
	 */
	protected function printIndividual(Individual $person) {
		if ($person->CanShow()) {
			$html = '<div class="parents d-inline-flex px-2">' . $this->printThumbnail($person) . '<p class="parents-data">' . $this->printNameUrl($person, $person->getXref());
			if ($this->options('show_occu')) {
				$html .= $this->printOccupations($person);
			}

			$html .= $this->printParents($person) . $this->printLifespan($person) . '.';

			// get a list of all the spouses
			/*
			 * First, determine the true number of spouses by checking the family gedcom
			 */
			$spousecount = 0;
			foreach ($person->getSpouseFamilies(Auth::PRIV_HIDE) as $i => $family) {
				$spouse = $family->getSpouse($person);
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
				foreach ($person->getSpouseFamilies(Auth::PRIV_HIDE) as $i => $family) {
					$spouse = $family->getSpouse($person);
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
			foreach ($person->getSpouseFamilies(Auth::PRIV_HIDE) as $family) {
				$spouse = $family->getSpouse($person);

				$html .= $this->printChildren($family, $person, $spouse);
			}

			return $html;
		} else {
			if ($person->getTree()->getPreference('SHOW_PRIVATE_RELATIONSHIPS')) {
				return '<p>' . I18N::translate('The details of this family are private.') . '</p>';
			}
		}
	}

	/**
	 * Print the content for a spouse
	 *
	 * @param type $family
	 * @param type $person
	 * @param type $spouse
	 * @param type $i
	 * @param type $count
	 * @return string
	 */
	protected function printSpouse($family, $person, $spouse, $i, $count) {
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
			switch ($person->getSex()) {
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
			switch ($person->getSex()) {
		case 'M':
		  $html .= I18N::translate('He married');
		  break;
		case 'F':
		  $html .= I18N::translate('She married');
		  break;
		default:
		  $html .= I18N::translate('This individual married');
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

			$marriage = $family->getFirstFact('MARR');
			if ($marriage) {
				$html .= $this->printDate($marriage) . $this->printPlace($marriage);
			}

			if ($this->printLifespan($spouse, true)) {
				$html .= $this->printLifespan($spouse, true);
			}
			$html .= '. ';

			$divorce = $family->getFirstFact('DIV');
			if ($divorce) {
				$html .= $this->printName($person) . ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse) . ' ' . I18N::translate('were divorced') . $this->printDate($divorce) . '.';
			}
		}
		return $html;
	}

	/**
	 * Print the content for a non-married partner
	 *
	 * @param type $family
	 * @param type $person
	 * @param type $spouse
	 * @return type
	 */
	protected function printPartner($family, $person, $spouse) {
		$html = ' ';

		switch ($person->getSex()) {
	  case 'M':
		$html .= I18N::translate('He had a relationship with');
		break;
	  case 'F':
		$html .= I18N::translate('She had a relationship with');
		break;
	  default:
		$html .= I18N::translate('This individual had a relationship with');
		break;
	}

		$html .= ' ' . $this->printNameUrl($spouse);
		$html .= $this->printRelationship($person, $spouse);
		$html .= $this->printParents($spouse);

		if ($family->getFirstFact('_NMR') && $this->printLifespan($spouse, true)) {
			$html .= $this->printLifespan($spouse, true);
		}

		return '. ' . $html;
	}

	/**
	 * Print the childrens list
	 *
	 * @param type $family
	 * @param type $person
	 * @param type $spouse
	 * @return string
	 */
	protected function printChildren($family, $person, $spouse) {
		$html = '';

		$match = null;
		if (preg_match('/\n1 NCHI (\d+)/', $family->getGedcom(), $match) && $match[1] == 0) {
			$html .= '<div class="children mb-4 px-2 ml-5"><p class="children-data">' . $this->printName($person) . ' ';
			if ($spouse && $spouse->CanShow()) {
				$html .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse) . ' ';
				$html .= I18N::translateContext('Two parents/one child', 'had');
			} else {
				$html .= I18N::translateContext('One parent/one child', 'had');
			}
			$html .= ' ' . I18N::translate('none') . ' ' . I18N::translate('children') . '.</p></div>';
		} else {
			$children = $family->getChildren();
			if ($children) {
				if ($this->checkPrivacy($children)) {
					$html .= '<div class="children mb-4 px-2 ml-5"><p class="children-data">' . $this->printName($person) . ' ';
					// needs multiple translations for the word 'had' to serve different languages.
					if ($spouse && $spouse->CanShow()) {
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
					$html .= '<div class="children mb-4 px-2 ml-5"><p class="children-data">' . I18N::translate('Children of ') . $this->printName($person);
					if ($spouse && $spouse->CanShow()) {
						$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse);
					}
					$html .= ':<ol class="p-0">';

					foreach ($children as $child) {
						if ($child->canShow()) {
							$html .= '<li class="child d-flex"><div class="child-data px-2">' . $this->printNameUrl($child);
							$pedi = $this->checkPedi($child, $family);

							if ($pedi) {
								$html .= ' <span class="pedi">';
								switch ($pedi) {
				  case 'foster':
					switch ($child->getSex()) {
					  case 'F':
						$html .= I18N::translateContext('FEMALE', 'foster child');
						break;
					  default:
						$html .= I18N::translateContext('MALE', 'foster child');
						break;
					}
					break;
				  case 'adopted':
					switch ($child->getSex()) {
					  case 'F':
						$html .= I18N::translateContext('FEMALE', 'adopted child');
						break;
					  default:
						$html .= I18N::translateContext('MALE', 'adopted child');
						break;
					}
					break;
				}
								$html .= '</span>';
							}

							if ($child->getBirthDate()->isOK() || $child->getDeathdate()->isOK()) {
								$html .= '<span class="lifespan"> (' . $child->getLifeSpan() . ')</span>';
							}

							$child_family = $this->getFamily($child);

							// do not load this part of the code in the fancy treeview tab on the individual page.
							if (WT_SCRIPT_NAME !== 'individual.php') {
								$text_follow = I18N::translate('follow') . ' ' . ($this->generation + 1) . '.' . $this->index;
								if ($child_family) {
									$html .= ' - <a class="scroll" href="#' . $child_family->getXref() . '">' . $text_follow . '</a>';
									$this->index++;
								} elseif ($this->options('show_singles') == true) {
									$html .= ' - <a class="scroll" href="#' . $child->getXref() . '">' . $text_follow . '</a>';
									$this->index++;
								}
							}
							$html .= '</div></li>';
						} else {
							$html .= '<li class="child d-flex private"><div class="child-data px-2">' . I18N::translate('Private') . '</div></li>';
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
			switch ($person->getSex()) {
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

			$father = $parents->getHusband();
			$mother = $parents->getWife();

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
	 * @param type $person
	 * @return string
	 */
	protected function printName(Individual $person) {
		$name = $person->getFullName();
		if ($this->pdf()) {
			return $this->pdf()->printName($person, $name);
		} else {
			return $name;
		}
	}

	/**
	 * Print the name of a person with the link to the individual page
	 *
	 * @param type $person
	 * @param type $xref
	 * @return string
	 */
	protected function printNameUrl($person, $xref = '') {
		$url = '<a href="' . $person->getHtmlUrl() . '">' . $person->getFullName() . '</a>';

		if ($this->pdf()) {
			return $this->pdf()->printNameUrl($person, $url);
		} else {
			return $url;
		}
	}

	/**
	 * Print occupations
	 *
	 * @param type $person
	 * @param type $tag
	 * @return string
	 */
	protected function printOccupations(Individual $person) {
		$html        = '';
		$occupations = $person->getFacts('OCCU', true);
		$count       = count($occupations);
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
			if (in_array(WT_LOCALE, ['de'])) {
				$html .= rtrim(ucfirst($fact->getValue()), ".");
			} else {
				$html .= rtrim(lcfirst($fact->getValue()), ".");
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
	 * @param type $person
	 * @param type $is_spouse
	 * @return string
	 */
	protected function printLifespan($person, $is_spouse = false) {
		$html = '';

		$is_bfact = false;
		foreach (explode('|', WT_EVENTS_BIRT) as $event) {
			$bfact = $person->getFirstFact($event);
			if ($bfact) {
				$bdate  = $this->printDate($bfact);
				$bplace = $this->printPlace($bfact);

				if ($bdate || $bplace) {
					$is_bfact = true;
					$html .= $this->printBirthText($person, $event, $is_spouse) . $bdate . $bplace;
					break;
				}
			}
		}

		$is_dfact = false;
		foreach (explode('|', WT_EVENTS_DEAT) as $event) {
			$dfact = $person->getFirstFact($event);
			if ($dfact) {
				$ddate  = $this->printDate($dfact);
				$dplace = $this->printPlace($dfact);

				if ($ddate || $dplace) {
					$is_dfact = true;
					$html .= $this->printDeathText($person, $event, $is_bfact) . $ddate . $dplace;
					break;
				}
			}
		}

		if ($is_bfact && $is_dfact && $bdate && $ddate) {
			$html .= $this->printAgeOfDeath($bfact, $dfact);
		}

		return $html;
	}

	/**
	 * Print the relationship between spouses (optional)
	 *
	 * @param type $person
	 * @param type $spouse
	 * @return string
	 */
	protected function printRelationship($person, $spouse) {
		$html = '';
		if ($this->options('check_relationship')) {
			$relationship = $this->checkRelationship($person, $spouse);
			if ($relationship) {
				$html .= ' (' . $relationship . ')';
			}
		}
		return $html;
	}

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
				$image   = '<img' .
			' dir="' . 'auto' . '"' . // For the tool-tip
			' class="pt-1 pr-2"' .
			' src="module.php?mod=' . $this->getName() . '&amp;mod_action=thumbnail&amp;mid=' . $mediaobject->getXref() . '"' .
			' alt="' . strip_tags($person->getFullName()) . '"' .
			' title="' . strip_tags($person->getFullName()) . '"' .
			' data-pdf="1"' .
			' data-cachefilename="' . basename($cache_filename) . '"' .
			' ' . $imgsize[3] . // height="yyy" width="xxx"
			'>';
				return
			'<a class="gallery" href="' . Html::escape($mediaobject->imageUrl(0, 0, '')) . '">' . $image . '</a>';
			} else {
				// fallback
				$thumbwidth = $thumbheight = $this->options('thumbnail_width');
				if ($this->options('crop_thumbnails')) {
					$resizetype = 'crop';
				} else {
					$resizetype = 'contain';
				}
				// set data-pdf = 0; default webtrees images can not be converted to pdf
				return $mediaobject->displayImage($thumbwidth, $thumbheight, $resizetype, ['class' => 'pt-1 pr-2', 'data-pdf' => '0']);
			}
		}
	}

	/**
	 * Print the birth text (born or baptized)
	 *
	 * @param type $person
	 * @param type $event
	 * @param type $is_spouse
	 * @return string
	 */
	protected function printBirthText($person, $event, $is_spouse = false) {
		$html = '';
		switch ($event) {
	  case 'BIRT':
		if ($is_spouse == true) {
			$html .= '. ';
			if ($person->isDead()) {
				$person->getSex() == 'F' ? $html .= I18N::translateContext('PAST', 'She was born') : $html .= I18N::translateContext('PAST', 'He was born');
			} else {
				$person->getSex() == 'F' ? $html .= I18N::translateContext('PRESENT', 'She was born') : $html .= I18N::translateContext('PRESENT', 'He was born');
			}
		} else {
			$this->printParents($person) || $this->printOccupations($person) ? $html .= ', ' : $html .= ' ';
			if ($person->isDead()) {
				$person->getSex() == 'F' ? $html .= I18N::translateContext('PAST (FEMALE)', 'was born') : $html .= I18N::translateContext('PAST (MALE)', 'was born');
			} else {
				$person->getSex() == 'F' ? $html .= I18N::translateContext('PRESENT (FEMALE)', 'was born') : $html .= I18N::translateContext('PRESENT (MALE)', 'was born');
			}
		}
		break;
	  case 'BAPM':
	  case 'CHR':
		if ($is_spouse == true) {
			$html .= '. ';
			if ($person->isDead()) {
				$person->getSex() == 'F' ? $html .= I18N::translateContext('PAST', 'She was baptized') : $html .= I18N::translateContext('PAST', 'He was baptized');
			} else {
				$person->getSex() == 'F' ? $html .= I18N::translateContext('PRESENT', 'She was baptized') : $html .= I18N::translateContext('PRESENT', 'He was baptized');
			}
		} else {
			$this->printParents($person) || $this->printOccupations($person) ? $html .= ', ' : $html .= ' ';
			if ($person->isDead()) {
				$person->getSex() == 'F' ? $html .= I18N::translateContext('PAST (FEMALE)', 'was baptized') : $html .= I18N::translateContext('PAST (MALE)', 'was baptized');
			} else {
				$person->getSex() == 'F' ? $html .= I18N::translateContext('PRESENT (FEMALE)', 'was baptized') : $html .= I18N::translateContext('PRESENT (MALE)', 'was bapitized');
			}
		}
		break;
	}
		return $html;
	}

	/**
	 * Print the death text (death or buried)
	 *
	 * @param type $person
	 * @param type $event
	 * @param type $is_bfact
	 * @return string
	 */
	protected function printDeathText($person, $event, $is_bfact) {
		$html = '';
		switch ($event) {
	  case 'DEAT':
		if ($is_bfact) {
			$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
			$person->getSex() == 'F' ? $html .= I18N::translateContext('FEMALE', 'died') : $html .= I18N::translateContext('MALE', 'died');
		} else {
			$person->getSex() == 'F' ? $html .= '. ' . I18N::translate('She died') : $html .= '. ' . I18N::translate('He died');
		}
		break;
	  case 'BURI':
		if ($is_bfact) {
			$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
			$person->getSex() == 'F' ? $html .= I18N::translateContext('FEMALE', 'was buried') : $html .= I18N::translateContext('MALE', 'was buried');
		} else {
			$person->getSex() == 'F' ? $html .= '. ' . I18N::translate('She was buried') : $html .= '. ' . I18N::translate('He was buried');
		}
		break;
	  case 'CREM':
		if ($is_bfact) {
			$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
			$person->getSex() == 'F' ? $html .= I18N::translateContext('FEMALE', 'was cremated') : $html .= I18N::translateContext('MALE', 'was cremated');
		} else {
			$person->getSex() == 'F' ? $html .= '. ' . I18N::translate('She was cremated') : $html .= '. ' . I18N::translate('He was cremated');
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
	protected function printAgeOfDeath($bfact, $dfact) {
		$bdate = $bfact->getDate();
		$ddate = $dfact->getDate();
		$html  = '';
		if ($bdate->isOK() && $ddate->isOK() && $this->isDateDMY($bfact) && $this->isDateDMY($dfact)) {
			$ageOfdeath = FunctionsDate::getAgeAtEvent(Date::GetAgeGedcom($bdate, $ddate), false);
			if (Date::getAge($bdate, $ddate, 0) < 2) {
				$html .= ' ' . /* I18N: %s is the age of death in days/months; %s is a string, e.g. at the age of 2 months */ I18N::translateContext('age in days/months', 'at the age of %s', $ageOfdeath);
			} else {
				$html .= ' ' . /* I18N: %s is the age of death in years; %s is a number, e.g. at the age of 40. If necessary add the term 'years' (always plural) to the string */ I18N::translateContext('age in years', 'at the age of %s', filter_var($ageOfdeath, FILTER_SANITIZE_NUMBER_INT));
			}
		}
		return $html;
	}

	/**
	 * Function to print dates with the right syntax
	 *
	 * @param type $fact
	 * @return type
	 */
	protected function printDate($fact) {
		$date = $fact->getDate();
		if ($date && $date->isOK()) {
			if (preg_match('/^(FROM|BET|TO|AND|BEF|AFT|CAL|EST|INT|ABT) (.+)/', $fact->getAttribute('DATE'))) {
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
			$place = new Place($place, $WT_TREE);
			$html  = ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before placesnames', 'in ');
			if ($this->options('use_gedcom_places') == true) {
				$html .= $place->getShortName();
			} else {
				$country   = $this->options('country');
				$new_place = array_reverse(explode(", ", $place->getGedcomName()));
				if (!empty($country) && $new_place[0] == $country) {
					unset($new_place[0]);
					$html .= '<span dir="auto">' . Html::escape(implode(', ', array_reverse($new_place))) . '</span>';
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
	 * @param type $pid
	 * @return object
	 */
	protected function getPerson($pid) {
		return Individual::getInstance($pid, $this->tree());
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
		foreach ($person->getSpouseFamilies(Auth::PRIV_HIDE) as $family) {
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
		$person = $this->getPerson($pid);
		$ng     = [];
		foreach ($person->getSpouseFamilies() as $family) {
			$children = $family->getChildren();
			if ($children) {
				foreach ($children as $key => $child) {
					$key                                                           = $family->getXref() . '-' . $key; // be sure the key is unique.
					$ng[$key]['pid']                                               = $child->getXref();
					$child->getSpouseFamilies(Auth::PRIV_HIDE) ? $ng[$key]['desc'] = 1 : $ng[$key]['desc'] = 0;
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
		$parents = $person->getPrimaryChildFamily();
		if ($parents) {
			$father = $parents->getHusband();
			$mother = $parents->getWife();
			if ($father) {
				$father = $father->getXref();
			}
			if ($mother) {
				$mother = $mother->getXref();
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
	 * check (blood) relationship between partners
	 *
	 * @param type $person
	 * @param type $spouse
	 * @return string (relationship name)
	 */
	private function checkRelationship($person, $spouse) {
		$controller = new RelationshipController();
		$paths      = $controller->calculateRelationships($person, $spouse, 1);
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
	}

	/**
	 * Check if this is a private record
	 * $records can be an array of xrefs or an array of objects
	 *
	 * @param type $records
	 * @param type $xrefs
	 * @return boolean
	 */
	private function checkPrivacy($records, $xrefs = false) {
		$count = 0;
		foreach ($records as $person) {
			if ($xrefs) {
				$person = $this->getPerson($person);
			}
			if ($person->CanShow()) {
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
		$record = GedcomRecord::getInstance($family->getXref(), $this->tree());
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
	 * Get the ftv_cache directory
	 *
	 * @return directory name
	 */
	public function cacheDir() {
		return WT_DATA_DIR . 'ftv_cache/';
	}

	/**
	 * Get the filename of the cached image
	 *
	 * @param Media $mediaobject
	 * @return filename
	 */
	public function cacheFileName(Media $mediaobject) {
		return $this->cacheDir() . $this->tree()->getTreeId() . '-' . $mediaobject->getXref() . '-' . filemtime($mediaobject->getServerFilename()) . '.' . $mediaobject->extension();
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
	private function getThumbnail(Media $mediaobject) {
		$cache_dir = $this->cacheDir();

		if (!file_exists($cache_dir)) {
			File::mkdir($cache_dir);
		}

		if (file_exists($mediaobject->getServerFilename())) {
			$cache_filename = $this->cacheFileName($mediaobject);

			if (!is_file($cache_filename)) {
				$thumbnail = $this->fancyThumb($mediaobject);
				$mimetype  = $mediaobject->mimeType();
				if ($mimetype === 'image/jpeg') {
					imagejpeg($thumbnail, $cache_filename);
				} elseif ($mimetype === 'image/png') {
					imagepng($thumbnail, $cache_filename);
				} elseif ($mimetype === 'image/gif') {
					imagegif($thumbnail, $cache_filename);
				} else {
					return;
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
		$mediasrc = $mediaobject->getServerFilename('main');

		if (is_file($mediasrc)) {
			$mimetype = $mediaobject->mimeType();
			if ($mimetype === 'image/jpeg' || $mimetype === 'image/png' || $mimetype === 'image/gif') {
				if (!list($imagewidth, $imageheight) = getimagesize($mediasrc)) {
					return null;
				}

				switch ($mimetype) {
		  case 'image/jpeg':
			$image = imagecreatefromjpeg($mediasrc);
			break;
		  case 'image/png':
			$image = imagecreatefrompng($mediasrc);
			break;
		  case 'image/gif':
			$image = imagecreatefromgif($mediasrc);
			break;
		}

				// fallback if image is in the database but not on the server
				if (isset($imagewidth) && isset($imageheight)) {
					$ratio = $imagewidth / $imageheight;
				} else {
					$ratio = 1;
				}

				$thumbwidth = $this->options('thumbnail_width');
				$crop       = $this->options('crop_thumbnails');
				if ($crop) {
					$thumbheight = $thumbwidth;
					if ($ratio < 1) {
						$new_height = $thumbwidth / $ratio;
						$new_width  = $thumbwidth;
					} else {
						$new_width  = $thumbheight * $ratio;
						$new_height = $thumbheight;
					}
				} else {
					// resize proportionally
					$thumbheight = $thumbwidth / $imagewidth * $imageheight;
					$new_width   = $thumbwidth;
					$new_height  = $thumbheight;
				}

				$process = imagecreatetruecolor(round($new_width), round($new_height));
				if ($mimetype == 'image/png') { // keep transparancy for png files.
					imagealphablending($process, false);
					imagesavealpha($process, true);
				}
				imagecopyresampled($process, $image, 0, 0, 0, 0, $new_width, $new_height, $imagewidth, $imageheight);

				if ($crop) {
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
}
