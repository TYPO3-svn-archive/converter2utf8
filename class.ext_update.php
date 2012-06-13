<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Ulfried Herrmann (Die Netzmacher) <http://herrmann.at.die-netzmacher.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * [CLASS/FUNCTION INDEX of SCRIPT]
 *
 * Hint: use extdeveval to insert/update function index above.
 */

/**
 * Class for updating TYPO3 database to UTF-8 charset
 *
 * @author  Ulfried Herrmann (Die Netzmacher) <http://herrmann.at.die-netzmacher.de>
 * @package TYPO3
 * @subpackage converter2utf8
 */
class ext_update {
	protected $extKey       = 'converter2utf8';                         //  The extension key.
	protected $extName      = 'UTF-8 Converter';                        //  The extension name.
	protected $extPath;                                                 //  absolute path to the extension
	protected $extRelPath;                                              //  relative path to the extension

	protected $extConf      = array();                                  //  extension configuration
	protected $templateDir  = 'res/template/';                          //  path to template directory
	protected $templateHTML = 'ext_update.tmpl.html';                   //  HTML template file
	protected $jQueryLib    = 'js/jquery-1.7.2.min.js';                 //  jQuery core
	protected $templateCSS  = 'css/ext_update.css';                     //  stylesheet
	protected $locallangXML = 'LLL:EXT:converter2utf8/locallang.xml';   //  locallang xml

	protected $template;                                                //  content of template file
	protected $tables       = array();                                  //  tables in db
	protected $backupPrefix = 'zzz_backup_converter2utf8_';             //  prefix for backuped tables
	protected $content;                                                 //  content output

	protected $textColumns  = array(                                    //  TCA columns to be handled by conversion
		'input' => array(
			'excludeEval' => 'date,datetime,time,timesec,year,int,num,md5,password,double2',
		),
		'text' => array(
			'excludeEval' => '',
		),
	);
	protected $typeConv = array(                                        //  temporary conversion of db columns
		'char' => 'binary',
		'text' => 'blob',
	);


	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return	string		HTML
	 */
	public function main() {
		$content = '';

		$this->getExtConf();

		if (empty ($_REQUEST['command'])) {
			$_GET['command'] = '';
		}
		switch ($_REQUEST['command']) {
			case 'exclude':
					//  set table in EXTCONF as excluded
				$this
					->getTableList()    //  check access
					->excludeTable();
				break;
			case 'backup':
					//  create table backup
				$this
					->getTableList()    //  check access
					->backupTable();
				break;
			case 'convert':
					//  convert table
				$this
					->getTableList()    //  check access
					->convertTable();
				break;
			case 'update-localconf':
					//  convert table
				$this->updateLocalconf();
				break;
			default:
					//  display table list
				$this
					->getPaths()
					->loadTemplate()
					->includeJQuery(TRUE)
					->getTableList()
					->displayTableList();
				$content .= $this->content;
				break;
		}

		return $content;
	}


	/**
	 * Get the extension configuration
	 *
	 * @return array
	 * @return obj     $this
	 * @since 0.1.0
	 */
	protected function getExtConf() {
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		if (!empty ($extConf)) {
			$this->extConf = $extConf;
		}

		return $this;
	}


	/**
	 * Checks how many rows are found and returns true if there are any
	 * (this function is called from the extension manager)
	 *
	 * @param	string		$what: what should be updated
	 * @return	boolean
	 */
	public function access($what = 'all') {
		return FALSE;
	}


	/*****************************************************************************/


	/**
	 * load template file
	 *
	 * @return obj     $this
	 * @access protected
	 * @since 0.1.0
	 */
	protected function getPaths() {
		$this->extPath    = t3lib_extmgm::extPath($this->extKey);
		$this->extRelPath = t3lib_extmgm::extRelPath($this->extKey);

		return $this;
	}


	/**
	 * load template file
	 *
	 * @return obj     $this
	 * @access protected
	 * @since 0.1.0
	 */
	protected function loadTemplate() {
		$templateFile = $this->extPath . $this->templateDir . $this->templateHTML;

		if (!file_exists($templateFile)) {
				##  @ToDo: change die() with displayMessage()
			$msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.noTemplate');
			die(sprintf($msg, $templateFile));
		} else {
			$this->template = file_get_contents($templateFile);
		}

		return $this;
	}


