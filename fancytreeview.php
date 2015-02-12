<?php
namespace Fisharebest\Webtrees;

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

use PDO;

/**
 * Class FancyTreeView
 */
class FancyTreeView extends fancy_treeview_WT_Module {

	// Get module options
	protected function options($value = '') {
		global $WT_TREE;
		$FTV_OPTIONS = unserialize($this->getSetting('FTV_OPTIONS'));

		$key = $WT_TREE->getIdFromName(Filter::get('ged'));
		if (empty($key)) {
			$key = WT_GED_ID;
		}

		if (empty($FTV_OPTIONS) || (is_array($FTV_OPTIONS) && !array_key_exists($key, $FTV_OPTIONS))) {
			$FTV_OPTIONS[0] = array(
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
				'FTV_TAB'				 => '0',
			);
			$key = 0;
		}

		// country could be disabled and thus not set
		if ($value == 'country' && !array_key_exists(strtoupper($value), $FTV_OPTIONS[$key])) {
			return '';
		} elseif ($value) {
			return($FTV_OPTIONS[$key][strtoupper($value)]);
		} else {
			return $FTV_OPTIONS[$key];
		}
	}

	// Get Indis from surname input - see: WT\Controller\Branches.php - loadIndividuals
	protected function indisArray($surname, $russell, $daitchMokotoff) {
		$sql = "SELECT DISTINCT i_id AS xref, i_file AS gedcom_id, i_gedcom AS gedcom" .
			" FROM `##individuals`" .
			" JOIN `##name` ON (i_id = n_id AND i_file = n_file)" .
			" WHERE n_file = :ged_id" .
			" AND n_type != '_MARNM'" .
			" AND (n_surn = :surname1 OR n_surname = :surname2";
		$args = array(
			'ged_id'	 => WT_GED_ID,
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
			$data[] = Individual::getInstance($row->xref, $row->gedcom_id, $row->gedcom);
		}
		return $data;
	}

	// Get surname from pid
	protected function getSurname($pid) {
		$sql = "SELECT n_surname AS surname FROM `##name` WHERE n_file = :ged_id AND n_id = :pid AND n_type = 'NAME'";
		$args = array(
			'ged_id' => WT_GED_ID,
			'pid'	 => $pid
		);
		$data = Database::prepare($sql)->execute($args)->fetchOne();
		return $data;
	}

	// Search within a multiple dimensional array
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

