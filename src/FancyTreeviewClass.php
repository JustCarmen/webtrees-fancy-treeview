<?php
/**
 * webtrees: online genealogy
 * Copyright (C) 2015 webtrees development team
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
use Fisharebest\Webtrees\Controller\BaseController;
use Fisharebest\Webtrees\Controller\RelationshipController;
use Fisharebest\Webtrees\Database;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\File;
use Fisharebest\Webtrees\Filter;
use Fisharebest\Webtrees\Functions\Functions;
use Fisharebest\Webtrees\Functions\FunctionsDate;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Individual;
use Fisharebest\Webtrees\Media;
use Fisharebest\Webtrees\Place;
use Fisharebest\Webtrees\Soundex;
use Fisharebest\Webtrees\Theme;
use Fisharebest\Webtrees\Tree;
use PDO;

/**
 * Class FancyTreeview
 */
class FancyTreeviewClass extends FancyTreeviewModule {

	/**
	 * Set the default module options
	 *
	 * @param type $key
	 * @return string
	 */
	private function setDefault($key) {
		$FTV_DEFAULT = array(
			'USE_FULLNAME'			 => '0',
			'NUMBLOCKS'				 => '0',
			'CHECK_RELATIONSHIP'	 => '0',
			'SHOW_SINGLES'			 => '0',
			'SHOW_PLACES'			 => '1',
			'USE_GEDCOM_PLACES'		 => '0',
			'COUNTRY'				 => '',
			'SHOW_OCCU'				 => '1',
			'RESIZE_THUMBS'			 => '1',
			'THUMB_SIZE'			 => '60',
			'THUMB_RESIZE_FORMAT'	 => '2',
			'USE_SQUARE_THUMBS'		 => '1',
			'SHOW_USERFORM'			 => '2',
			'SHOW_PDF_ICON'			 => '2',
			'FTV_TAB'				 => '1',
		);
		return $FTV_DEFAULT[$key];
	}