	/**
	 * includes jQuery Lib (and CSS via jQuery too)
	 *
	 * @param  bool    include CSS?
	 * @return obj     $this
	 * @access protected
	 * @since 0.1.0
	 */
	protected function includeJQuery($includeCSS = TRUE) {
		$markerArray  = array(
			'###PATH2TEMPLATE###'  => $this->extRelPath . $this->templateDir,
			'###JQUERYLIB###'      => $this->jQueryLib,
			'###CONVERTING###'     => $GLOBALS['LANG']->sL($this->locallangXML . ':process.converting'),
			'###TABLE###'          => $GLOBALS['LANG']->sL($this->locallangXML . ':head.table'),
			'###OF###'             => $GLOBALS['LANG']->sL($this->locallangXML . ':process.of'),
			'###BACKUP_DONE###'    => $GLOBALS['LANG']->sL($this->locallangXML . ':success.backupDone'),
		##	'###BACKUP_FAILED###'  => $GLOBALS['LANG']->sL($this->locallangXML . ':error.backupFailed'),
			'###CONVERT_DONE###'   => $GLOBALS['LANG']->sL($this->locallangXML . ':success.convertDone'),
			'###EXCLUDE_DONE###'   => $GLOBALS['LANG']->sL($this->locallangXML . ':success.excludeDone'),
		);

		$templateJQcore = t3lib_parsehtml::getSubpart($this->template, '###TEMPLATE_JQINCLUDECORE###');
		$this->content .= t3lib_parsehtml::substituteMarkerArray($templateJQcore, $markerArray);

		if ($includeCSS === TRUE) {
			$templateCSS = t3lib_parsehtml::getSubpart($this->template, '###TEMPLATE_JQINCLUDECSS###');
			$markerArray['###PATH2TEMPLATE###'] = strtr($markerArray['###PATH2TEMPLATE###'], array('/' => '\/'));
			$markerArray['###TEMPLATECSS###']   = strtr($this->templateCSS, array('/' => '\/'));
			$this->content .= t3lib_parsehtml::substituteMarkerArray($templateCSS, $markerArray);
		}

		return $this;
	}


	/**
	 * get tables list
	 *
	 * @return obj     $this
	 * @access protected
	 * @since 0.1.0
	 */
	protected function getTableList() {
		$tables = $GLOBALS['TYPO3_DB']->admin_get_tables();
		foreach ($tables as $tKey => $tVal) {
			if ($_REQUEST['command'] != 'prepare-restore') {
				if (!preg_match('/^' . $this->backupPrefix . '/', $tKey)) {
					$this->tables[] = $tKey;
				}
			} else {
				if (preg_match('/^' . $this->backupPrefix . '/', $tKey)) {
					$this->tables[] = $tKey;
				}
			}
		}
			//  save tables in session
		$sessionData['tables'] = $this->tables;
		$GLOBALS['BE_USER']->setAndSaveSessionData($this->extKey, $sessionData);

		return $this;
	}


