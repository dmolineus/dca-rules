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
 * ... ['global_button']['button_callback'] => array('TlFiles', 'generateGlobalButton'),
 * ... ['global_button']['button_rules'] = array('isAdmin', 'generate');
 * 
 * By default it try to match against a id. If no id is set it uses the class attribute. This way
 * is nessesarry because the button_callback does not know the name of the button
 * ... ['global_button']['button_rules']['id'] = 'mybutton1';
 * ... ['global_button']['button_rules']['class'] = 'mybutton1';
 */
class DataContainer extends Backend
{
	
	/**
	 * @var array global buttons 
	 */
	protected $arrGlobalButtons = array();
	
	/**
	 * @var arry buttons buttons 
	 */
	protected $arrButtons = array();
	
	/**
	 * @var array label callback
	 */
	protected $arrLabelCallback = array();
	
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
		
		if($this->strTable === null)
		{
			$strTable = get_class($this);
			$strTable = substr($strTable, strrpos($strTable, '\\')+1);
			$strTable = 'tl' . preg_replace('/([A-Z])/', '_\0', $strTable);
			$this->strTable = strtolower($strTable);	
		}
		
		if(!isset($GLOBALS['TL_DCA'][$this->strTable]))
		{
			return;
		}
		
		// read all button configurations
		if(isset($GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations']))
		{
			foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['global_operations'] as $strButton => $arrConfig) 
			{
				if(!isset($arrConfig['button_rules']))
				{
					continue;
				}
				
				$strMatch = (isset($arrConfig['id']) ? 'id' : 'class');
				
				$this->arrGlobalButtons[$strButton] = array
				(
					'match' => ($strMatch == 'class' ? 'icon' : $strMatch),
					'value' => $arrConfig[$strMatch],
				);
			}
		}
		
		// read all button configurations
		if(isset($GLOBALS['TL_DCA'][$this->strTable]['list']['operations']))
		{
			foreach ($GLOBALS['TL_DCA'][$this->strTable]['list']['operations'] as $strButton => $arrConfig) 
			{
				if(!isset($arrConfig['button_rules']))
				{
					continue;
				}
				
				$strMatch = (isset($arrConfig['id']) ? 'id' : 'icon');
				
				$this->arrButtons[$strButton] = array
				(
					'match' => $strMatch,
					'value' => $arrConfig[$strMatch],
				);
			}
		}
		
		// get global label callback
		if(isset($GLOBALS['TL_DCA'][$this->strTable]['list']['label']['label_rules']))
		{
			$this->arrLabelCallback = $GLOBALS['TL_DCA'][$this->strTable]['list']['label']['label_rules'];
		}
	}


