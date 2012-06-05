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


	/**
	 * Main function, returning the HTML content of the module
	 *
	 * @return	string		HTML
	 */
	public function main() {
## $sql = 'SHOW CHARACTER SET';
		$content = '';

		if (empty ($_REQUEST['command'])) {
			$_GET['command'] = '';
		}
		switch ($_REQUEST['command']) {
		case 'exclude':
			$this
				->getTableList()
				->excludeTable();
			break;
		case 'backup':
			$this
				->getTableList()
				->backupTable();
			break;
		case 'convert':
			$this
				->getTableList()
				->convertTable();
			break;
		default:
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
	 * Checks how many rows are found and returns true if there are any
	 * (this function is called from the extension manager)
	 *
	 * @param	string		$what: what should be updated
	 * @return	boolean
	 */
	public function access($what = 'all') {
		return TRUE;
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
$path = get_defined_constants();
$path = $_SERVER;
##echo '<pre><b>$path @ ' . __FILE__ . '::' . __LINE__ . ':</b> ' . print_r($path, 1) . '</pre>';exit;
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
			'###BACKUP_FAILED###'  => $GLOBALS['LANG']->sL($this->locallangXML . ':error.backupFailed'),
			'###CONVERT_DONE###'   => $GLOBALS['LANG']->sL($this->locallangXML . ':success.convertDone'),
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
			if (!preg_match('/^' . $this->backupPrefix . '/', $tKey)) {
				$this->tables[] = $tKey;
			}
		}

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
		$templateList     = t3lib_parsehtml::getSubpart($this->template, '###TEMPLATE_TABLELIST###');
		$markerArray = array(
			'###HEAD_TABLE###'        => $GLOBALS['LANG']->sL($this->locallangXML . ':head.table'),
			'###HEAD_INFORMATION###'  => $GLOBALS['LANG']->sL($this->locallangXML . ':head.information'),
			'###HEAD_EXCLUDE###'      => $GLOBALS['LANG']->sL($this->locallangXML . ':head.exclude'),
			'###ACTION###'            => htmlspecialchars($_SERVER['REQUEST_URI']),
		###	'###ACTION###'            => '/typo3conf/ext/converter2utf8/test.php',
			'###PROCESS_BACKUP###'    => $GLOBALS['LANG']->sL($this->locallangXML . ':process.backup'),
			'###PROCESS_CONVERT###'   => $GLOBALS['LANG']->sL($this->locallangXML . ':process.convert'),
		);
		$templateList     = t3lib_parsehtml::substituteMarkerArray($templateList, $markerArray);
		$templateListItem = t3lib_parsehtml::getSubpart($templateList,   '###TEMPLATE_TABLELIST_ITEM###');

			//  loop tables
		$n = 0;
		$listItemContent  = '';
		foreach ($this->tables as $tKey => $tVal) {
			$n++;

				//  table information
			$markerArray = array(
				'###EVENODD###'     => ($n % 2 == 0) ? 'x-grid3-row-alt' : ' x-grid3-row',
				'###TABLENAME###'   => $tVal,
				'###TABLEINFO###'   => $tVal,
				'###TABLECLASS###'  => '',
				'###INFORMATION###' => array(),
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
					$msg = $GLOBALS['LANG']->sL($this->locallangXML . ':hasContent');
					$markerArray['###INFORMATION###'][] = sprintf($msg, $numRows);
				}
				$markerArray['###TABLEINFO###']     = '<img src="' . $hasTCA['iconfile'] . '" />'
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

				//  correct path if necessary
			if (empty ($hasTCA['iconfile'])) {
				if (file_exists(realpath('sysext/t3skin/icons/gfx/i/' . $table . '.gif'))) {
					$hasTCA['iconfile'] = 'sysext/t3skin/icons/gfx/i/' . $table . '.gif';
				} elseif (array_key_exists($table, $typeIcons)) {
					$hasTCA['iconfile'] = 'sysext/t3skin/images/icons/status/' . $typeIcons[$table];
				}
			}
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
				FROM ' . $table;
		$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
		if (!$res) {
				##  @ToDo: change die() with displayMessage()
		    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbCountRows');
			die(sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error()));
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
		if (!empty ($_REQUEST['table'])) {
			$table  = $_REQUEST['table'];
			$backup = $this->backupPrefix . $table;
		} else {
			die($GLOBALS['LANG']->sL($this->locallangXML . ':error.noParameterTable'));
		}

			//  get create statement
		$sql = 'SHOW CREATE TABLE ' . $GLOBALS['TYPO3_DB']->quoteStr($table, $table);
		$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
		if (!$res) {
				##  @ToDo: change die() with displayMessage()
		    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbShowCreate');
			die(sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error()));
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
			die(sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error()));
		}

			//  copy data
		$sql = 'INSERT INTO ' . $backup . '
				SELECT * FROM ' . $table;
		$res = $GLOBALS['TYPO3_DB']->sql_query($sql);
		if (!$res) {
				##  @ToDo: change die() with displayMessage()
		    $msg = $GLOBALS['LANG']->sL($this->locallangXML . ':error.dbInsertSelect');
			die(sprintf($msg, $GLOBALS['TYPO3_DB']->sql_error()));
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
		sleep(2);
		echo 'ok';
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
		sleep(2);
		echo 'ok';
		exit;
	}


	/**
	 * Get the extension configuration
	 *
	 * @return array
	 * @return obj     $this
	 */
	protected function getExtConf() {
		$extConf = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$this->extKey]);
		if (!empty ($extConf)) {
			$this->extConf = $extConf;
		}

		return $this;
	}


	/**
	 * Write back configuration
	 *
	 * @param array $extConf
	 * @return void
	 * @see EXT:caretaker_instance/class.ext_update.php
	 */
	protected function writeExtConf($extConf) {
		$install = new t3lib_install();
		$install->allowUpdateLocalConf = 1;
		$install->updateIdentity = $this->extName;

		$lines = $install->writeToLocalconf_control();
		$install->setValueInLocalconfFile($lines, '$TYPO3_CONF_VARS[\'EXT\'][\'extConf\'][\'' . $this->extKey . '\']', serialize($extConf));
		$install->writeToLocalconf_control($lines);

		t3lib_extMgm::removeCacheFiles();
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
	protected function displayMessage($type, array $msg, $styleposition = '') {
		$this
			->getPaths()
			->loadTemplate();

		$markerArray  = array(
			'###STYLEPOSITION###'  => $styleposition,
			'###MESSAGETYPE###'    => $type,
			'###MESSAGEHEADER###'  => $msg['header'],
			'###MESSAGEBODY###'    => $msg['body'],
		);

		$templateTypo3Message = t3lib_parsehtml::getSubpart($this->template, '###TEMPLATE_TYPO3MESSAGE###');
		$content              = t3lib_parsehtml::substituteMarkerArray($templateTypo3Message, $markerArray);

		return $content;
	}


	/**
	 * Message in EM with link to updater Script
	 *
	 * @return string     HTML wrapped message
	 * @access protected
	 * @since 0.1.0
	 */
	function emDisplayStartConversionMessage(&$params, &$tsObj) {
		$this
			->getPaths()
			->loadTemplate();

		$type = 'information';
		if (t3lib_div::int_from_ver(TYPO3_version) < 4005000) {
			$link = 'index.php?&amp;id=0&amp;CMD[showExt]=' . $this->extKey . '&amp;SET[singleDetails]=updateModule';
		} else {
			$link = 'mod.php?&amp;id=0&amp;M=tools_em&amp;CMD[showExt]=' . $this->extKey . '&amp;SET[singleDetails]=updateModule';
		}
		$msg  = array(
			'header'        => 'Header',
			'body'          => '
  					' . /*$GLOBALS['LANG']->sL($this->locallangXML . ':error.noTemplate') .*/ '<br />
  					<a style="text-decoration:underline;" href="' . $link . '">
  					' . $GLOBALS['LANG']->sL($this->locallangXML . ':em.updateMessageLink') . '</a>',
		);
		$styleposition = 'position: absolute; top:10px; right:10px; z-index: 10000;';
		$content = $this->displayMessage($type, $msg, $styleposition);

		return $content;
	}
}


if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/converter2utf8/class.ext_update.php']) {
	include_once ($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/converter2utf8/class.ext_update.php']);
}
?>