	/**
	 * display tables list
	 *
	 * @return obj     $this
	 * @access protected
	 * @since 0.1.0
	 */
	protected function displayTableList() {
		$this->getExtConf();
		$_tablesExclude   = t3lib_div::trimExplode(',', $this->extConf['tablesExclude'], TRUE);
		$_tablesProcessed = t3lib_div::trimExplode(',', $this->extConf['tablesProcessed'], TRUE);

		$templateList     = t3lib_parsehtml::getSubpart($this->template, '###TEMPLATE_TABLELIST###');
		$markerArray      = array(
			'###HEAD_TABLE###'        => $GLOBALS['LANG']->sL($this->locallangXML . ':head.table'),
			'###HEAD_INFORMATION###'  => $GLOBALS['LANG']->sL($this->locallangXML . ':head.information'),
			'###HEAD_EXCLUDE###'      => $GLOBALS['LANG']->sL($this->locallangXML . ':head.exclude'),
			'###TOGGLE_EXCLUDE###'    => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_browse_links.xml:toggleSelection'),
			'###ACTION###'            => htmlspecialchars($_SERVER['REQUEST_URI']),
		###	'###ACTION###'            => '/typo3conf/ext/converter2utf8/test.php',
			'###UPDATERMSGHEADER###'  => $GLOBALS['LANG']->sL($this->locallangXML . ':process.updaterMsgHeader'),
			'###UPDATERMSG###'        => $GLOBALS['LANG']->sL($this->locallangXML . ':process.updaterMsg'),
			'###PROCESS_BACKUP###'    => $GLOBALS['LANG']->sL($this->locallangXML . ':process.backup'),
			'###PROCESS_CONVERT###'   => $GLOBALS['LANG']->sL($this->locallangXML . ':process.convert'),
			'###DISPLAY_CONVERT###'   => 'block',
			'###PROCESS_RESTORE###'   => $GLOBALS['LANG']->sL($this->locallangXML . ':process.restore'),
			'###DISPLAY_RESTORE###'   => 'none',
		);
		$templateList     = t3lib_parsehtml::substituteMarkerArray($templateList, $markerArray);
		$templateListItem = t3lib_parsehtml::getSubpart($templateList,   '###TEMPLATE_TABLELIST_ITEM###');

			//  loop tables
		$n = 0;
		$listItemContent  = '';
		foreach ($this->tables as $tKey => $tVal) {
			$n++;
			$skipTable = (in_array($tVal, $_tablesExclude)
							OR in_array($tVal, $_tablesProcessed)) ? TRUE : FALSE;
##	t3lib_div::devlog('skip table: ' . $tVal . ' :: ' . (int)$skipTable, 'utf8', 0);

				//  table information
			$markerArray = array(
				'###EVENODD###'     => ($n % 2 == 0) ? 'x-grid3-row-alt' : ' x-grid3-row',
				'###TABLENAME###'   => $tVal,
				'###TABLEINFO###'   => $tVal,
				'###TABLECLASS###'  => '',
				'###INFORMATION###' => array(),
				'###CHECKED###'     => ($skipTable === FALSE) ? '' : 'checked="checked"',
			);

				//  is there any TCA config for this table?
			$process = TRUE;
			$hasTCA  = $this->checkTCA($tVal);
			if ($hasTCA === FALSE) {
				$process = FALSE;
				$markerArray['###TABLECLASS###']    = 'table-skipped';
				$markerArray['###INFORMATION###'][] = $GLOBALS['LANG']->sL($this->locallangXML . ':noTCA');
			} else {
					//  has this table content?
				$numRows = $this->countRows($tVal);
				if (empty ($numRows)) {
					$process = FALSE;
					$markerArray['###TABLECLASS###']    = 'table-skipped';
					$markerArray['###INFORMATION###'][] = $GLOBALS['LANG']->sL($this->locallangXML . ':noContent');
				} else {
						//  display table as processable
					$markerArray['###TABLECLASS###']    = 'table-process';
					$markerArray['###TABLECLASS###']   .= ($skipTable === FALSE) ? '' : ' table-excluded';
					$msg = $GLOBALS['LANG']->sL($this->locallangXML . ':hasContent');
					$markerArray['###INFORMATION###'][] = sprintf($msg, $numRows);
				}
				$markerArray['###TABLEINFO###'] = '<img src="' . $hasTCA['iconfile'] . '" />'
														. '<span>' . $GLOBALS['LANG']->sL($hasTCA['title']) . '</span><br />'
														. $markerArray['###TABLENAME###'];
			}
			$markerArray['###INFORMATION###'] = implode('<br />', $markerArray['###INFORMATION###']);

			$itemContent = t3lib_parsehtml::substituteMarkerArray($templateListItem, $markerArray);
				//  hide 'exclude' checkbox
			if ($process === FALSE) {
				$itemContent = t3lib_parsehtml::substituteSubpart($itemContent, '###TEMPLATE_TABLEEXCLUDE_ITEM###', '');
			}
			$listItemContent .= $itemContent;
		}

		$this->content .= t3lib_parsehtml::substituteSubpart($templateList, '###TEMPLATE_TABLELIST_ITEM###', $listItemContent);

		return $this;
	}


	/**
	 * checks TCA of current table
	 *
	 * @param string   table
	 *
	 * @return bool
	 * @access protected
	 * @since 0.1.0
	 */
	protected function checkTCA($table) {
		$hasTCA = FALSE;
		$typeIcons  = array(
			'be_groups'  => 'user-group-backend.png',
			'be_users'   => 'user-backend.png',
			'fe_groups'  => 'user-group-frontend.png',
			'fe_users'   => 'user-frontend.png',
		);

		t3lib_div::loadTCA($table);
		if (isset ($GLOBALS['TCA'][$table]['columns']) AND (is_array($GLOBALS['TCA'][$table]['columns']))) {
			$hasTCA = array(
				'title'    => $GLOBALS['TCA'][$table]['ctrl']['title'],
				'iconfile' => $GLOBALS['TCA'][$table]['ctrl']['iconfile'],
			);

				//  correct path if necessary: check icon in 'mimetypes'
			if (empty ($hasTCA['iconfile'])
					AND !empty ($GLOBALS['TCA'][$table]['ctrl']['typeicon_classes']['default'])) {
				$_tcd =& $GLOBALS['TCA'][$table]['ctrl']['typeicon_classes']['default'];
				if (preg_match('/^mimetypes\-/', $_tcd)) {
					$_icon = preg_replace('/^mimetypes\-/', '', $_tcd);
					$_path = 'sysext/t3skin/images/icons/mimetypes/';
					if (file_exists(realpath($_path . $_icon . '.gif'))) {
						$hasTCA['iconfile'] = $_path . $_icon . '.gif';
					} elseif (file_exists(realpath($_path . $_icon . '.png'))) {
						$hasTCA['iconfile'] = $_path . $_icon . '.png';
					}
				}
			}
				//  correct path if necessary: check icon in 'gfx/i'
			if (empty ($hasTCA['iconfile'])) {
				$_path = 'sysext/t3skin/icons/gfx/i/';
				if (file_exists(realpath($_path . $table . '.gif'))) {
					$hasTCA['iconfile'] = $_path . $table . '.gif';
				} elseif (array_key_exists($table, $typeIcons)) {
					$hasTCA['iconfile'] = 'sysext/t3skin/images/icons/status/' . $typeIcons[$table];
				}
			}
				//  correct path if necessary: no path to icon -> look in 'gfx/i'
			if (strpos($hasTCA['iconfile'], '/') === FALSE) {
				$hasTCA['iconfile'] = 'sysext/t3skin/icons/gfx/i/' . $hasTCA['iconfile'];
			}
		}

		return $hasTCA;
	}