	// Sort the array according to the $key['SORT'] input.
	protected function sortArray($array, $sort_by) {

		$array_keys = array('tree', 'surname', 'display_name', 'pid', 'access_level', 'sort');

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

	protected function getPageLink($pid) {
		global $WT_TREE;
		$link = '<a href="module.php?mod=' . $this->getName() . '&amp;mod_action=page&amp;ged=' . $WT_TREE->getNameHtml() . '&amp;rootid=' . $pid . '" target="_blank">';

		if ($this->options('use_fullname') == true) {
			$link .= I18N::translate('Descendants of %s', Individual::getInstance($pid)->getFullName());
		} else {
			$link .= I18N::translate('Descendants of the %s family', $this->getSurname($pid));
		}

		$link .= '</a>';

		return $link;
	}

	protected function getCountryList() {
		$list = '';
		$countries = Database::prepare("SELECT SQL_CACHE p_place as country FROM `##places` WHERE p_parent_id=? AND p_file=?")
				->execute(array('0', WT_GED_ID))->fetchAll(PDO::FETCH_ASSOC);

		foreach ($countries as $country) {
			$list[$country['country']] = $country['country']; // set the country as key to display as option value.
		}
		return $list;
	}

	// Radio buttons
	protected function radioButtons($name, $selected) {
		$values = array(
			0	 => I18N::translate('no'),
			1	 => I18N::translate('yes'),
		);

		return radio_buttons($name, $values, $selected, 'class="radio-inline"');
	}

	// Print functions
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

	// Print functions
	protected function printTabContent($pid) {
		$html = '';
		$gen = 1;
		$root = $pid; // save value for read more link
		$generation = array($pid);
		$html .= $this->printTabContentGeneration($generation, $gen);

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
				if($gen === 3) {
					$html .= 
						'<div id="read-more-link"><a href="module.php?mod=' . $this->getName() .  '&amp;mod_action=page&rootid=' . $root . '">'. I18N::translate('Read more') . '</a></div>';
					return $html;
				} else {
					$gen++;
					$html .= $this->printTabContentGeneration($generation, $gen);
					unset($next_gen, $descendants, $pids);
				}
			} else {
				return $html;
			}
		}
		return $html;
	}

	private function printGeneration($generation, $i) {

		// added data attributes to retrieve values easily with jquery (for scroll reference en next generations).
		$html = '<li class="block generation-block" data-gen="' . $i . '" data-pids="' . implode('|', $generation) . '">
					<div class="blockheader ui-state-default"><span class="header-title">' . I18N::translate('Generation') . ' ' . $i . '</span>';
		if ($i > 1) {
			$html .= '<a href="#fancy_treeview-page" class="header-link scroll">' . I18N::translate('back to top') . '</a>';
		}
		$html .= '	</div>';

		if ($this->checkPrivacy($generation, true)) {
			$html .= '<div class="blockcontent generation private">' . I18N::translate('The details of this generation are private.') . '</div>';
		} else {
			$html .= '<ol class="blockcontent generation">';
			$generation = array_unique($generation); // needed to prevent the same family added twice to the generation block (this is the case when parents have the same ancestors and are both members of the previous generation).

			foreach ($generation as $pid) {
				$individual = $this->getIndividual($pid);

				// only list persons without parents in the same generation - if they have they will be listed in the next generation anyway.
				// This prevents double listings
				if (!$this->hasParentsInSameGeneration($individual, $generation)) {
					$family = $this->getFamily($individual);
					if (!empty($family)) {
						$id = $family->getXref();
					} else {
						if ($this->options('show_singles') == true || !$individual->getSpouseFamilies()) {
							$id = 'S' . $pid;
						} // Added prefix (S = Single) to prevent double id's.
					}
					$class = $individual->canShow() ? 'family' : 'family private';
					$html .= '<li id="' . $id . '" class="' . $class . '">' . $this->printIndividual($individual) . '</li>';
				}
			}
			$html .= '</ol></li>';
		}
		return $html;
	}
	
	private function printTabContentGeneration($generation, $i) {

		// added data attributes to retrieve values easily with jquery (for scroll reference en next generations).
		$html = '<li class="generation-block" data-gen="' . $i . '" data-pids="' . implode('|', $generation) . '">
					<div class="blockheader">
						<span class="header-title">' . I18N::translate('Generation') . ' ' . $i . '</span>
					</div>';
		
		if ($this->checkPrivacy($generation, true)) {
			$html .= '<div class="generation private">' . I18N::translate('The details of this generation are private.') . '</div>';
		} else {
			$html .= '<ol class="generation">';
			$generation = array_unique($generation); // needed to prevent the same family added twice to the generation block (this is the case when parents have the same ancestors and are both members of the previous generation).

			foreach ($generation as $pid) {
				$individual = $this->getIndividual($pid);

				// only list persons without parents in the same generation - if they have they will be listed in the next generation anyway.
				// This prevents double listings
				if (!$this->hasParentsInSameGeneration($individual, $generation)) {
					$family = $this->getFamily($individual);
					if (!empty($family)) {
						$id = $family->getXref();
					} else {
						if ($this->options('show_singles') == true || !$individual->getSpouseFamilies()) {
							$id = 'S' . $pid;
						} // Added prefix (S = Single) to prevent double id's.
					}
					$class = $individual->canShow() ? 'family' : 'family private';
					$html .= '<li id="' . $id . '" class="' . $class . '">' . $this->printIndividual($individual) . '</li>';
				}
			}
			$html .= '</ol></li>';
		}
		return $html;
	}

	private function printIndividual($individual) {

		if ($individual->CanShow()) {
			$resize = $this->options('resize_thumbs') == 1 ? true : false;
			$html = '<div class="parents">' . $this->printThumbnail($individual, $this->options('thumb_size'), $this->options('thumb_resize_format'), $this->options('use_square_thumbs'), $resize) . '<a id="' . $individual->getXref() . '" href="' . $individual->getHtmlUrl() . '"><p class="desc">' . $individual->getFullName() . '</a>';
			if ($this->options('show_occu') == true) {
				$html .= $this->printFact($individual, 'OCCU');
			}

			$html .= $this->printParents($individual) . $this->printLifespan($individual);

			// get a list of all the spouses
			/*
			 * First, determine the true number of spouses by checking the family gedcom
			 */
			$spousecount = 0;
			foreach ($individual->getSpouseFamilies(WT_PRIV_HIDE) as $i => $family) {
				$spouse = $family->getSpouse($individual);
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
				foreach ($individual->getSpouseFamilies(WT_PRIV_HIDE) as $i => $family) {
					$spouse = $family->getSpouse($individual);
					if ($spouse && $spouse->canShow() && $this->getMarriage($family)) {
						$html .= $this->printSpouse($family, $individual, $spouse, $spouseindex, $spousecount);
						$spouseindex++;
					}
				}
			}

			$html .= '</p></div>';

			// get children for each couple (could be none or just one, $spouse could be empty, includes children of non-married couples)
			foreach ($individual->getSpouseFamilies(WT_PRIV_HIDE) as $family) {
				$spouse = $family->getSpouse($individual);
				$html .= $this->printChildren($family, $individual, $spouse);
			}

			return $html;
		} else {
			if ($individual->getTree()->getPreference('SHOW_PRIVATE_RELATIONSHIPS')) {
				return I18N::translate('The details of this family are private.');
			}
		}
	}

	private function printSpouse($family, $individual, $spouse, $i, $count) {

		$html = ' ';

		if ($count > 1) {
			// we assume no one married more then five times.
			$wordcount = array(
				I18N::translate('first'),
				I18N::translate('second'),
				I18N::translate('third'),
				I18N::translate('fourth'),
				I18N::translate('fifth')
			);
			switch ($individual->getSex()) {
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
			switch ($individual->getSex()) {
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

		$html .= ' <a href="' . $spouse->getHtmlUrl() . '">' . $spouse->getFullName() . '</a>';

		// Add relationship note
		if ($this->options('check_relationship')) {
			$relationship = $this->checkRelationship($individual, $spouse, $family);
			if ($relationship) {
				$html .= ' (' . $relationship . ')';
			}
		}

		$html .= $this->printParents($spouse);

		if (!$family->getMarriage()) { // use the default privatized function to determine if marriage details can be shown.
			$html .= '.';
		} else {
			// use the facts below only on none private records.
			if ($this->printParents($spouse)) {
				$html .= ',';
			}
			$marrdate = $family->getMarriageDate();
			$marrplace = $family->getMarriagePlace();
			if ($marrdate && $marrdate->isOK()) {
				$html .= $this->printDate($marrdate);
			}
			if ($marrplace->getGedcomName()) {
				$html .= $this->printPlace($marrplace->getGedcomName(), $family->getTree());
			}
			$html .= $this->printLifespan($spouse, true);

			$div = $family->getFirstFact('DIV');
			if ($div) {
				$html .= $individual->getFullName() . ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $spouse->getFullName() . ' ' . I18N::translate('were divorced') . $this->printDivorceDate($div) . '.';
			}
		}
		return $html;
	}

	private function printChildren($family, $individual, $spouse) {
		$html = '';

		$match = null;
		if (preg_match('/\n1 NCHI (\d+)/', $family->getGedcom(), $match) && $match[1] == 0) {
			$html .= '<div class="children"><p>' . $individual->getFullName() . ' ';
			if ($spouse && $spouse->CanShow()) {
				$html .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $spouse->getFullName() . ' ';
				$html .= I18N::translate_c('Two parents/one child', 'had');
			} else {
				$html .= I18N::translate_c('One parent/one child', 'had');
			}
			$html .= ' ' . I18N::translate('none') . ' ' . I18N::translate('children') . '.</p></div>';
		} else {
			$children = $family->getChildren();
			if ($children) {
				if ($this->checkPrivacy($children)) {
					$html .= '<div class="children"><p>' . $individual->getFullName() . ' ';
					// needs multiple translations for the word 'had' to serve different languages.
					if ($spouse && $spouse->CanShow()) {
						$html .= /* I18N: Note the space at the end of the string */ I18N::translate('and ') . $spouse->getFullName() . ' ';
						if (count($children) > 1) {
							$html .= I18N::translate_c('Two parents/multiple children', 'had');
						} else {
							$html .= I18N::translate_c('Two parents/one child', 'had');
						}
					} else {
						if (count($children) > 1) {
							$html .= I18N::translate_c('One parent/multiple children', 'had');
						} else {
							$html .= I18N::translate_c('One parent/one child', 'had');
						}
					}
					$html .= ' ' . /* I18N: %s is a number */ I18N::plural('%s child', '%s children', count($children), count($children)) . '.</p></div>';
				} else {
					$html .= '<div class="children"><p>' . I18N::translate('Children of ') . $individual->getFullName();
					if ($spouse && $spouse->CanShow()) {
						$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
						if (!$family->getMarriage()) {
							// check relationship first (If a relationship is found the information of this parent is printed elsewhere on the page.)
							if ($this->options('check_relationship')) {
								$relationship = $this->checkRelationship($individual, $spouse, $family);
							}
							if (isset($relationship) && $relationship) {
								$html .= $spouse->getFullName() . ' (' . $relationship . ')';
							} else {
								// the non-married spouse is not mentioned in the parents div text or elsewhere on the page. So put a link behind the name.
								$html .= '<a class="tooltip" title="" href="' . $spouse->getHtmlUrl() . '">' . $spouse->getFullName() . '</a>';
								// Print info of the non-married spouse in a tooltip
								$html .= '<span class="tooltip-text">' . $this->printTooltip($spouse) . '</span>';
							}
						} else {
							$html .= $spouse->getFullName();
						}
					}
					$html .= ':<ol>';

					foreach ($children as $child) {
						$html .= '<li class="child"><a href="' . $child->getHtmlUrl() . '">' . $child->getFullName() . '</a>';
						$pedi = $this->checkPedi($child, $family);

						if ($pedi) {
							$html .= ' <span class="pedi"> - ';
							switch ($pedi) {
								case 'foster':
									switch ($child->getSex()) {
										case 'F':
											$html .= I18N::translate_c('FEMALE', 'foster child');
											break;
										default:
											$html .= I18N::translate_c('MALE', 'foster child');
											break;
									}
									break;
								case 'adopted':
									switch ($child->getSex()) {
										case 'F':
											$html .= I18N::translate_c('FEMALE', 'adopted child');
											break;
										default:
											$html .= I18N::translate_c('MALE', 'adopted child');
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
						if (WT_SCRIPT_NAME !== 'inidividual.php') {
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

	private function printParents($individual) {
		$parents = $individual->getPrimaryChildFamily();
		if ($parents) {
			$pedi = $this->checkPedi($individual, $parents);

			$html = '';
			switch ($individual->getSex()) {
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
				$html .= $father->getFullName();
			}
			if ($father && $mother) {
				$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
			}
			if ($mother) {
				$html .= $mother->getFullName();
			}

			return $html;
		}
	}

	private function printLifespan($individual, $is_spouse = false) {
		$html = '';
		$birthdate = $individual->getBirthDate();
		$deathdate = $individual->getDeathdate();
		$ageOfdeath = get_age_at_event(Date::GetAgeGedcom($birthdate, $deathdate), false);

		$birthdata = false;
		if ($birthdate->isOK() || $individual->getBirthPlace() != '') {
			$birthdata = true;
			if ($is_spouse == true) {
				$html .= '. ';
				if ($individual->isDead()) {
					$individual->getSex() == 'F' ? $html .= I18N::translate_c('PAST', 'She was born') : $html .= I18N::translate_c('PAST', 'He was born');
				} else {
					$individual->getSex() == 'F' ? $html .= I18N::translate_c('PRESENT', 'She was born') : $html .= I18N::translate_c('PRESENT', 'He was born');
				}
			} else {
				$this->printParents($individual) || $this->printFact($individual, 'OCCU') ? $html .= ', ' : $html .= ' ';
				if ($individual->isDead()) {
					$individual->getSex() == 'F' ? $html .= I18N::translate_c('PAST (FEMALE)', 'was born') : $html .= I18N::translate_c('PAST (MALE)', 'was born');
				} else {
					$individual->getSex() == 'F' ? $html .= I18N::translate_c('PRESENT (FEMALE)', 'was born') : $html .= I18N::translate_c('PRESENT (MALE)', 'was born');
				}
			}
			if ($birthdate->isOK()) {
				$html .= $this->printDate($birthdate);
			}
			if ($individual->getBirthPlace() != '') {
				$html .= $this->printPlace($individual->getBirthPlace(), $individual->getTree());
			}
		}

		$deathdata = false;
		if ($deathdate->isOK() || $individual->getDeathPlace() != '') {
			$deathdata = true;

			if ($birthdata) {
				$html .= ' ' . /* I18N: Note the space at the end of the string */ I18N::translate('and ');
				$individual->getSex() == 'F' ? $html .= I18N::translate_c('FEMALE', 'died') : $html .= I18N::translate_c('MALE', 'died');
			} else {
				$individual->getSex() == 'F' ? $html .= '. ' . I18N::translate('She died') : $html .= '. ' . I18N::translate('He died');
			}

			if ($deathdate->isOK()) {
				$html .= $this->printDate($deathdate);
			}
			if ($individual->getDeathPlace() != '') {
				$html .= $this->printPlace($individual->getDeathPlace(), $individual->getTree());
			}

			if ($birthdate->isOK() && $deathdate->isOK()) {
				if (Date::getAge($birthdate, $deathdate, 0) < 2) {
					$html .= ' ' . /* I18N: %s is the age of death in days/months; %s is a string, e.g. at the age of 2 months */ I18N::translate_c('age in days/months', 'at the age of %s', $ageOfdeath);
				} else {
					$html .= ' ' . /* I18N: %s is the age of death in years; %s is a number, e.g. at the age of 40 */ I18N::translate_c('age in years', 'at the age of %s', $ageOfdeath);
				}
			}
		}

		if ($birthdata || $deathdata) {
			$html .= '. ';
		}

		return $html;
	}

	// some couples are known as not married but have children together. Print the info of the "spouse" parent in a tooltip.
	private function printTooltip($individual) {
		$birthdate = $individual->getBirthDate();
		$deathdate = $individual->getDeathdate();
		$html = '';
		if ($birthdate->isOK()) {
			$html .= '<strong>' . I18N::translate('Birth') . ':</strong> ' . strip_tags($birthdate->Display());
		}
		if ($deathdate->isOK()) {
			$html .= '<br><strong>' . I18N::translate('Death') . ':</strong> ' . strip_tags($deathdate->Display());
		}

		$parents = $individual->getPrimaryChildFamily();
		if ($parents) {
			$father = $parents->getHusband();
			$mother = $parents->getWife();
			if ($father) {
				$html .= '<br><strong>' . I18N::translate('Father') . ':</strong> ' . strip_tags($father->getFullName());
			}
			if ($mother) {
				$html .= '<br><strong>' . I18N::translate('Mother') . ':</strong> ' . strip_tags($mother->getFullName());
			}
		}
		return $html;
	}

	private function printThumbnail($individual, $thumbsize, $resize_format, $square, $resize) {
		$mediaobject = $individual->findHighlightedMedia();
		if ($mediaobject) {
			$html = '';
			if ($resize == true) {
				$mediasrc = $resize_format == 1 ? $mediaobject->getServerFilename('thumb') : $mediaobject->getServerFilename('main');
				$thumbwidth = $thumbsize; $thumbheight = $thumbsize;
				$mediatitle = strip_tags($individual->getFullName());

				$type = $mediaobject->mimeType();
				if ($type == 'image/jpeg' || $type == 'image/png') {

					if (!list($width_orig, $height_orig) = @getimagesize($mediasrc)) {
						return null;
					}

					switch ($type) {
						case 'image/jpeg':
							$image = @imagecreatefromjpeg($mediasrc);
							break;
						case 'image/png':
							$image = @imagecreatefrompng($mediasrc);
							break;
					}

					// fallback if image is in the database but not on the server
					if (isset($width_orig) && isset($height_orig)) {
						$ratio_orig = $width_orig / $height_orig;
					} else {
						$ratio_orig = 1;
					}

					if ($resize_format == 1) {
						$thumbwidth = $thumbwidth / 100 * $width_orig;
						$thumbheight = $thumbheight / 100 * $height_orig;
					}

					if ($square == true) {
						$thumbheight = $thumbwidth;
						if ($ratio_orig < 1) {
							$new_height = $thumbwidth / $ratio_orig;
							$new_width = $thumbwidth;
						} else {
							$new_width = $thumbheight * $ratio_orig;
							$new_height = $thumbheight;
						}
					} else {
						if ($resize_format == 1) {
							$new_width = $thumbwidth;
							$new_height = $thumbheight;
						} elseif ($width_orig > $height_orig) {
							$new_height = $thumbheight / $ratio_orig;
							$new_width = $thumbwidth;
						} elseif ($height_orig > $width_orig) {
							$new_width = $thumbheight * $ratio_orig;
							$new_height = $thumbheight;
						} else {
							$new_width = $thumbwidth;
							$new_height = $thumbheight;
						}
					}
					$process = @imagecreatetruecolor(round($new_width), round($new_height));
					if ($type == 'image/png') { // keep transparancy for png files.
						imagealphablending($process, false);
						imagesavealpha($process, true);
					}
					@imagecopyresampled($process, $image, 0, 0, 0, 0, $new_width, $new_height, $width_orig, $height_orig);

					$thumb = $square == true ? imagecreatetruecolor($thumbwidth, $thumbheight) : imagecreatetruecolor($new_width, $new_height);
					if ($type == 'image/png') {
						imagealphablending($thumb, false);
						imagesavealpha($thumb, true);
					}
					@imagecopyresampled($thumb, $process, 0, 0, 0, 0, $thumbwidth, $thumbheight, $thumbwidth, $thumbheight);

					@imagedestroy($process);
					@imagedestroy($image);

					$width = $square == true ? round($thumbwidth) : round($new_width);
					$height = $square == true ? round($thumbheight) : round($new_height);
					ob_start(); $type = 'image/png' ? imagepng($thumb, null, 9) : imagejpeg($thumb, null, 100); $newThumb = ob_get_clean();
					$html = '<a' .
						' class="' . 'gallery' . '"' .
						' href="' . $mediaobject->getHtmlUrlDirect('main') . '"' .
						' type="' . $mediaobject->mimeType() . '"' .
						' data-obje-url="' . $mediaobject->getHtmlUrl() . '"' .
						' data-obje-note="' . htmlspecialchars($mediaobject->getNote()) . '"' .
						' data-obje-xref="' . $mediaobject->getXref() . '"' .
						' data-title="' . Filter::escapeHtml($mediaobject->getFullName()) . '"' .
						'><img class="ftv-thumb" src="data:' . $mediaobject->mimeType() . ';base64,' . base64_encode($newThumb) . '" dir="auto" title="' . $mediatitle . '" alt="' . $mediatitle . '" width="' . $width . '" height="' . $height . '"/></a>'; // need size to fetch it with jquery (for pdf conversion)
				}
			} else {
				$html = $mediaobject->displayImage();
			}
			return $html;
		}
	}

	private function printDate($date) {
		if ($date->qual1 || $date->qual2) {
			return ' ' . /* I18N: Date prefix for date qualifications, like estimated, about, calculated, from, between etc. Leave the string empty if your language don't need such a prefix. If you do need this prefix, add an extra space at the end of the string to separate the prefix from the date. It is correct the source text is empty, because the source language (en-US) does not need this string. */ I18N::translate_c('prefix before dates with date qualifications, followed right after the words birth, death, married, divorced etc. Read the comment for more details.', ' ') . $date->Display();
		}
		if ($date->MinDate()->d > 0) {
			return ' ' . /* I18N: Note the space at the end of the string */ I18N::translate_c('before dateformat dd-mm-yyyy', 'on ') . $date->Display();
		}
		if ($date->MinDate()->m > 0) {
			return ' ' . /* I18N: Note the space at the end of the string */ I18N::translate_c('before dateformat mmm yyyy', 'in ') . $date->Display();
		}
		if ($date->MinDate()->y > 0) {
			return ' ' . /* I18N: Note the space at the end of the string */ I18N::translate_c('before dateformat yyyy', 'in ') . $date->Display();
		}
	}

	private function printDivorceDate($div) {
		// Only display if it has a date
		if ($div->getDate()->isOK() && $div->canShow()) {
			return $this->printDate($div->getDate());
		}
	}

	private function printFact($individual, $tag) {
		$facts = $individual->getFacts();
		foreach ($facts as $fact) {
			if ($fact->getTag() == $tag) {
				$html = ', ' . rtrim($fact->getValue(), ".");
				return $html;
			}
		}
	}

	private function printPlace($place, $tree) {
		if ($this->options('show_places') == true) {
			$place = new Place($place, $tree);
			$html = ' ' . /* I18N: Note the space at the end of the string */ I18N::translate_c('before placesnames', 'in ');
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

	// Other functions
	protected function getIndividual($pid) {
		$individual = Individual::getInstance($pid);
		return $individual;
	}

	private function getFamily($individual) {
		foreach ($individual->getSpouseFamilies(WT_PRIV_HIDE) as $family) {
			return $family;
		}
	}

	private function getNextGen($pid) {
		$individual = $this->getIndividual($pid);
		foreach ($individual->getSpouseFamilies() as $family) {
			$children = $family->getChildren();
			if ($children) {
				foreach ($children as $key => $child) {
					$key = $family->getXref() . '-' . $key; // be sure the key is unique.
					$ng[$key]['pid'] = $child->getXref();
					$child->getSpouseFamilies(WT_PRIV_HIDE) ? $ng[$key]['desc'] = 1 : $ng[$key]['desc'] = 0;
				}
			}
		}
		if (isset($ng)) {
			return $ng;
		}
	}

	// check if a person has parents in the same generation
	private function hasParentsInSameGeneration($individual, $generation) {
		$parents = $individual->getPrimaryChildFamily();
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

	// check (blood) relationship between partners
	private function checkRelationship($individual, $spouse, $family) {
		$count = count($family->getChildren());
		for ($i = 0; $i <= $count; $i++) { // the number of paths is equal to the number of children, because every relationship is checked through each child.
			// and we need the relationship from the next path.
			$nodes = get_relationship($individual, $spouse, false, 0, $i);

			if (!is_array($nodes)) {
				return '';
			}

			$path = array_slice($nodes['relations'], 1);

			$combined_path = '';
			$display = false;
			foreach ($path as $key => $rel) {
				$rel_to_exclude = array('son', 'daughter', 'child'); // don't return the relationship path through the children
				if ($key == 0 && in_array($rel, $rel_to_exclude)) {
					$display = false;
					break;
				}
				$rel_to_find = array('sister', 'brother', 'sibling'); // one of these relationships must be in the path
				if (in_array($rel, $rel_to_find)) {
					$display = true;
					break;
				}
			}

			if ($display == true) {
				foreach ($path as $rel) {
					$combined_path.=substr($rel, 0, 3);
				}
				return get_relationship_name_from_path($combined_path, $individual, $spouse);
			}
		}
	}

	private function checkPrivacy($record, $xrefs = false) {
		$count = 0;
		foreach ($record as $individual) {
			if ($xrefs) {
				$individual = $this->getIndividual($individual);
			}
			if ($individual->CanShow()) {
				$count++;
			}
		}
		if ($count < 1) {
			return true;
		}
	}

	// Determine if the family parents are married. Don't use the default function because we want to privatize the record but display the name and the parents of the spouse if the spouse him/herself is not private.
	private function getMarriage($family) {
		$record = GedcomRecord::getInstance($family->getXref());
		foreach ($record->getFacts('MARR', false, WT_PRIV_HIDE) as $fact) {
			if ($fact) {
				return true;
			}
		}
	}

	// Check if this person is an adopted or foster child
	private function checkPedi($individual, $parents) {
		$pedi = "";
		foreach ($individual->getFacts('FAMC') as $fact) {
			if ($fact->getTarget() === $parents) {
				$pedi = $fact->getAttribute('PEDI');
				break;
			}
		}
		return $pedi;
	}

	protected function getStylesheet() {
		$theme_dir = $this->module . '/themes/';
		$stylesheet = '';
		
		if (Theme::theme()->themeId() !== '_administration') {
			$stylesheet .= $this->includeCss($theme_dir . 'base/style.css');
		}
		
		if (file_exists($theme_dir . Theme::theme()->themeId() . '/style.css')) {
			$stylesheet .= $this->includeCss($theme_dir . Theme::theme()->themeId() . '/style.css');
		}

		return $stylesheet;
	}

	protected function includeJs($controller, $page) {

		switch ($page) {
		case 'admin':
			$controller->addInlineJavascript('
				var ModuleDir			= "' . $this->module . '";
				var ModuleName			= "' . $this->getName() . '";
				var ThemeID				= "' . Theme::theme()->themeId() . '";
			', BaseController::JS_PRIORITY_HIGH);
			$controller
				->addExternalJavascript(WT_AUTOCOMPLETE_JS_URL)
				->addInlineJavascript('autocomplete();')
				->addExternalJavascript($this->module . '/js/admin.js');
			break;

		case 'menu':
			$controller->addInlineJavascript('
				var ModuleDir			= "' . $this->module . '";
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
				var TextOk				= "' . I18N::translate('Ok') . '";
				var TextCancel			= "' . I18N::translate('Cancel') . '";
			', BaseController::JS_PRIORITY_HIGH)
				->addExternalJavascript(WT_AUTOCOMPLETE_JS_URL)
				->addInlineJavascript('autocomplete();')
				->addExternalJavascript($this->module . '/js/page.js');

			if ($this->options('show_pdf_icon') >= WT_USER_ACCESS_LEVEL && I18N::direction() === 'ltr') {
				$controller->addExternalJavascript($this->module . '/pdf/pdf.js');
			}

			// some files needs an extra js script
			if (file_exists(WT_STATIC_URL . $this->module . '/themes/' . Theme::theme()->themeId() . '/' . Theme::theme()->themeId() . '.js')) {
				$controller->addExternalJavascript($this->module . '/themes/' . Theme::theme()->themeId() . '/' . Theme::theme()->themeId() . '.js');
			}

			if ($this->options('show_userform') >= WT_USER_ACCESS_LEVEL) {
				$this->includeJsInline($controller);
			}
			break;
		}
	}

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

	protected function rootId() {
		return Filter::get('rootid', WT_REGEX_XREF);
	}

	protected function addMessage($id, $type, $hidden, $message = '') {
		$style = $hidden ? ' style="display:none"' : '';

		return
			'<div id="' . $id . '" class="alert alert-' . $type . ' alert-dismissible"' . $style . '>' .
			'<button type="button" class="close" aria-label="' . I18N::translate('close') . '">' .
			'<span aria-hidden="true">&times;</span>' .
			'</button>' .
			'<span class="message">' . $message . '</span>' .
			'</div>';
	}

}