	/**
	 * generic check permissioin callback
	 * 
	 * @param DataContainer 
	 */
	public function checkPermission($objDc)
	{
		$arrRules = $GLOBALS['TL_DCA'][$this->strTable]['config']['permission_rules'];
		
		foreach ($arrRules as $strRule) 
		{
			$arrAttributes = array();
			$this->parseRule($strRule, $arrAttributes, null, null, null, null, null, null, null, 'permission');
			$strError = sprintf('Not enough permissions for action "%" on item with ID "%s"', \Input::get('act'), \Input::get('id'));
			
			if(!$this->{$strRule}($objDc, $arrAttributes, $strError))
			{
				$this->log($strError, $this->strTable . ' checkPermission', TL_ERROR);
				$this->redirect('contao/main.php?act=error');
				return;
			}			
		}
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
	public function generateButton($arrRow, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes)
	{
		$strButton = $this->findButton($strHref, $strLabel, $strTitle, $strIcon, $strAttributes, $arrRow);

		if($strButton === null)
		{
			return '';
		}
		
		$arrRules = $GLOBALS['TL_DCA'][$this->strTable]['list'][($arrRow === null) ? 'global_operations' : 'operations'][$strButton]['button_rules'];
		
		$this->strGenerated = '';
		
		foreach ($arrRules as $strRule) 
		{
			$this->parseRule($strRule, $arrAttributes, $strButton, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes, $arrRow);
			
			if(!$this->$strRule($strButton, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes, $arrAttributes, $arrRow))
			{
				return '';				
			}
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
	public function generateGlobalButton($strHref, $strLabel, $strTitle, $strIcon, $strAttributes)
	{
		return $this->generateButton(null, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes);
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
		if(empty($this->arrLabelCallback))
		{
			return;
		}
		
		foreach ($this->arrLabelCallback as $strRule) 
		{
			$arrAttributes = array();
			
			$this->parseRule($strRule, $arrAttributes, null, null, $strLabel, null, null, null, $arrRow, 'label');			
			$this->{$strRule}($arrRow, $strLabel, $objDc, $arrValues, $arrAttributes);
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
				$strHref = $this->addToUrl($strHref);	
			}
			
			$this->strGenerated .= sprintf
			(
				'<a href="%s" class="%s" title="%s" %s>%s</a>',
				$strHref, $strIcon, $strTitle,  $strAttributes, $strLabel
			);
		}
		
		// local button
		else 
		{			
			if(!isset($arrAttributes['plain']))
			{
				if(!isset($arrAttributes['table']))
				{
					$strHref .= '&table=' . $this->strTable;			
				}
				
				if(!isset($arrAttributes['id']))
				{
					$strHref .= '&id=' . $arrRow['id'] ;			
				}
				
				$strHref = $this->addToUrl($strHref);	
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
	 * @param array option data row of operation buttons
	 * @return bool true if rule is passed
	 */
	protected function buttonRuleReferer(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		$strHref = $this->getReferer(true);
		$arrAttributes['plain'] = true;
		
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
		var_dump($strButton);
		var_dump($arrAttributes);
			
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
		if (strlen(\Input::get('tid')))
		{
			$this->toggleState(\Input::get('tid'), (\Input::get('state') == 1), $arrAttributes);
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

		if (!$arrRow[$strField])
		{
			$strIcon = (isset($arrAttributes['icon'])) ? $arrAttributes['icon'] : 'invisible.gif';
		}
		
		return true;
	}
	
	
	/**
	 * find button by trying to match it against the configuration
	 * 
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon class
	 * @param string added attributes
	 * @return bool true if rule is passed
	 */
	protected function findButton($href, $label, $title, $icon, $attributes, $arrRow=null)
	{
		$arrSearch = ($arrRow === null) ? 'arrGlobalButtons' : 'arrButtons';
		
		foreach ($this->{$arrSearch} as $strButton => $arrOption) 
		{			
			if(${$arrOption['match']} == $arrOption['value'])
			{
				return $strButton;
			} 			
		}
	}
	
	
	/**
	 * generic has access rule
	 * 
	 * @param attributes supports module and alexfs
	 * @return bool
	 */
	protected function genericHasAccess(&$arrAttributes)
	{
		$blnHasAccess = true;
		
		if(isset($arrAttributes['module']))
		{
			$blnHasAccess &= $this->User->hasAccess($arrAttributes['module'], 'module');			
		}
		
		if($blnHasAccess && isset($arrAttributes['alexf']))
		{
			$strTable = isset($arrAttributes['table']) ? $arrAttributes['table'] : $this->strTable;
			$blnHasAccess &= $this->User->hasAccess($strTable . '::' . $arrAttributes['alexf'], 'alexf');
		}
		
		if($blnHasAccess && isset($arrAttributes['fop']))
		{
			$blnHasAccess &= $this->User->hasAccess($arrAttributes['fop'], 'fop');
		}
		
		return $blnHasAccess;
	}
	
	
	/**
	 * generic is allowed rule
	 * 
	 * @param attributes supports
	 * 		- ptable string 	optioinal if want to check isAllowed for another table than data from $arrRow
	 * 		- field string  	optional column of current row for WHERE id=? statement, default pid
	 * 		- where string		optional customized where, default id=?
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
		$strWhere = isset($arrAttributes['where']) ? $arrAttributes['where'] :  'id=?';
		$strPId = isset($arrAttributes['pid']) ? $arrAttributes['pid'] : 'pid';
		$strValue = isset($arrAttributes['value']) ? $arrAttributes['value'] :  $arrRow[$strPId];
		
		$objParent = $this->Database->prepare("SELECT * FROM " . $strPtable . " WHERE " . $strWhere)->limit(1)->execute($strValue);								

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
	 */
	protected function labelRuleYesNo(&$arrRow, &$strLabel, &$objDc, &$arrValues, &$arrAttributes)
	{
		var_dump($arrRow[$arrAttributes['field']]);
		$strYesNow = ($arrRow[$arrAttributes['field']]) ? 'yes' : 'no';
		
		$arrValues[$arrAttributes['index']] = $GLOBALS['TL_LANG']['MSC'][$strYesNow];
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
	 * @param string button
	 * @param string href
	 * @param string label
	 * @param string title
	 * @param string icon
	 * @param string attributes
	 * @param array current row
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
	 * 		'tile:value=$strLabel', 			// access php variables, given as arguments, no array key access posible
	 * );
	 */
	protected function parseRule(&$strRule, &$arrAttributes, $strButton, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes, $arrRow=null, $strPrefix='button')
	{
		$arrRule = explode(':', $strRule);
		$strRule = $strPrefix . 'Rule' . ucfirst(array_shift($arrRule));
		
		foreach($arrRule as $strAttribute)
		{
			$arrSplit = explode('=', $strAttribute);
			
			if(!isset($arrSplit[1]))
			{
				$arrAttributes[$arrSplit[0]] = true;				
			}
			elseif(is_numeric($arrSplit[1]) || $arrSplit[1] == 'false' || $arrSplit[1] == 'true' || (substr($arrSplit[1], 0, 1) == '$'))
			{
				$arrAttributes[$arrSplit[0]] = $this->parseValue($arrSplit[1]);
			}
			elseif(substr($arrSplit[1], 0, 1) == '[')
			{
				$arrAttributes[$arrSplit[0]] = array_map(array($this, 'parseValue'), explode(',', substr($arrSplit[1], 1, -1)));
			}
			else
			{
				$arrAttributes[$arrSplit[0]] = $arrSplit[1];
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
		elseif(substr($mixedValue, 0, 1) == '$')
		{
			return $this->{substr($mixedValue, 1)};
			
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
		$blnPermission = true;
		
		if(isset($arrAttributes['act']))
		{
			if(!is_array($arrAttributes['act']))
			{
				$arrAttributes['act'] = array($arrAttributes['act']);
			}
			
			if(!in_array(\Input::get('act'), $arrAttributes['act']))
			{
				$blnPermission = false;
			}			
		}
		
		if($blnPermission)
		{
			return true;
		}
		
		$this->prepareErrorMessage($arrAttributes, $strError);
		
		return false;
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
				$arrAttributes['value'] = \Input::get('id');			
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
					$arrParams[] = \Input::get($strParam);					
				}
				
				$strError = call_user_func_array('sprintf', $arrParams);				
			}
		}
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
		\Input::setGet('id', $intId);
		\Input::setGet('act', 'toggle');
		
		$this->checkPermission();
		
		$strTable = (isset($arrAttributes['table'])) ? $arrAttributes['table'] : $this->strTable;
		$strField = (isset($arrAttributes['field'])) ? $arrAttributes['field'] : 'published';

		// Check permissions to publish
		if (!$this->User->isAdmin && !$this->User->hasAccess($strTable . '::' . $strField, 'alexf'))
		{
			$strError = 'Not enough permissions to toggle state of item ID "'.$intId.'"';
			$this->prepareErrorMessage($arrAttributes, $strError);
			 
			$this->log($strError, $this->strTable . ' toggleState', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		
		if(isset($GLOBALS['TL_DCA'][$this->strTable]['config']['enableVersioning']) && $GLOBALS['TL_DCA'][$this->strTable]['config']['enableVersioning'])
		{
			$this->createInitialVersion($strTable, $intId);
			
		}

		// Trigger the save_callback
		if (is_array($GLOBALS['TL_DCA']['tl_news']['fields'][$strField]['save_callback']))
		{
			foreach ($GLOBALS['TL_DCA']['tl_news']['fields'][$strField]['save_callback'] as $callback)
			{
				$this->import($callback[0]);
				$blnVisible = $this->$callback[0]->$callback[1]($blnVisible, $this);
			}
		}

		// Update the database
		$this->Database->prepare("UPDATE " . $strTable . " SET tstamp=". time() .", " . $strField ."='" . ($blnVisible ? 1 : '') . "' WHERE id=?")
					   ->execute($intId);


		if(isset($GLOBALS['TL_DCA'][$this->strTable]['config']['enableVersioning']) && $GLOBALS['TL_DCA'][$this->strTable]['config']['enableVersioning'])
		{
			$this->createNewVersion($strTable, $intId);
			
		}
	}
	
}
