Contao extension DataContainer Rules
=========

This extension provides easily configurable rules for generating buttons and labels in the dca files of Contao.

The issue
--------

Usually you have to declare button callbacks for every button you want to generate depending on some rules. 
So usually you run into coding the more or less same callbacks for every button or for every label value
manipulation.

How dca-rules works
--------

dca-rules solves this issue by providing general callbacks which depends on defined rules in the dca files. At
the moment there are four callbacks defined:

* checkPermission 		for the dca onload_callback
* generateButton		for generating a operation button
* generateGlobalButton 	for generating a global operation button
* generateLabel			for the label_callback in the list view

Then you can set up somes rules for them by using following variables

	$GLOBALS['TL_DCA']['table']['config']['permission_rules'] = array();
	$GLOBALS['TL_DCA']['table']['list']['label']['label_rules'] = array();
	$GLOBALS['TL_DCA']['table']['list']['operations']['button']['button_rules'] = array();
	$GLOBALS['TL_DCA']['table']['list']['global_operations']['button']['button_rules'] = array();

The only thing you have to do is to create a data container class extending the provided class and using the 
provides callbacks

	// Contao 3
	namepsace Author\Vendor\DataContainer;
	use Netzmacht\Utils\DataContainer;
	
	class MyTable extends DataContainer
	{
		// auto generates $this->strTable = 'tl_my_table';
		// you can set a custom protected $strTable = 'custom_table'; as well  
	}

	// Contao 2
	require_once 'system/modules/dca-rules/classes/DataContainer.php'
	class MyTable extends DataContainer extends Netzmacht\Utils\DataContainer
	{
	}
	
	// alternative
	class MyTable extends DataContainerRules
	{
	}

	// in the tl_my_table.php
	$GLOBALS['TL_DCA']['table']['list']['operations']['button']['button_callback'] = array('Author\Vendor\DataContainer\MyTable', 'generateButton');
	$GLOBALS['TL_DCA']['table']['list']['operations']['button']['button_rules'] = array('isAdmin', 'generate');
	
Using rules
------

You can define a list of rules which are called each until one return false. You have to use the generate rule for buttons, so you can decide that
something will happen after the button is generated. There is also the possibility to set attributes for the rules. There is following syntax for
them:

	$rule = array('rule:attribute'); // will set attribute to true
	$rule = array('rule:attribute=2'); // will set attribute to an int 2, int and bool(false, true) are converted to the value
	$rule = array('rule:attribute=$value'); // accessing attributes of the object (will be translated to $this->value)
	$rule = array('rule:attribute=[one,two,three]'); // will set attribute to an array(one,two,three)
	$rule = array('rule:attribute=[true,2,false,$value,2.3]'); // values of an array will be converted to ints and bool as well
	$rule = array('rule:a=2:b=3:c:4');	// combining attributes
	$rul

Maybe you want to create a toggle icon for a field called 'status'. The toggleIcon will automatically check if the user has access to the field. So
the only thing you have to do is to define the rule and pass the field. If no field is set it uses the published field

	$GLOBALS['TL_DCA']['table']['list']['operations']['button']['button_rules'] = array('toggleIcon:field=status', 'generate');

Another example is the checkPermission callback. Maybe you want to limit the access to the dca for the admin for every action exept the show action.
You can solve it by assigning a isAdmin rule and set the act modes. Then the rule is only used if on the act mode

	$GLOBALS['TL_DCA']['table']['config']['permission_rules'] = array('isAdmin:act=[edit,editAll,delete,select]', 'generate');
	
Using label_rules
------

Label rules are useful to manipulate the output of the the values. There is no stop method included here, so every registered rule is used. They usually
have the index and field attribute. the index attribute decides which value of the return will be changed, the field decides which value of the row array is used.

The following example will create a yes no output for value[1]. It is nessecary to use the field, because the field header is the current value. Parse date will parse
the date for value[2]. Format can be datim,date,time or a custom rule. It can be empty, them datim will be used

	$GLOBALS['TL_DCA']['table']['list']['label']['label_rules'] = array('yesNo:index=1:field=enabled', 'parseDate:index=2:format=time))
	
Create custom rules
------

The DataContainer provides a set of rules. But it is easily extendable. You simply has to create a method in the data container. there are following
name conventions

* For button rules: buttonRule* 
* For label rules: labelRule*
* For permission rules: permissionRule*

The rules will get different arguments. You should use the references so it is easy to manipulate values. For example will want to add a state to the href,
let's create a buttonRuleAddState, the new $strHref will be used by the generate rule. The referer rule, which generates a referer link works like this.

	// dca config
	$GLOBALS['TL_DCA']['table']['list']['operations']['button']['button_rules'] = array('isAdmin', 'addState', 'generate');

	protected function buttonRuleAddState(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		$strHref .= '&state=1';
		return true;
	}
	
It is also possible to add something to the output. Let's say you want to add the current time behind a global button. The output will be stored in 
$this->strGenerated variable. Note that we first generate the button before we add something to the generated output.

	// dca config
	$GLOBALS['TL_DCA']['table']['list']['operations']['button']['button_rules'] = array('generate', 'addTime');

	protected function buttonRuleAddTime(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow=null)
	{
		$this->strGenerated .= sprintf(' (%s)', date('h:i'));
		return true;
	}
	
Provided rules
------

Following rules are inlcuded and can be used:

__Button rules__
used for normal and global operations

* generate											creates a icon output
* toggleIcon, attributes: table, field, icon		for createing a toggling icon
* referer											for creating a back link
* hasAccess, attributes: isAdmin,module,alexf,fop	check is user has access by using the BackendUser::hasAccess
* isAllowed, attributes: operation,table,closed,ptable,pid,where,value	check is user is allowed by using the BackendUser::isAllowed
* isAdmin											check if user is admin

__Label rules__
are useful to change the displayed value in the list view

* parseDate, attributes: index,field,format			parsing a timestamt
* yesNo, attributes: index,field

__Permission rules__
for using the gerneric checkPermission

* generic, attributes: act,error,params				used to check the access for an action and can create a custom log message
* hasAccess, attributes: isAdmin,module,alexf,fop,value,act,error,params
* isAllowed, attributes: operation,table,closed,ptable,pid,where,value,act,error,params
* isAdmin, attributes: act,error,params				check if user is admin
* forbidden: act,error,params						deny access for everyone