<?php

/**
 * Contao Open Source CMS
 * 
 * Copyright (C) 2005-2012 Leo Feyer
 * 
 * @package   netzmacht-utils
 * @author    David Molineus <http://www.netzmacht.de>
 * @license   GNU/LGPL 
 * @copyright Copyright 2012 David Molineus netzmacht creative 
 *  
 **/
 
namespace Netzmacht\Utils;
use Backend;


/**
 * General DataContainer which provides usefull helpers for the daily stuff
 * using DataContainers
 * 
 * You only have to extend it and set $strTable. Then you can use it for generating
 * your buttons by assign different rules. It's all configurable in the DCA!. Just use
 * a generic callback for creating the button. It's nessessary to add the generate rule
 * so you can decide if something should happen after that
 * 
 */
class DataContainer extends Backend
{
	
	/**
	 * @var string generated output
	 */
	protected $strGenerated = '';
	
	/**
	 * @var string table
	 */
	protected $strTable;
	
	
	/**
	 * constructor fetches all configurations
	 */
	public function __construct()
	{
		parent::__construct();
		
		$this->import('BackendUser', 'User');
		$this->import('Input');
		
		if($this->strTable === null)
		{
			$strTable = get_class($this);	
			$intPosNs = strrpos($strTable, '\\');				
			$strTable = $intPosNs === false ? $strTable : substr($strTable, $intPosNs+1);
			$strTable = 'tl' . preg_replace('/([A-Z])/', '_\0', $strTable);
			$this->strTable = strtolower($strTable);	
		}
	}


	/**
	 * use __call to generate seperate methods for each button. so it is possible to
	 * find a button
	 * 
	 * @param string called method
	 * @param array arguments
	 */
	public function __call($strMethod, $arrArguments)
	{
		if (strncmp($strMethod, 'generateButton', 14) === 0)
		{
			$strButton = lcfirst(substr($strMethod, 14));
			array_insert($arrArguments, 0, $strButton);
			return call_user_func_array(array($this, 'generateButton'), $arrArguments);
		}
		elseif (strncmp($strMethod, 'generateGlobalButton', 20) === 0)
		{
			$strButton = lcfirst(substr($strMethod, 20));
			array_insert($arrArguments, 0, $strButton);
			return call_user_func_array(array($this, 'generateGlobalButton'), $arrArguments);
		}
		
		return null;
	}