	/**
	 * Get module options
	 * @param type $k
	 * @return type
	 */
	protected function options($k) {
		$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));
		$key = strtoupper($k);

		if (empty($FTV_OPTIONS[$this->tree_id]) || (is_array($FTV_OPTIONS[$this->tree_id]) && !array_key_exists($key, $FTV_OPTIONS[$this->tree_id]))) {
			return $this->setDefault($key);
		} else {
			return($FTV_OPTIONS[$this->tree_id][$key]);
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
		$args = array(
			'tree_id'	 => $this->tree_id,
			'surname1'	 => $surname,
			'surname2'	 => $surname
		);
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
		$data = array();
		foreach ($rows as $row) {
			$tree = Tree::findById($row->tree_id);
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
		$sql = "SELECT n_surname AS surname FROM `##name` WHERE n_file = :tree_id AND n_id = :pid AND n_type = 'NAME'";
		$args = array(
			'tree_id'	 => $this->tree_id,
			'pid'		 => $pid
		);
		$data = Database::prepare($sql)->execute($args)->fetchOne();
		return $data;
	}

	/**
	 * The sortname is used in the pdf index
	 * 
	 * @param type $person
	 * @return type
	 */
	private function getSortName($person) {
		$sortname = $person->getSortName();
		$text1 = I18N::translateContext('Unknown given name', '…');
		$text2 = I18N::translateContext('Unknown surname', '…');
		$search = array(',', '@P.N.', '@N.N.');
		$replace = array(', ', $text1, $text2);
		return str_replace($search, $replace, $sortname);
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
		$results = array();
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

		$array_keys = array('tree', 'surname', 'pid', 'access_level', 'sort');

		foreach ($array as $pos => $val) {
			$tmp_array[$pos] = $val[$sort_by];
		}
		asort($tmp_array);

		$return_array = array();
		foreach ($tmp_array as $pos => $val) {
			foreach ($array_keys as $key) {
				$key = strtoupper($key);
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
		$link = '<a href="module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;ged=' . $this->tree->getNameHtml() . '&amp;rootid=' . $pid . '" target="_blank">';

		if ($this->options('use_fullname') == true) {
			$link .= I18N::translate('Descendants of %s', Individual::getInstance($pid, $this->tree)->getFullName());
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
		$list = '';
		$sql = "SELECT SQL_CACHE p_place as country FROM `##places` WHERE p_parent_id=:parent_id AND p_file=:tree_id";
		$args = array(
			'parent_id'	 => '0',
			'tree_id'	 => $this->tree_id
		);

		$countries = Database::prepare($sql)->execute($args)->fetchAll(PDO::FETCH_ASSOC);

		foreach ($countries as $country) {
			$list[$country['country']] = $country['country']; // set the country as key to display as option value.
		}
		return $list;
	}

	/**
	 * Since we can't use Flashmessages here, use our own message system
	 *
	 * @param type $id
	 * @param type $type
	 * @param type $hidden
	 * @param type $message
	 * @return type
	 */
	protected function addMessage($id, $type, $hidden, $message = '') {
		$style = $hidden ? ' style="display:none"' : '';

		if (Theme::theme()->themeId() === '_administration') {
			return
				'<div id="' . $id . '" class="alert alert-' . $type . ' alert-dismissible"' . $style . '>' .
				'<button type="button" class="close" aria-label="' . I18N::translate('close') . '">' .
				'<span aria-hidden="true">&times;</span>' .
				'</button>' .
				'<span class="message">' . $message . '</span>' .
				'</div>';
		} else {
			return '<p class="ui-state-error">' . $message . '</p>';
		}
	}

	/**
	 * Get the root ID
	 * @return ID
	 */
	protected function rootId() {
		return Filter::get('rootid', WT_REGEX_XREF);
	}

	/**
	 * Print the Fancy Treeview page
	 *
	 * @return html
	 */
	protected function printPage() {
		$gen = Filter::get('gen', WT_REGEX_INTEGER);
		$pids = Filter::get('pids');
		$numblocks = $this->options('numblocks');

		if ($numblocks == 0) {
			$numblocks = 99;
		}

		$html = '';
		if (!isset($gen) && !isset($pids)) {
			$gen = 1;
			$numblocks = $numblocks - 1;
			$generation = array($this->rootId());
			$html .= $this->printGeneration($generation, $gen);
		} else {
			$generation = explode('|', $pids);
		}

		$lastblock = $gen + $numblocks + 1; // + 1 to get one hidden block.
		while (count($generation) > 0 && $gen < $lastblock) {
			$pids = $generation;
			unset($generation);

			foreach ($pids as $pid) {
				$next_gen[] = $this->getNextGen($pid);
			}

			foreach ($next_gen as $descendants) {
				if (count($descendants) > 0) {
					foreach ($descendants as $descendant) {
						if ($this->options('show_singles') == true || $descendant['desc'] == 1) {
							$generation[] = $descendant['pid'];
						}
					}
				}
			}

			if (!empty($generation)) {
				$gen++;
				$html .= $this->printGeneration($generation, $gen);
				unset($next_gen, $descendants, $pids);
			} else {
				return $html;
			}
		}
		return $html;
	}

	/**
	 * Print the tabcontent for this person on the individual page
	 *
	 * @param type $pid
	 * @return string (html)
	 */
	protected function printTabContent($pid) {
		$html = '';
		$gen = 1;
		$root = $pid; // save value for read more link
		$generation = array($pid);
		$html .= $this->printGeneration($generation, $gen);

		while (count($generation) > 0 && $gen < 4) {
			$pids = $generation;
			unset($generation);

			foreach ($pids as $pid) {
				$next_gen[] = $this->getNextGen($pid);
			}

			foreach ($next_gen as $descendants) {
				if (count($descendants) > 0) {
					foreach ($descendants as $descendant) {
						if ($this->options('show_singles') == true || $descendant['desc'] == 1) {
							$generation[] = $descendant['pid'];
						}
					}
				}
			}

			if (!empty($generation)) {
				if ($gen === 3) {
					$html .= $this->printReadMoreLink($root);
					return $html;
				} else {
					$gen++;
					$html .= $this->printGeneration($generation, $gen);
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
	 * @param type $generation
	 * @param type $i
	 * @return string
	 */
	private function printGeneration($generation, $i) {
		// added data attributes to retrieve values easily with jquery (for scroll reference en next generations).
		$html = '<li class="block generation-block" data-gen="' . $i . '" data-pids="' . implode('|', $generation) . '">' .
			$this->printBlockHeader($i);

		if ($this->checkPrivacy($generation, true)) {
			$html .= $this->printPrivateBlock();
		} else {
			$html .= $this->printBlockContent(array_unique($generation));
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
	private function printBlockHeader($i) {
		return
			'<div class="blockheader ui-state-default">' .
			'<span class="header-title">' . I18N::translate('Generation') . ' ' . $i . '</span>' .
			$this->printBackToTopLink($i) .
			'</div>';
	}

	/**
	 *
	 * @param type $generation
	 * @return string
	 */
	private function printBlockContent($generation) {
		$html = '<ol class="blockcontent generation">';
		foreach ($generation as $pid) {
			$person = $this->getPerson($pid);
			if (!$this->hasParentsInSameGeneration($person, $generation)) {
				$family = $this->getFamily($person);
				if (!empty($family)) {
					$id = $family->getXref();
				} else {
					if ($this->options('show_singles') == true || !$person->getSpouseFamilies()) {
						$id = 'S' . $pid;
					} // Added prefix (S = Single) to prevent double id's.
				}
				$class = $person->canShow() ? 'family' : 'family private';
				$html .= '<li id="' . $id . '" class="' . $class . '">' . $this->printIndividual($person) . '</li>';
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
	private function printBackToTopLink($i) {
		if ($this->action === 'page' && $i > 1) {
			return '<a href="#fancy_treeview-page" class="header-link scroll">' . I18N::translate('back to top') . '</a>';
		}
	}

	/**
	 * Print read-more link
	 *
	 * @param type $root
	 * @return string
	 */
	private function printReadMoreLink($root) {
		return
			'<div id="read-more-link">' .
			'<a href="module.php?mod=' . $this->getName() . '&amp;mod_action=page&rootid=' . $root . '&amp;ged=' . Filter::escapeUrl(Tree::findById($this->tree_id)->getName()) . '">' .
			I18N::translate('Read more') .
			'</a>' .
			'</div>';
	}

	/**
	 * Print private block content
	 *
	 * @return string
	 */
	private function printPrivateBlock() {
		return
			'<div class="blockcontent generation private">' .
			I18N::translate('The details of this generation are private.') .
			'</div>';
	}

	/**
	 * Print the content for one individual
	 *
	 * @param type $person
	 * @return string (html)
	 */
	private function printIndividual($person) {

		if ($person->CanShow()) {
			$html = '<div class="parents">' . $this->printThumbnail($person) . '<p class="desc">' . $this->printNameUrl($person, $person->getXref());
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
				return I18N::translate('The details of this family are private.');
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
	private function printSpouse($family, $person, $spouse, $i, $count) {

		$html = ' ';

		if ($count > 1) {
			// we assume no one married more then ten times.
			$wordcount = array(
				/* I18N: first marriage  */  I18N::translate('first'),
				/* I18N: second marriage  */ I18N::translate('second'),
				/* I18N: third marriage  */  I18N::translate('third'),
				/* I18N: fourth marriage  */ I18N::translate('fourth'),
				/* I18N: fifth marriage  */  I18N::translate('fifth'),
				/* I18N: sixth marriage  */  I18N::translate('sixth'),
				/* I18N: seventh marriage  */I18N::translate('seventh'),
				/* I18N: eighth marriage  */ I18N::translate('eighth'),
				/* I18N: ninth marriage  */  I18N::translate('ninth'),
				/* I18N: tenth marriage  */  I18N::translate('tenth'),
			);
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
	private function printPartner($family, $person, $spouse) {

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
	private function printChildren($family, $person, $spouse) {
		$html = '';

		$match = null;
		if (preg_match('/\n1 NCHI (\d+)/', $family->getGedcom(), $match) && $match[1] == 0) {
			$html .= '<div class="children"><p>' . $this->printName($person) . ' ';
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
					$html .= '<div class="children"><p>' . $this->printName($person) . ' ';
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
					$html .= '<div class="children"><p>' . I18N::translate('Children of ') . $this->printName($person);
					if ($spouse && $spouse->CanShow()) {
						$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $this->printName($spouse);
					}
					$html .= ':<ol>';

					foreach ($children as $child) {
						$html .= '<li class="child">' . $this->printNameUrl($child);
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

						if ($child->CanShow() && ($child->getBirthDate()->isOK() || $child->getDeathdate()->isOK())) {
							$html .= '<span class="lifespan"> (' . $child->getLifeSpan() . ')</span>';
						}

						$child_family = $this->getFamily($child);

						// do not load this part of the code in the fancy treeview tab on the individual page.
						if (WT_SCRIPT_NAME !== 'individual.php') {
							if ($child->canShow() && $child_family) {
								$html .= ' - <a class="scroll" href="#' . $child_family->getXref() . '"></a>';
							} else { // just go to the person details in the next generation (added prefix 'S'for Single Individual, to prevent double ID's.)
								if ($this->options('show_singles') == true) {
									$html .= ' - <a class="scroll" href="#S' . $child->getXref() . '"></a>';
								}
							}
						}
						$html .= '</li>';
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
	private function printParents($person) {
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
	private function printName($person) {
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
	private function printNameUrl($person, $xref = '') {
		if ($xref) {
			$name = ' name="' . $xref . '"';
		} else {
			$name = '';
		}
		
		$url = '<a' . $name . ' href="' . $person->getHtmlUrl() . '">' . $person->getFullName() . '</a>';
		
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
	private function printOccupations($person) {
		$html = '';
		$occupations = $person->getFacts('OCCU', true);
		$count = count($occupations);
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
			if (in_array(WT_LOCALE, array('de'))) {
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
	private function printLifespan($person, $is_spouse = false) {
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
	private function printRelationship($person, $spouse) {
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
	private function printThumbnail($person) {
		$mediaobject = $person->findHighlightedMedia();
		if ($mediaobject) {
			$cache_filename = $this->getThumbnail($mediaobject);
			if ($this->options('resize_thumbs') && is_file($cache_filename)) {
				$imgsize = getimagesize($cache_filename);
				// Use the Fancy thumbnail image
				$image = '<img' .
					' dir="' . 'auto' . '"' . // For the tool-tip
					' src="module.php?mod=' . $this->getName() . '&amp;mod_action=thumbnail&amp;mid=' . $mediaobject->getXref() . '&amp;thumb=2&amp;cb=' . $mediaobject->getEtag() . '"' .
					' alt="' . strip_tags($person->getFullName()) . '"' .
					' title="' . strip_tags($person->getFullName()) . '"' .
					' ' . $imgsize[3] . // height="yyy" width="xxx"
					'>';
				return
					'<a' .
					' class="' . 'gallery' . '"' .
					' href="' . $mediaobject->getHtmlUrlDirect() . '"' .
					' type="' . $mediaobject->mimeType() . '"' .
					' data-obje-url="' . $mediaobject->getHtmlUrl() . '"' .
					' data-obje-note="' . Filter::escapeHtml($mediaobject->getNote()) . '"' .
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
	 * @param type $person
	 * @param type $event
	 * @param type $is_spouse
	 * @return string
	 */
	private function printBirthText($person, $event, $is_spouse = false) {
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
	private function printDeathText($person, $event, $is_bfact) {
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
	private function printAgeOfDeath($bfact, $dfact) {
		$bdate = $bfact->getDate();
		$ddate = $dfact->getDate();
		$html = '';
		if ($bdate->isOK() && $ddate->isOK() && $this->isDateDMY($bfact) && $this->isDateDMY($dfact)) {
			$ageOfdeath = FunctionsDate::getAgeAtEvent(Date::GetAgeGedcom($bdate, $ddate), false);
			if (Date::getAge($bdate, $ddate, 0) < 2) {
				$html .= ' ' . /* I18N: %s is the age of death in days/months; %s is a string, e.g. at the age of 2 months */ I18N::translateContext('age in days/months', 'at the age of %s', $ageOfdeath);
			} else {
				$html .= ' ' . /* I18N: %s is the age of death in years; %s is a number, e.g. at the age of 40 */ I18N::translateContext('age in years', 'at the age of %s', $ageOfdeath);
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
	private function printDate($fact) {
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
	private function printPlace($fact) {
		global $WT_TREE;
		$place = $fact->getAttribute('PLAC');
		if ($place && $this->options('show_places') == true) {
			$place = new Place($place, $WT_TREE);
			$html = ' ' . /* I18N: Note the space at the end of the string */ I18N::translateContext('before placesnames', 'in ');
			if ($this->options('use_gedcom_places') == true) {
				$html .= $place->getShortName();
			} else {
				$country = $this->options('country');
				$new_place = array_reverse(explode(", ", $place->getGedcomName()));
				if (!empty($country) && $new_place[0] == $country) {
					unset($new_place[0]);
					$html .= '<span dir="auto">' . Filter::escapeHtml(implode(', ', array_reverse($new_place))) . '</span>';
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
		return Individual::getInstance($pid, $this->tree);
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
	private function getFamily($person) {
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
		$ng = array();
		foreach ($person->getSpouseFamilies() as $family) {
			$children = $family->getChildren();
			if ($children) {
				foreach ($children as $key => $child) {
					$key = $family->getXref() . '-' . $key; // be sure the key is unique.
					$ng[$key]['pid'] = $child->getXref();
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
	 * @param type $generation
	 * @return boolean
	 */
	private function hasParentsInSameGeneration($person, $generation) {
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
			if (in_array($father, $generation) || in_array($mother, $generation)) {
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
		$paths = $controller->calculateRelationships($person, $spouse, 1);
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
	 *
	 * @param type $record
	 * @param type $xrefs
	 * @return boolean
	 */
	private function checkPrivacy($record, $xrefs = false) {
		$count = 0;
		foreach ($record as $person) {
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
		$record = GedcomRecord::getInstance($family->getXref(), $this->tree);
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
	protected function cacheDir() {
		return WT_DATA_DIR . 'ftv_cache/thumbs/';
	}

	/**
	 * Get the filename of the cached image
	 *
	 * @param Media $mediaobject
	 * @return filename
	 */
	public function cacheFileName(Media $mediaobject) {
		return $this->cacheDir() . $this->tree_id . '-' . $mediaobject->getXref() . '-' . filemtime($mediaobject->getServerFilename()) . '.' . $mediaobject->extension();
	}

	/**
	 * remove all old cached files for this tree
	 */
	protected function emptyCache() {
		foreach (glob($this->cacheDir() . '*') as $cache_file) {
			if (is_file($cache_file)) {
				$tree_id = intval(explode('-', basename($cache_file))[0]);
				if ($tree_id === $this->tree_id) {
					unlink($cache_file);
				}
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
				$mimetype = $mediaobject->mimeType();
				if ($mimetype === 'image/jpeg') {
					imagejpeg($thumbnail, $cache_filename);
				} elseif ($mimetype === 'image/png') {
					imagepng($thumbnail, $cache_filename);
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
		// option 1 = percentage of original webtrees thumbnail
		// option 2 = size in pixels
		$resize_format = $this->options('thumb_resize_format');
		if ($resize_format === '1') {
			$mediasrc = $mediaobject->getServerFilename('thumb');
		} else {
			$mediasrc = $mediaobject->getServerFilename('main');
		}

		if (is_file($mediasrc)) {
			$thumbsize = $this->options('thumb_size');
			$thumbwidth = $thumbheight = $thumbsize;

			$mimetype = $mediaobject->mimeType();
			if ($mimetype === 'image/jpeg' || $mimetype === 'image/png') {

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
				}

				// fallback if image is in the database but not on the server
				if (isset($imagewidth) && isset($imageheight)) {
					$ratio = $imagewidth / $imageheight;
				} else {
					$ratio = 1;
				}

				if ($resize_format === '1') {
					$thumbwidth = $thumbwidth / 100 * $imagewidth;
					$thumbheight = $thumbheight / 100 * $imageheight;
				}

				$square = $this->options('use_square_thumbs');
				if ($square == true) {
					$thumbheight = $thumbwidth;
					if ($ratio < 1) {
						$new_height = $thumbwidth / $ratio;
						$new_width = $thumbwidth;
					} else {
						$new_width = $thumbheight * $ratio;
						$new_height = $thumbheight;
					}
				} else {
					if ($resize_format === '1') {
						$new_width = $thumbwidth;
						$new_height = $thumbheight;
					} elseif ($imagewidth > $imageheight) {
						$new_height = $thumbheight / $ratio;
						$new_width = $thumbwidth;
					} elseif ($imageheight > $imagewidth) {
						$new_width = $thumbheight * $ratio;
						$new_height = $thumbheight;
					} else {
						$new_width = $thumbwidth;
						$new_height = $thumbheight;
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
	 * Get the Fancy treeview theme corresponding with the current user theme	 *
	 * Take into account the use of a custom childtheme
	 *
	 * @return theme directory
	 */
	private function theme() {
		$theme_dir = $this->directory . '/css/themes/';
		if (file_exists($theme_dir . Theme::theme()->themeId())) {
			return $theme_dir . theme::theme()->themeId();
		} else {
			$parentclass = get_parent_class(Theme::theme());
			$parentclassname = explode('\\', $parentclass);
			if (end($parentclassname) !== 'AbstractTheme') {
				$parenttheme = new $parentclass;
				if (file_exists($theme_dir . $parenttheme->themeId())) {
					return $theme_dir . $parenttheme->themeId();
				}
			}
		}
	}

	/**
	 * Get the stylesheet which correspondents with the current user theme
	 *
	 * @return stylesheet
	 */
	protected function getStylesheet() {
		$stylesheet = '';

		$stylesheet .= $this->includeCss($this->directory . '/css/base/style.css');

		if ($this->theme()) {
			$stylesheet .= $this->includeCss($this->theme() . '/style.css');
		}

		return $stylesheet;
	}

	/**
	 * Determine which javascript file we need
	 *
	 * @param type $controller
	 * @param type $page
	 *
	 * @return inline and/or external Javascript
	 */
	protected function includeJs($controller, $page) {

		switch ($page) {
			case 'admin':
				$controller->addInlineJavascript('
				var ModuleDir			= "' . $this->directory . '";
				var ModuleName			= "' . $this->getName() . '";
				var ThemeID				= "' . Theme::theme()->themeId() . '";
			', BaseController::JS_PRIORITY_HIGH);
				$controller
					->addExternalJavascript(WT_AUTOCOMPLETE_JS_URL)
					->addInlineJavascript('autocomplete();')
					->addExternalJavascript($this->directory . '/js/admin.js');
				break;

			case 'menu':
				$controller->addInlineJavascript('
				var ModuleDir			= "' . $this->directory . '";
				var ModuleName			= "' . $this->getName() . '";
				var ThemeID				= "' . Theme::theme()->themeId() . '";
			', BaseController::JS_PRIORITY_HIGH);
				$controller->addInlineJavascript('jQuery(".fancy-treeview-script").remove();', BaseController::JS_PRIORITY_LOW);
				break;

			case 'page':
				$controller
					->addInlineJavascript('
				var PageTitle			= "' . urlencode(strip_tags($controller->getPageTitle())) . '";
				var RootID				= "' . $this->rootId() . '";
				var OptionsNumBlocks	= ' . $this->options('numblocks') . ';
				var TextFollow			= "' . I18N::translate('follow') . '";
				var TextOk				= "' . I18N::translate('ok') . '";
				var TextCancel			= "' . I18N::translate('cancel') . '";
			', BaseController::JS_PRIORITY_HIGH)
					->addExternalJavascript(WT_AUTOCOMPLETE_JS_URL)
					->addInlineJavascript('autocomplete();')
					->addExternalJavascript($this->directory . '/js/page.js');

				// some files needs an extra js script
				if ($this->theme()) {
					$js = $this->theme() . '/' . basename($this->theme()) . '.js';
					if (file_exists($js)) {
						$controller->addExternalJavascript($js);
					}
				}

				if ($this->options('show_userform') >= Auth::accessLevel($this->tree)) {
					$this->includeJsInline($controller);
				}
				break;
			case 'tab':
				$controller->addInlineJavascript('
					jQuery("a[href$=' . $this->getName() . ']").text("' . $this->getTabTitle() . '");
				');
				break;
		}
	}

	/**
	 * Add Inline Javascript
	 *
	 * @param type $controller
	 *
	 * @return javascript
	 */
	private function includeJsInline($controller) {
		$controller->addInlineJavascript('
			jQuery("#new_rootid").autocomplete({
				source: "autocomplete.php?field=INDI",
				html: true
			});

			// submit form to change root id
			jQuery( "form#change_root" ).submit(function(e) {
				e.preventDefault();
				var new_rootid = jQuery("form #new_rootid").val();
				var url = jQuery(location).attr("pathname") + "?mod=' . $this->getName() . '&mod_action=page&rootid=" + new_rootid;
				jQuery.ajax({
					url: url,
					csrf: WT_CSRF_TOKEN,
					success: function() {
						window.location = url;
					},
					statusCode: {
						404: function() {
							var msg = "' . I18N::translate('This individual does not exist or you do not have permission to view it.') . '";
							jQuery("#error").text(msg).addClass("ui-state-error").show();
							setTimeout(function() {
								jQuery("#error").fadeOut("slow");
							}, 3000);
							jQuery("form #new_rootid")
								.val("")
								.focus();
						}
					}
				});
			});
		');
	}

	/**
	 * Use javascript to include the stylesheet(s) in the header
	 *
	 * @param type $css
	 * @return javascript
	 */
	protected function includeCss($css) {
		return
			'<script class="fancy-treeview-script">
				var newSheet=document.createElement("link");
				newSheet.setAttribute("href","' . $css . '");
				newSheet.setAttribute("type","text/css");
				newSheet.setAttribute("rel","stylesheet");
				newSheet.setAttribute("media","all");
				document.getElementsByTagName("head")[0].appendChild(newSheet);
			</script>';
	}

}