	/**
	 * checks content of current table
	 *
	 * @param string   table
	 * @return integer / false
	 * @access protected
	 * @since 0.1.0
	 */
	protected function countRows($table) {
		$numRow = FALSE;

		$sql = 'SELECT COUNT(*) AS numrows
				FROM ' . $GLOBALS['TYPO3_DB']->quoteStr($table, $table);
		$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
		if (!$res) {
				##  @ToDo: change die() with displayMessage()
		    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbCountRows');
			$msg = sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error());
				//  log
			if (!empty ($this->extConf['enableDevlog'])) {
				t3lib_div::devLog($msg, $this->extKey, 3);
			}
			die($msg);
		}
		$ftc = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		$numRow = $ftc['numrows'];

		return $numRow;
	}


	/**
	 * create a table backup
	 *
	 * @return string     success message
	 * @access protected
	 * @since 0.1.0
	 */
	protected function backupTable() {
			//  check if table parameter is set and in session
		$this->checkTableAccess();

		$table  = $_REQUEST['table'];
		$table  = $GLOBALS['TYPO3_DB']->quoteStr($table, $table);
		$backup = $this->backupPrefix . $table;

			//  get create statement
		$sql = 'SHOW CREATE TABLE ' . $table;
		$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
		if (!$res) {
				##  @ToDo: change die() with displayMessage()
		    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbShowCreate');
			$msg = sprintf($msg, $sql/*$GLOBALS['TYPO3_DB']->sql_error()*/);
				//  log
			if (!empty ($this->extConf['enableDevlog'])) {
				t3lib_div::devLog($msg, $this->extKey, 3);
			}
			die($msg);
		}
		$ftc = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

			//  delete backup table
		$sql = 'DROP TABLE IF EXISTS ' . $backup;
		$GLOBALS['TYPO3_DB']->sql_query($sql);
			//  create backup table
		$sql = $ftc['Create Table'];
				//  replace table name (but not field names with same value)
		$firstLineOri = substr($sql, 0, (15 + strlen($table)));
		$firstLineNew = strtr($firstLineOri, array($table => $backup));
		$sql = strtr($sql, array($firstLineOri => $firstLineNew));
		$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
		if (!$res) {
				##  @ToDo: change die() with displayMessage()
		    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbCreateBackupTable');
			$msg = sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error());
				//  log
			if (!empty ($this->extConf['enableDevlog'])) {
				t3lib_div::devLog($msg, $this->extKey, 3);
			}
			die($msg);
		}

			//  copy data
		$sql = 'INSERT INTO ' . $backup . '
				SELECT * FROM ' . $table;
		$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
		if (!$res) {
				##  @ToDo: change die() with displayMessage()
		    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbInsertSelect');
			$msg = sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error());
				//  log
			if (!empty ($this->extConf['enableDevlog'])) {
				t3lib_div::devLog($msg, $this->extKey, 3);
			}
			die($msg);
		} else {
			echo 'ok';
			exit;
		}
	}


	/**
	 * convert table
	 *
	 * @return string     success message
	 * @access protected
	 * @since 0.1.0
	 */
	protected function convertTable() {
		$this
				//  check if table parameter is set and in session
			->checkTableAccess()
				//  mark table as processed
			->removeTableFromExtConf('tablesExclude')
			->addTableToExtConf('tablesProcessed');


		$table = $_REQUEST['table'];
		$table = $GLOBALS['TYPO3_DB']->quoteStr($table, $table);
			//  get table information from TCA
		t3lib_div::loadTCA($table);
		if (!isset ($GLOBALS['TCA'][$table]['columns']) OR (!is_array($GLOBALS['TCA'][$table]['columns']))) {
				//  log
			if (!empty ($this->extConf['enableDevlog'])) {
				$msg = 'Table %1$s requested to convert, but no TCA information';
				$msg = sprintf($msg, $_REQUEST['table']);
				t3lib_div::devLog($msg, $this->extKey, 2);
			}
		} else {
				//  collect fields with 'text' type
			$textColumns = array();
			foreach ($GLOBALS['TCA'][$table]['columns'] as $cKey => $cVal) {
					//  check for text columns
				if (!array_key_exists($cVal['config']['type'], $this->textColumns)) {
						//  skip
						//  log
					if (!empty ($this->extConf['enableDevlog'])) {
						$msg = 'Column %1$s skipped, no text column (%2$s)';
						$msg = sprintf($msg, $cKey, $cVal['config']['type']);
						t3lib_div::devLog($msg, $this->extKey, 1);
					}
				} else {
						//  check for non text evals
					$_convertThisColumn = TRUE;
					if (!empty ($cVal['config']['eval'])) {
						$_eval = t3lib_div::trimExplode(',', $cVal['config']['eval'], TRUE);
						foreach ($_eval as $eVal) {
							if (t3lib_div::inList($this->textColumns[$cVal['config']['type']]['excludeEval'], $eVal) ) {
								$_convertThisColumn = FALSE;
									//  log
								if (!empty ($this->extConf['enableDevlog'])) {
									$msg = 'Column %1$s skipped, found non text evaluation (%2$s)';
									$msg = sprintf($msg, $cKey, $eVal);
									t3lib_div::devLog($msg, $this->extKey, 1);
								}
							}
						}
					}
						//  collect remaining columns
					if ($_convertThisColumn === TRUE) {
						$textColumns[$cKey] = array();  //  used for conversion à la J. van Hemert
						$textFields[]       = $cKey;    //  used for conversion à la uherrmann
							//  log
						if (!empty ($this->extConf['enableDevlog'])) {
							$msg = 'Column %1$s found (type: „%2$s” / eval: „%2$3”)';
							$msg = sprintf($msg, $cKey, $cVal['config']['type'], $cVal['config']['eval']);
							t3lib_div::devLog($msg, $this->extKey, 0);
						}
					}
				}
			}
		}


##/*
			//  get table information from database
		foreach ($textColumns as $tKey => $_) {
		##	$sql = 'SHOW FULL COLUMNS FROM pages';
			$sql = 'SHOW COLUMNS FROM ' . $table;
			$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
			if (!$res) {
					##  @ToDo: change die() with displayMessage()
			    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbShowColumns');
				$msg = sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error());
					//  log
				if (!empty ($this->extConf['enableDevlog'])) {
					t3lib_div::devLog($msg, $this->extKey, 3);
				}
				die($msg);
			}
			while ($ftc = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				if (isset($textColumns[$ftc['Field']])) {
					$textColumns[$ftc['Field']] = $ftc;
				}
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
		}


			//  process each column
			//  @see db_utf8_fix.php@J. van Hemert
		foreach ($textColumns as $tcKey => $tcVal) {
			$oldtype = $tcVal['Type'];

				// modify type into a binary equivalent
			$tcVal['Type'] = str_replace(array_keys($this->typeConv), array_values($this->typeConv), $tcVal['Type']);
				// only do the magic if the type was modified
			if ($tcVal['Type'] != $oldtype) {
				$tcVal['Null'] = (strtolower($tcVal['Null']) == 'yes') ? 'NULL' : 'NOT NULL';
				if (is_numeric($tcVal['Default'])) {
					$tcVal['Default'] = $tcVal['Default'];
				} else {
					$tcVal['Default'] = ($tcVal['Default'] === 'NULL') ? $tcVal['Default'] : '\'' . $tcVal['Default'] . '\'';
				}
				$sql = 'ALTER TABLE ' . $table . ' MODIFY COLUMN ' . $tcVal['Field'] . ' ' . $tcVal['Type'] . ' ' . $tcVal['Null'];
					// only use default part if it's not a blob/text
				if (strpos($tcVal['Type'], 'blob') === FALSE) {
					$sql .= ' DEFAULT ' . $tcVal['Default'];
				}
				$sql .= ' ' . $tcVal['Extra'] . ';';
###	echo $sql . '<br />';
				$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
				if (!$res) {
						##  @ToDo: change die() with displayMessage()
				    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbAlterTable');
					$msg = sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error());
						//  log
					if (!empty ($this->extConf['enableDevlog'])) {
						t3lib_div::devLog($msg, $this->extKey, 3);
					}
					die($msg);
				} else {
						//  log
					if (!empty ($this->extConf['enableDevlog'])) {
						$num = $GLOBALS['TYPO3_DB']->sql_affected_rows();
						$msg = 'Column %1$s temporary converted to binary equivalent (%2$s rows affected)';
						$msg = sprintf($msg, $tcVal['Field'], $num);
						t3lib_div::devLog($msg, $this->extKey, 0);
					}
				}

					// modify type back to the non-binary equivalent, but add utf8 character set / collation setting
			##	$tcVal['Type'] = str_replace(array_values($this->typeCon), array_keys($this->typeCon), $tcVal['Type']);
				$sql = 'ALTER TABLE ' . $table . ' MODIFY COLUMN ' . $tcVal['Field'] . ' ' . $oldtype .
					   ' CHARACTER SET utf8 COLLATE utf8_general_ci ' . $tcVal['Null'];
				if (strpos($tcVal['Type'], 'text') === FALSE) {
					$sql .= ' DEFAULT ' . $tcVal['Default'];
				}
				$sql .= ' ' . $tcVal['Extra'] . ';';
###	echo $sql . '<br />';
				$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
				if (!$res) {
						##  @ToDo: change die() with displayMessage()
				    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbAlterTable');
					$msg = sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error());
						//  log
					if (!empty ($this->extConf['enableDevlog'])) {
						t3lib_div::devLog($msg, $this->extKey, 3);
					}
					die($msg);
				} else {
						//  log
					if (!empty ($this->extConf['enableDevlog'])) {
						$num = $GLOBALS['TYPO3_DB']->sql_affected_rows();
						$msg = 'Column %1$s successfully converted (%2$s rows affected)';
						$msg = sprintf($msg, $tcVal['Field'], $num);
						t3lib_div::devLog($msg, $this->extKey, -1);
					}
				}
			}
		}