	/**
	 * generic check permissioin callback
	 * 
	 * @param DataContainer 
	 */
	public function checkPermission($objDc=null)
	{
		if(!isset($GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules']))
		{
			return;
		}
		
		$arrRules = $GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'];
		
		// generate default error message
		if($this->Input->get('act') != '')
		{
			$strErrorDefault = sprintf('User "%s" has not enough permission to run action %s"', $this->User->username, $this->Input->get('act'));
			
			if($this->Input->get('id') != '')
			{
				$strErrorDefault .= ' on item with ID "' . $this->Input->get('id') . '"';
			}
		}
		else
		{
			$strErrorDefault = sprintf('User "%s" has not enough permission to access module %s"', $this->User->username, $this->Input->get('do'));
		}
		
		foreach ($arrRules as $strRule) 
		{
			$strError = $strErrorDefault;
			
			$this->parseRule($strRule, $arrAttributes, 'permission');			
			
			if(!$this->{$strRule}($objDc, $arrAttributes, $strError))
			{
				$this->log($strError, $this->strTable . ' checkPermission', TL_ERROR);
				$this->redirect('contao/main.php?act=error');
				return;
			}

			$this->resetAttributes($arrAttributes);
		}
	}
	
	
	/**
	 * generic generateLabel callback
	 * 
	 * @param array current row
	 * @param string label
	 * @param DataContainer
	 * @param array values
	 * @return array
	 */
	public function generateLabel($arrRow, $strLabel, $objDc, $arrValues)
	{
		if(!isset($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['label_rules']))
		{
			return $arrValues;
		}
		
		foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['label_rules'] as $strRule) 
		{
			$arrAttributes = array();
			
			$this->parseRule($strRule, $arrAttributes, 'label');						
			$this->{$strRule}($arrRow, $strLabel, $objDc, $arrValues, $arrAttributes);
			
			$this->resetAttributes($arrAttributes);
		}
		
		return $arrValues;		
	}
	


	/**
	 * rule for generating the button
	 *
	 * @param string the button name 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @param array attributes
	 * @param array option data row of operation buttons
	 * @return bool true if rule is passed
	 */
	protected function buttonRuleDisableIcon(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		if(isset($arrAttributes['rule']))
		{
			$strRule = $arrAttributes['rule'];
			$this->parseRule($strRule, $arrAttributes);
	
			$blnDisable = $this->{$strRule}($strButton, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes, $arrAttributes, $arrRow);
		}
		else
		{
			$blnDisable = (bool) $arrAttributes['value'];
		}
			
		if($blnDisable && !$arrAttributes['disable'])
		{
			$arrAttributes['disable'] = true;
			$arrAttributes['__set__'][] = 'disable';
			$strIcon = isset($arrAttributes['icon']) ? $arrAttributes['icon'] : str_replace('.', '_.', $strIcon);
		}
		
		return true;
	}
	
	
	/**
	 * rule for generating the button
	 *
	 * @param string the button name 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @param array attributes
	 * @param array option data row of operation buttons
	 * @return bool true if rule is passed
	 */
	protected function buttonRuleGenerate(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		// global button
		if($arrRow === null)
		{
			if(!isset($arrAttributes['plain']))
			{
				if(isset($arrAttributes['table']))
				{
					$strHref .= '&table=' . ($arrAttributes['table'] === true ? $this->strTable : $arrAttributes['table']);		
				}
				
				if(isset($arrAttributes['id']))
				{
					$strHref .= '&id=' . ($arrAttributes['id'] === true ? $this->Input->get('id') : $arrAttributes['id']);		
				}
				
				$strHref = 'contao/main.php?do=' . $this->Input->get('do') . '&' . $strHref . '&rt=' . REQUEST_TOKEN;
			}
			
			$this->strGenerated .= sprintf
			(
				'<a href="%s" class="%s" title="%s" %s>%s</a>',
				$strHref, $strIcon, $strTitle,  $strAttributes, $strLabel
			);
		}
		
		// local button
		elseif(isset($arrAttributes['disable']))
		{
			$this->strGenerated = $this->generateImage($strIcon, $strLabel) . ' ';
		}
		else
		{			
			if(!isset($arrAttributes['plain']))
			{
				if(!isset($arrAttributes['noTable']))
				{
					$strHref .= '&table=' . $this->strTable;			
				}
				
				if(!isset($arrAttributes['noId']))
				{
					$strHref .= '&id=' . $arrRow['id'] ;			
				}
				
				$strHref = $this->addToUrl($strHref);	
			}
			else 
			{
				$blnFirst = (strpos($strHref, '?') === false);
				
				if(isset($arrAttributes['table']))
				{
					$strHref .= ($blnFirst ? '?' : '&') . 'table=' . ($arrAttributes['table'] === true ? $this->strTable : $arrAttributes['table']);
					$blnFirst = false;			
				}
				
				if(isset($arrAttributes['id']))
				{
					$strHref .= ($blnFirst ? '?' : '&') . 'id=' . $arrRow['id'] ;
					$blnFirst = false;			
				}
				
				if(isset($arrAttributes['rt']))
				{
					$strHref .= ($blnFirst ? '?' : '&') . 'rt=' . REQUEST_TOKEN ;
					$blnFirst = false;			
				}
			}
			
			$this->strGenerated .= sprintf
			(
				'<a href="%s" title="%s" %s>%s</a> ',
				$strHref, $strTitle,  $strAttributes, $this->generateImage($strIcon, $strLabel)
			);			
		}
		
		return true;
	}
	
	
	/**
	 * rule for generating a referer button
	 *
	 * @param string the button name 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @param array attributes
	 * @param array option data row of operation buttons
	 * @return bool true if rule is passed
	 */
	protected function buttonRuleReferer(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		$strHref = $this->getReferer(true);
		$arrAttributes['plain'] = true;
		$arrAttributes['__set__'][] = 'plain';
		
		return true;
	}
	
	
	/**
	 * rule for checking if user hass access to something 
	 *
	 * @param string the button name 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @param array option data row of operation buttons
	 * @param arrary supported attribues
	 * 		- bool isAdmin, set to false if not gaining access if user admin
	 * 		- string module check if user has access to module
	 * 		- string alexf Allowed excluded fields
	 * @return bool true if rule is passed
	 */
	protected function buttonRuleHasAccess(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		if(isset($arrAttributes['isAdmin']) && $arrAttributes['isAdmin']  && $this->User->isAdmin)
		{
				return true;			
		}
				
		return $this->genericHasAccess($arrAttributes);
	}
	
	
	/**
	 * rule checks if user is admin
	 *
	 * @param string the button name 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @param array attributes
	 * @param array option data row of operation buttons
	 * @return bool true if rule is passed
	 */
	protected function buttonRuleIsAdmin(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		return $this->User->isAdmin;
	}
	
	
	/**
	 * rule checks if user is allowed to run action
	 *
	 * @param string the button name 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @param array option data row of operation buttons
	 * 		- table string 		optional if want to check for another table
	 * 		- closed bool 		optional if want to check if table is closed
	 * 		- ptable string 	optioinal if want to check isAllowed for another table than data from $arrRow
	 * 		- field string  	optional column of current row for WHERE id=? statement, default pid
	 * 		- where string		optional customized where, default id=?
	 * 		- value string		optional value if not want to check against a value of arrRow, default $arrRow[$pid]
	 * 		- operation int 	operation integer for BackendUser::isAllowed
	 * @return bool true if rule is passed
	 */
	protected function buttonRuleIsAllowed(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		$strTable = (isset($arrAttributes['table'])) ? $arrAttributes['table'] : $this->strTable;
				
		if(isset($arrAttributes['closed']) && $GLOBALS['TL_DCA'][$strTable]['config']['closed'])
		{
			return false;				
		}
		
		if($this->User->isAdmin)
		{
			return true;
		}
		
		return $this->genericIsAllowed($arrAttributes, $arrRow);
	}
	
	
	/**
	 * using the standard toggle icon rule of contao to make it customizeable
	 *
	 * @param string the button name 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @param array option data row of operation buttons
	 * 		- string table optional if not checking access to the data container table
	 * 		- string field optional field which stores the state, default is published
	 * 		- string icon  optional icon to use for invisible state, default is invisible.gif
	 * @return bool true if rule is passed
	 */
	protected function buttonRuleToggleIcon(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		if (strlen($this->Input->get('tid')))
		{
			$this->toggleState($this->Input->get('tid'), ($this->Input->get('state') == 1), $arrAttributes);
			$this->redirect($this->getReferer());
		}
		
		$strTable = (isset($arrAttributes['table'])) ? $arrAttributes['table'] : $this->strTable;
		$strField = (isset($arrAttributes['field'])) ? $arrAttributes['field'] : 'published';
		
		// Check permissions AFTER checking the tid, so hacking attempts are logged
		if (!$this->User->isAdmin && !$this->User->hasAccess($strTable . '::' . $strField , 'alexf'))
		{
			return false;
		}

		$strHref .= '&amp;tid='.$arrRow['id'].'&amp;state='.($arrRow[$strField] ? '' : 1);

		if ((isset($arrAttributes['inverted']) ? $arrRow[$strField] : !$arrRow[$strField]))
		{
			$strIcon = (isset($arrAttributes['icon'])) ? $arrAttributes['icon'] : 'invisible.gif';
		}
		
		return true;
	}
	
	
	/**
	 * generic generate button callback
	 *
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @return string
	 */
	protected function generateButton($strButton, $arrRow, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes)
	{
		$strOperation = ($arrRow === null) ? 'global_operations' : 'operations';
		
		if($strButton === null || !isset($GLOBALS['TL_DCA'][$this->strTable]['list'][$strOperation][$strButton]['button_rules']))
		{
			return '';
		}
		
		$arrRules = $GLOBALS['TL_DCA'][$this->strTable]['list'][$strOperation][$strButton]['button_rules'];
		
		$this->strGenerated = '';
		
		foreach ($arrRules as $strRule) 
		{
			$this->parseRule($strRule, $arrAttributes);
						
			if(!$this->$strRule($strButton, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes, $arrAttributes, $arrRow))
			{				
				return '';
			}
			
			$this->resetAttributes($arrAttributes);
		}
		
		return $this->strGenerated;
	}
	
	
	/**
	 * generic create global button callback
	 *
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @return string
	 */
	protected function generateGlobalButton($strButton, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes)
	{
		return $this->generateButton($strButton, null, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes);
	}	
	
	
	/**
	 * generic has access rule
	 * 
	 * @param attributes supports module and alexfs
	 * @return bool
	 */
	protected function genericHasAccess(&$arrAttributes)
	{
		if(isset($arrAttributes['module']))
		{
			return $this->User->hasAccess($arrAttributes['module'], 'modules');			
		}
		
		if($arrAttributes['ptable'])
		{
			$strTable = $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'];
		}
		else
		{
			$strTable = isset($arrAttributes['table']) ? $arrAttributes['table'] : $this->strTable;
		}
		
		if(isset($arrAttributes['permission']) && isset($arrAttributes['action']))
		{
			if($arrAttributes['action'] == 'alexf')
			{
				
				$arrAttributes['action'] = $strTable . '::' . $arrAttributes['action'];
			}
			
			return $this->User->hasAccess($arrAttributes['action'], $arrAttributes['permission']);			
		}
		elseif(isset($arrAttributes['alexf']))
		{
			return $this->User->hasAccess($strTable . '::' . $arrAttributes['alexf'], 'alexf');
		}
		elseif(isset($arrAttributes['fop']))
		{
			return $this->User->hasAccess($arrAttributes['fop'], 'fop');
		}
		
		return false;
	}
	
	
	/**
	 * generic is allowed rule
	 * 
	 * @param attributes supports
	 * 		- ptable string 	optioinal if want to check isAllowed for another table than data from $arrRow
	 * 		- field string  	optional column of current row for WHERE id=? statement, default pid
	 * 		- value string		optional value if not want to check against a value of arrRow, default $arrRow[$pid]
	 * 		- operation int 	operation integer for BackendUser::isAllowed
	 * @param array current row
	 * @return bool
	 */
	protected function genericIsAllowed(&$arrAttributes, $arrRow=null)
	{
		if(!isset($arrAttributes['ptable']))
		{
			return $this->User->isAllowed($arrAttributes['operation'], $arrRow);			
		}
		
		$strPtable = (isset($arrAttributes['ptable']) && $arrAttributes['ptable'] !== true) ? $arrAttributes['ptable'] : $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'];
		$strPId = isset($arrAttributes['pid']) ? $arprAttributes['pid'] : 'pid';
		$strValue = isset($arrAttributes['value']) ? $arrAttributes['value'] :  $arrRow[$strPId];
		
		$objParent = $this->Database->prepare("SELECT * FROM " . $strPtable . " WHERE id=?")->limit(1)->execute($strValue);								

		return $this->User->isAllowed($arrAttributes['operation'], $objParent->row());
	}
	
	
	/**
	 * set value to yes or no depending on value
	 * 
	 * @param array current row
	 * @param string label
	 * @param DataContainer
	 * @param array reference to values
	 * @param int value of index
	 * @param string field name
	 * @param array attributes, supports
	 * 		- index int index of value
	 * 		- field string field name
	 * 		- condition mixed optional if you want to check a condition not only a value
	 * 		- is mixed optional to check value against another value, otherwise only true/false of value is checked
	 */
	protected function labelRuleYesNo(&$arrRow, &$strLabel, &$objDc, &$arrValues, &$arrAttributes)
	{
		$mixedValue = isset($arrAttributes['field'])? $arrRow[$arrAttributes['field']] : $arrValues[$arrAttributes['index']];
		
		if(isset($arrAttributes['condition']))
		{
			$blnIs = isset($arrAttributes['is']) ? $arrAttributes['is'] : true;
			$strYesNo = (($mixedValue == $arrAttributes['condition']) == $blnIs) ? 'yes' : 'no';
		}
		else 
		{
			$mixedValue = isset($arrAttributes['is']) ? ($mixedValue == $arrAttributes['is']) : $mixedValue;
			$strYesNo = ($mixedValue) ? 'yes' : 'no';	
		}
		
		$arrValues[$arrAttributes['index']] = $GLOBALS['TL_LANG']['MSC'][$strYesNo];
	}
	
	
	/**
	 * parse timestamp into date
	 * 
	 * @param array current row
	 * @param string label
	 * @param DataContainer
	 * @param array reference to values
	 * @param int value of index
	 * @param string field name
	 */
	protected function labelRuleParseDate(&$arrRow, &$strLabel, $objDc, &$arrValues, &$arrAttributes)
	{
		if(isset($arrAttributes['field']))
		{
			$arrValues[$arrAttributes['index']] = $arrRow[$arrAttributes['field']];
		}
		
		if(!isset($arrAttributes['format']) || $arrAttributes['format'] == 'datim')
		{
			$strFormat = isset($arrAttributes['format']) ? $arrAttributes['format'] : $GLOBALS['TL_CONFIG']['datimFormat'];
		}
		elseif($arrAttributes['format'] == 'date')
		{
			$strFormat = $GLOBALS['TL_CONFIG']['dateFormat'];
		}
		elseif($arrAttributes['format'] == 'time')
		{
			$strFormat = $GLOBALS['TL_CONFIG']['timeFormat'];
		}
		else 
		{
			$strFormat = $arrAttributes['format'];
		}
		
		$arrValues[$arrAttributes['index']] = $this->parseDate($strFormat, $arrValues[$arrAttributes['index']]);
	}
	
	
	/**
	 * parse a string which contains the rule with attributes
	 * 
	 * @param string the rule
	 * @param array attributes
	 * @param string rule prefix
	 * 
	 * Syntax of a rule: do not use spaces
	 * 
	 * 'button_rules' = array
	 * (
	 * 		'isAdmin,' 							// simple rule
	 * 		'hasAccess:isAdmin', 				// set attribute isAdmin=true
	 * 		'hasAccess:module=files', 			// set attribute module='files'
	 * 		'hasAccess:isAdmin:module=files', 	// set attributes isAdmin=true and module='files'
	 * 		'hasAccess:module=[files,news]', 	// set attribute module=array('files', 'news') 
	 * 		'hasAccess:isAdmin=false', 			// convert to boolean attributes isAdmin=false
	 * 		'hasAccess:isAdmin=1', 				// convert to int attributes isAdmin=1
	 * 		'title:value=$strLabel', 			// access php variables, given as arguments, no array key access posible
	 * 		'hasAccess:error=&.lang'			// access $GLOBALS['TL_LANG'][$this->strTable]['lang']
	 * 		'hasAccess:error=&.lang.0'			// access $GLOBALS['TL_LANG'][$this->strTable]['lang'][0]
	 * 		'hasAccess:error=&tl_article.create.0' // access $GLOBALS['TL_LANG']['tl_article']['lang'][0]
	 * );
	 */
	protected function parseRule(&$strRule, &$arrAttributes, $strPrefix='button')
	{
		$arrRule = explode(':', $strRule);
		$strRule = $strPrefix . 'Rule' . ucfirst(array_shift($arrRule));
		
		foreach($arrRule as $strAttribute)
		{
			$arrSplit = explode('=', $strAttribute);
			
			if(!isset($arrSplit[1]))
			{
				$arrAttributes[$arrSplit[0]] = true;
				continue;		
			}
			
			$strFirst = substr($arrSplit[1], 0, 1);
			
			if(substr($arrSplit[1], 0, 1) == '[')
			{
				$arrAttributes[$arrSplit[0]] = array_map(array($this, 'parseValue'), explode(',', substr($arrSplit[1], 1, -1)));
			}
			else
			{
				$arrAttributes[$arrSplit[0]] = $this->parseValue($arrSplit[1]);
			}
		}
	}
	
	
	/**
	 * parse a value to a float, int, boolean value
	 * 
	 * @param mixed
	 * @return mixed
	 */
	protected function parseValue($mixedValue)
	{
		if(is_numeric($mixedValue))
		{
			return $mixedValue + 0;
		}
		elseif($mixedValue == 'true')
		{
			return true;
		}
		elseif($mixedValue == 'false')
		{
			return false;			
		}
		
		$strFirst = substr($mixedValue, 0, 1);
		
		if($strFirst == '$')
		{
			return $this->{substr($mixedValue, 1)};
			
		}
		elseif($strFirst == '&')
		{
			$arrLang = explode('.', substr($mixedValue, 1));
			
			if($arrLang[0] == '')
			{
				$arrLang[0] = $this->strTable;
			}
			elseif($arrLang[0] != 'MOD' && $arrLang[0] != 'MSC') 
			{
				$this->loadLanguageFile($arrLang[0]);				
			}
			
			if(isset($arrLang[2]))
			{
				return $GLOBALS['TL_LANG'][$arrLang[0]][$arrLang[1]][$arrLang[2]];
			}
			
			return $GLOBALS['TL_LANG'][$arrLang[0]][$arrLang[1]];
		}

		return $mixedValue;
	}
	
	
	/**
	 * doing generic permission rule handling
	 * 
	 * checking for access to act param
	 * and support error messages
	 * 
	 * @param DataContainer
	 * @param array attributes, supports act, error and params
	 * @param string error message
	 * @return bool true if rule is passed
	 */
	protected function permissionRuleGeneric($objDc, &$arrAttributes, &$strError)
	{
		$this->prepareErrorMessage($arrAttributes, $strError);
		$blnAccess = true;

		if(isset($arrAttributes['act']))
		{
			if($arrAttributes['act'] == '*' && $this->Input->get('act') != '')
			{
				return true;
			}
			
			if(!is_array($arrAttributes['act']))
			{
				$arrAttributes['act'] = array($arrAttributes['act']);
			}
			
			if(in_array($this->Input->get('act'), $arrAttributes['act']))
			{
				return true;
			}
			
			$blnAccess = false;
		}
		
		if(isset($arrAttributes['key']))
		{
			if($arrAttributes['key'] == '*' && $this->Input->get('act') != '')
			{
				return true;
			}
			
			if(!is_array($arrAttributes['key']))
			{
				$arrAttributes['key'] = array($arrAttributes['key']);
			}
			
			if(in_array($this->Input->get('key'), $arrAttributes['key']))
			{
				return true; 
			}
			
			$blnAccess = false;		
		}
	
		return $blnAccess;
	}
	
	
	/**
	 * check if user has access depending on the act
	 * 
	 * @param DataContainer
	 * @param array attributes, supports act,error,params
	 * @param string error message
	 * @return bool
	 */
	protected function permissionRuleHasAccess($objDc, &$arrAttributes, &$strError)
	{	
		if($this->permissionRuleGeneric($objDc, $arrAttributes, $strError))
		{
			return $this->genericHasAccess($arrAttributes);			
		}
		
		return true;		
	}
	

	/**
	 * check if user has access depending on the act
	 * 
	 * @param DataContainer
	 * @param array attributes, supports
	 * 		- ptable string 	check isAllowed that table 
	 * 		- where string		optional customized where, default id=?
	 * 		- value string		option value for the where clause, if empty the request id will be used
	 * 		- operation int 	operation integer for BackendUser::isAllowed
	 * @param string error message
	 * @return bool
	 */
	protected function permissionRuleIsAllowed($objDc, &$arrAttributes, &$strError)
	{		
		if($this->permissionRuleGeneric($objDc, $arrAttributes, $strError))
		{
			if(!isset($arrAttributes['value']))
			{
				$arrAttributes['value'] = $this->Input->get('id');			
			}
			
			return $this->genericIsAllowed($arrAttributes, $objDc->activeRecord->row());			
		}
		
		return true;		
	}
	
		
	/**
	 * check if user is admin depending on the act
	 * 
	 * @param DataContainer
	 * @param array attributes, supports act,error,params
	 * @param string error message
	 * @return bool
	 */
	protected function permissionRuleIsAdmin($objDc, &$arrAttributes, &$strError)
	{
		if($this->permissionRuleGeneric($objDc, $arrAttributes, $strError))
		{
			return $this->User->isAdmin;			
		}
		
		return true;		
	}
	
	
	/**
	 * use this role for disabling access
	 * 
	 * @param DataContainer
	 * @param array attributes
	 * @param string error message
	 * @return bool
	 */
	protected function permissionRuleForbidden($objDc, &$arrAttributes, &$strError)
	{
		if($this->permissionRuleGeneric($objDc, $arrAttributes, $strError))
		{
			return false;			
		}
		
		return true;		
	}
	
	
	/**
	 * prepare a error message will try to replace wildcards in an error message
	 * 
	 * @param array attributes, supportet error and params
	 * @param string error message
	 */
	protected function prepareErrorMessage(&$arrAttributes, &$strError)
	{
		if(isset($arrAttributes['error']))
		{
			if(isset($arrAttributes['params']))
			{
				if(!is_array($arrAttributes['params']))
				{
					$arrAttributes['params'] = array($arrAttributes['params']);
				}
				
				$arrParams = array($arrAttributes['error']);
				
				foreach ($arrAttributes['params']  as $strParam) 
				{
					$arrParams[] = ($strParam == '%user') ? $this->User->username : $this->Input->get($strParam);					
				}
				
				$strError = call_user_func_array('sprintf', $arrParams);				
			}
		}
	}
	
	
	/**
	 * reset attributes will remove attributes which are not flagged in __set__
	 * 
	 * @param array attributes
	 */
	protected function resetAttributes(&$arrAttributes)
	{
		if(!isset($arrAttributes['__set__']) || empty($arrAttributes['__set__']))
		{
			$arrAttributes = array();
			return;	
		}
		
		$arrNew = array();
		$arrNew['__set__'] = $arrAttributes['__set__'];
		
		foreach ($arrAttributes['__set__'] as $strKey) 
		{
			if(isset($arrAttributes[$strKey]))
			{
				$arrNew[$strKey] = $arrAttributes[$strKey];
			}			
		}
		
		$arrAttributes = $arrNew;
	}


	/**
	 * toggle the state, the way it is used in contao (see tl_news or tl_article)
	 * 
	 * @param integer
	 * @param boolean
	 */
	protected function toggleState($intId, $blnVisible, &$arrAttributes)
	{
		// Check permissions to edit
		$this->Input->setGet('id', $intId);
		$this->Input->setGet('act', 'toggle');
		
		$this->checkPermission();
		
		if(isset($arrAttributes['inverted']))
		{
			$blnVisible = !$blnVisible;
		}
		
		$strTable = (isset($arrAttributes['table'])) ? $arrAttributes['table'] : $this->strTable;
		$strField = (isset($arrAttributes['field'])) ? $arrAttributes['field'] : 'published';

		// Check permissions to publish
		if (!$this->User->isAdmin && !$this->User->hasAccess($strTable . '::' . $strField, 'alexf'))
		{
			$strError = 'Not enough permissions to toggle state of item ID "'.$intId.'"';
			$this->prepareErrorMessage($arrAttributes, $strError);
			 
			$this->log($strError, get_class($this) . ' toggleState', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		
		if(isset($GLOBALS['TL_DCA'][$strTable]['config']['enableVersioning']) && $GLOBALS['TL_DCA'][$strTable]['config']['enableVersioning'])
		{
			$this->createInitialVersion($strTable, $intId);
		}

		// Trigger the save_callback
		if (is_array($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['save_callback']))
		{
			foreach ($GLOBALS['TL_DCA'][$strTable]['fields'][$strField]['save_callback'] as $callback)
			{
				$this->import($callback[0]);
				$blnVisible = $this->$callback[0]->$callback[1]($blnVisible, $this);
			}
		}

		// Update the database
		$this->Database->prepare("UPDATE " . $strTable . " SET tstamp=". time() .", " . $strField ."='" . ($blnVisible ? 1 : '') . "' WHERE id=?")
					   ->execute($intId);


		if(isset($GLOBALS['TL_DCA'][$strTable]['config']['enableVersioning']) && $GLOBALS['TL_DCA'][$strTable]['config']['enableVersioning'])
		{
			$this->createNewVersion($strTable, $intId);
			
		}
	}
	
}