##*/

/*
			//  read
		$GLOBALS['TYPO3_DB']->sql_query('SET NAMES ' . $this->extConf['charsetBefore']);
		$select_fields = 'uid,' . implode(',', $textFields);
		$from_table    = $table;
		$where_clause  = '';
		$groupBy       = '';
		$orderBy       = '';
		$limit         = '';
		$res  = $GLOBALS['TYPO3_DB']->exec_SELECTquery($select_fields, $from_table, $where_clause, $groupBy, $orderBy, $limit);
		$rows = array();
		while ($ftc = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
##echo '<pre><b>$ftc @ ' . __FILE__ . '::' . __LINE__ . ':</b> ' . print_r($ftc, 1) . '</pre>';
##exit;
			$rows[] = $ftc;
		}

			//  write
		$trans = array('â€ž' => '„', 'â€œ' => '”');
		$GLOBALS['TYPO3_DB']->sql_query('SET NAMES utf8');
	//	$table           = $table;
		$no_quote_fields = FALSE;
		foreach ($rows as $rVal) {
			$where         = 'uid = ' . (int)$rVal['uid'];
			unset($rVal['uid']);
			$fields_values = $rVal;
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery($table, $where, $fields_values, $no_quote_fields);
		}
*/

			//  renew table index
		$GLOBALS['TYPO3_DB']->sql_query('REPAIR TABLE ' . $table);


			//  log
		if (!empty ($this->extConf['enableDevlog'])) {
			$msg = 'Table %1$s converted successfully';
			$msg = sprintf($msg, $_REQUEST['table']);
			t3lib_div::devLog($msg, $this->extKey, -1);
		}

		echo 'ok-converted';
		exit;
	}


	/**
	 * mark table as excluded in localconf.php
	 *
	 * @return string     success message
	 * @access protected
	 * @since 0.1.0
	 */
	protected function excludeTable() {
		$this
				//  check if table parameter is set and in session
			->checkTableAccess()
				//  mark table as excluded
			->removeTableFromExtConf('tablesExclude')
			->addTableToExtConf('tablesExclude');
		echo 'ok-excluded';
		exit;
	}


	/**
	 * Prepare extension configuration array:
	 * add table item
	 *
	 * @param string   $arrayKey
	 * @return obj     $this
	 * @since 0.1.0
	 * @see EXT:caretaker_instance/class.ext_update.php
	 */
	protected function addTableToExtConf($arrayKey) {
		$extConf =& $this->extConf;
		if (!isset ($extConf[$arrayKey])) {
			$_tables = array();
		} else {
			$_tables = t3lib_div::trimExplode(',', $extConf[$arrayKey], TRUE);
		}
		$_tables[]          = $_REQUEST['table'];
		$extConf[$arrayKey] = implode(',', $_tables);
		$this->writeExtConf($extConf);
			//  log
		if (!empty ($extConf['enableDevlog'])) {
			$msg = 'Table %1$s added to extConf %2$s';
			$msg = sprintf($msg, $_REQUEST['table'], $arrayKey);
			t3lib_div::devLog($msg, $this->extKey, -1);
		}

		return $this;
	}


	/**
	 * Prepare extension configuration array:
	 * remove table item
	 *
	 * @param string   $arrayKey
	 * @return obj     $this
	 * @since 0.1.0
	 * @see EXT:caretaker_instance/class.ext_update.php
	 */
	protected function removeTableFromExtConf($arrayKey) {
		$extConf =& $this->extConf;
		if (!isset ($extConf[$arrayKey])) {
			$_tables = array();
		} else {
			$_tables = t3lib_div::trimExplode(',', $extConf[$arrayKey], TRUE);
			foreach ($_tables as $tKey => $tVal) {
				if ($tVal == $_REQUEST['table']) {
					unset($_tables[$tKey]);
				}
			}
		}
		$extConf[$arrayKey] = implode(',', $_tables);
		$this->writeExtConf($extConf);
			//  log
		if (!empty ($extConf['enableDevlog'])) {
			$msg = 'Table %1$s removed from extConf %2$s';
			$msg = sprintf($msg, $_REQUEST['table'], $arrayKey);
			t3lib_div::devLog($msg, $this->extKey, 0);
		}

		return $this;
	}


	/**
	 * Updates localconf.php
	 *
	 * @return void
	 * @since 0.1.0
	 */
	protected function updateLocalconf() {
		$install = new t3lib_install();
		$install->allowUpdateLocalConf = 1;
		$install->updateIdentity = $this->extName;

		$lines = $install->writeToLocalconf_control();
		$install->setValueInLocalconfFile($lines, '$TYPO3_CONF_VARS[\'SYS\'][\'setDBinit\']', 'SET NAMES utf8');
		$install->writeToLocalconf_control($lines);

	##	if ($removeCacheFiles === TRUE) {
			t3lib_extMgm::removeCacheFiles();
	##	}

			//  unlock adminOnly
		$sessionData = $GLOBALS['BE_USER']->getSessionData($this->extKey);
		if (!empty ($sessionData['setAdminOnly'])) {
			$install = new t3lib_install();
			$install->allowUpdateLocalConf = 1;
			$install->updateIdentity = $this->extName;

			$lines = $install->writeToLocalconf_control();
			$install->setValueInLocalconfFile($lines, '$TYPO3_CONF_VARS[\'BE\'][\'adminOnly\']', 1);
			$install->writeToLocalconf_control($lines);

				//  save setting in session
			$sessionData['setAdminOnly'] = '0';
			$GLOBALS['BE_USER']->setAndSaveSessionData($this->extKey, $sessionData);
		}



		echo 'ok';
		exit;
	}


	/**
	 * Write back extension configuration
	 *
	 * @param array    $extConf
	 * @return obj     $this
	 * @since 0.1.0
	 * @see EXT:caretaker_instance/class.ext_update.php
	 */
	protected function writeExtConf($extConf, $removeCacheFiles = FALSE) {
		$install = new t3lib_install();
		$install->allowUpdateLocalConf = 1;
		$install->updateIdentity = $this->extName;

		$lines = $install->writeToLocalconf_control();
		$install->setValueInLocalconfFile($lines, '$TYPO3_CONF_VARS[\'EXT\'][\'extConf\'][\'' . $this->extKey . '\']',
						serialize($extConf));
		$install->writeToLocalconf_control($lines);

		if ($removeCacheFiles === TRUE) {
			t3lib_extMgm::removeCacheFiles();
		}

		return $this;
	}


	/**
	 * check if table parameter is set and in session
	 *
	 * @param array    $extConf
	 * @return obj     $this
	 * @since 0.1.0
	 * @see EXT:caretaker_instance/class.ext_update.php
	 */
	protected function checkTableAccess() {
			//  no table parameter
		if (empty ($_REQUEST['table'])) {
			die($GLOBALS['LANG']->sL($this->locallangXML . ':error.noParameterTable'));
		}

			//  table not in session
		$sessionData = $GLOBALS['BE_USER']->getSessionData($this->extKey);
		if (!in_array($_REQUEST['table'], $sessionData['tables'])) {
			die($GLOBALS['LANG']->sL($this->locallangXML . ':error.invalidParameterTable'));
		}

			//  talbe converted yet
		if ($_REQUEST['command'] == 'convert' OR $_REQUEST['command'] == 'backup') {
			$_tablesProcessed = t3lib_div::trimExplode(',', $this->extConf['tablesProcessed'], TRUE);
			if (in_array($_REQUEST['table'], $_tablesProcessed)) {
				die($GLOBALS['LANG']->sL($this->locallangXML . ':error.tableAlreadyProcessed'));
			}
		}

		return $this;
	}


	/**
	 * Message in EM with link to updater Script
	 *
	 * @return string     HTML wrapped message
	 * @access protected
	 * @since 0.1.0
	 */
	function emDisplayStartConversionMessage(&$params, &$tsObj) {
		$content = '';

		$this
			->getExtConf()
			->getPaths()
			->loadTemplate();


			//  set Backend for admin only
		if (!empty ($_POST['data']['setAdminOnly'])) {
			$install = new t3lib_install();
			$install->allowUpdateLocalConf = 1;
			$install->updateIdentity = $this->extName;

			$lines = $install->writeToLocalconf_control();
			$install->setValueInLocalconfFile($lines, '$TYPO3_CONF_VARS[\'BE\'][\'adminOnly\']', 1);
			$install->writeToLocalconf_control($lines);

				//  save setting in session
			$sessionData['setAdminOnly'] = $_POST['data']['setAdminOnly'];
			$GLOBALS['BE_USER']->setAndSaveSessionData($this->extKey, $sessionData);
		}


			//  check: admin only setting in localconf
		if (empty ($GLOBALS['TYPO3_CONF_VARS']['BE']['adminOnly']) AND empty ($_POST['data']['setAdminOnly'])) {
			$type = 'error';
			$msg  = array(
				'header' => $GLOBALS['LANG']->sL($this->locallangXML . ':em.adminOnlyMessageHeader'),
				'body'   => $GLOBALS['LANG']->sL($this->locallangXML . ':em.adminOnlyMessageWarning'),
			);
		##	$style = 'position: absolute; top:90px; right:10px; width: 300px; z-index: 10000;';
			$style = '';
			$content .= $this->displayMessage($type, $msg, $style) . '
					<dd>' . $GLOBALS['LANG']->sL($this->locallangXML . ':em.adminOnlyMessageSolution') . '</dd>
					<dd>
						<div id="userTS-updateMessage" class="typo3-tstemplate-ceditor-row">
							<input type="hidden" name="data[setAdminOnly]" value="0" />
							<input type="checkbox" id="data[setAdminOnly]" name="data[setAdminOnly]" value="1" checked="checked" />
						</div>
					</dd>';
		}

			//  check: extConf is set
		$countExtConf = count($this->extConf);
		if ($countExtConf == 0 AND !isset ($_POST['data'])) {
			$type = 'warning';
			$msg  = array(
				'header' => $GLOBALS['LANG']->sL($this->locallangXML . ':em.updateMessageHeader'),
				'body'   => $GLOBALS['LANG']->sL($this->locallangXML . ':em.updateMessageWarning'),
			);
		##	$style = 'position: absolute; top:10px; right:10px; width: 300px; z-index: 10000;';
			$style = '';
			$content .= $this->displayMessage($type, $msg, $style);
		}


			//  link to updater script
		if (empty ($content)) { //  no errors
			$type = 'information';
		##	if (t3lib_div::int_from_ver(TYPO3_version) < 4005000) {
		##		$link = 'index.php?&amp;id=0&amp;CMD[showExt]=' . $this->extKey . '&amp;SET[singleDetails]=updateModule';
		##	} else {
				$link = 'mod.php?&amp;id=0&amp;M=tools_em&amp;CMD[showExt]=' . $this->extKey . '&amp;SET[singleDetails]=updateModule';
		##	}
			$msg  = array(
				'header' => $GLOBALS['LANG']->sL($this->locallangXML . ':em.updateMessageHeader'),
				'body'   => '
	  					' . /*$GLOBALS['LANG']->sL($this->locallangXML . ':error.noTemplate') .*/ '<br />
	  					<a style="text-decoration:underline;" href="' . $link . '">
	  					' . $GLOBALS['LANG']->sL($this->locallangXML . ':em.updateMessageLink') . '</a>',
			);
			$content .= $this->displayMessage($type, $msg, $style);

		}

		return $content;
	}


	/**
	 * load template file
	 *
	 * @param  string     type of message
	 * @param  string     message content
	 * @param  string     message container position style
	 * @return string     HTML wrapped message
	 * @access protected
	 * @since 0.1.0
	 */
	protected function displayMessage($type, array $msg, $style = '') {
		$this
			->getPaths()
			->loadTemplate();

		$markerArray  = array(
			'###STYLE###'         => $style,
			'###MESSAGETYPE###'   => $type,
			'###MESSAGEHEADER###' => $msg['header'],
			'###MESSAGEBODY###'   => $msg['body'],
		);

		$templateTypo3Message = t3lib_parsehtml::getSubpart($this->template, '###TEMPLATE_TYPO3MESSAGE###');
		$content              = t3lib_parsehtml::substituteMarkerArray($templateTypo3Message, $markerArray);

		return $content;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/converter2utf8/class.ext_update.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/converter2utf8/class.ext_update.php']);
}
?>