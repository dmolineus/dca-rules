<?php

require '/var/www/dev/system/initialize.php';
require '/var/www/dev/system/modules/dca-rules/classes/DataContainer.php';

$GLOBALS['TL_LANG']['tl_data_container']['error'][0] = 'fehler';

class CustomTable extends Netzmacht\Utils\DataContainer
{
	protected $strTable = 'my_custom_table';
}

class CustomizedTable extends Netzmacht\Utils\DataContainer
{
	
}

class DataContainerTest extends PHPUnit_Framework_TestCase
{
	// protected $strTable testing
	
	/**
	 * @dataProvider provideStrTable
	 */
	public function testStrTable($strTable, $objClass)
	{
		$this->assertAttributeEquals($strTable,	'strTable', $objClass);
	}
	
	public function provideStrTable()
	{
		return array(
			array('tl_data_container', new Netzmacht\Utils\DataContainer()),
			array('my_custom_table', new CustomTable()),
			array('tl_customized_table', new CustomizedTable()),
		);	
	}
	
	
	// parseValue testing
	
	/**
	 * @dataProvider provideParseValue
	 */
	public function testParseValue($value, $result)
	{
		$parseValue = self::getMethod('parseValue');
		
		$dc = new Netzmacht\Utils\DataContainer();
		$this->assertEquals($result, $parseValue->invokeArgs($dc, array($value)));
		
	}
	
	public function provideParseValue()
	{
		return array(
			array('true', true),
			array('false', false),
			array('1.2', 1.2),
			array('200', 200),
			array('wahl', 'wahl'),
			array('$strTable', 'tl_data_container'),
			array('&.error.0', 'fehler'),
			array('&ERR.general', 'An error occurred!'),
		);
	}
	
	
	// parseRule testing
	
	/**
	 * @dataProvider provideParseRule
	 */
	public function testParseRule($strRule, $strExpected, $arrArgsExpected, $prefix='button')
	{
		$parseRule = self::getMethod('parseRule');
		
		$dc = new Netzmacht\Utils\DataContainer();
		$arrAttributes = array();

		$parseRule->invokeArgs($dc, array(&$strRule, &$arrAttributes, $prefix));
		$this->assertEquals($strExpected, $strRule);
		$this->assertEquals($arrArgsExpected, $arrAttributes);
	}
	
	public function provideParseRule()
	{
		return array(
			array('generate:id=[1,2,3,4,5]', 'buttonRuleGenerate', array('id' => array(1,2,3,4,5))),
			array('generateExample:id=[2,false,1.2,hallo]', 'permissionRuleGenerateExample', array('id' => array(2, false, 1.2, 'hallo')), 'permission'),
			array('getId:from=GET:act=[,do,all]', 'labelRuleGetId', array('from' => 'GET', 'act' => array(null, 'do', 'all')), 'label'),
		);
	}


	// butonRuleGenerate testing
	
	/**
	 * @dataProvider provideButtonRuleGenerate
	 */
	public function testButtonRuleGenerate($strButton, $strHref, $strLabel, $strTitle, $strIcon, $strAttributes, $arrAttributes, $expected, $expectedReturn=true, $arrRow=null)
	{
		$dc = new Netzmacht\Utils\DataContainer();
		
		$rule = static::getMethod('buttonRuleGenerate');
		$return = $rule->invokeArgs($dc, array(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow));
		$this->assertEquals($return, $expectedReturn);
		$this->assertAttributeEquals($expected,	'strGenerated', $dc);		
	}
	
	public function provideButtonRuleGenerate()
	{
		return array(
			// operations 
			array('test', 'http://www.test.de', 'Test', 'Umgebung', 'edit.gif', 'onclick="DoNothing();"', array('plain' => true), '<a href="http://www.test.de" title="Umgebung" onclick="DoNothing();"><img src="system/themes/default/images/edit.gif" width="12" height="16" alt="Test"></a> ', true, array(1)),
			array('test', 'act=edit', 'Test', 'Umgebung', 'edit.gif', '', array(), '<a href="'.\Environment::get('script').'?&amp;act=edit&amp;table=tl_data_container&amp;id=&amp;rt='.REQUEST_TOKEN.'" title="Umgebung"><img src="system/themes/default/images/edit.gif" width="12" height="16" alt="Test"></a> ', true, array(1)),
			array('test', 'act=edit', 'Test', 'Umgebung', 'edit.gif', '', array('noTable' => true), '<a href="'.\Environment::get('script').'?&amp;act=edit&amp;id=&amp;rt='.REQUEST_TOKEN.'" title="Umgebung"><img src="system/themes/default/images/edit.gif" width="12" height="16" alt="Test"></a> ', true, array(1)),
			array('test', 'act=edit', 'Test', 'Umgebung', 'edit.gif', '', array('noTable' => true, 'noId' => true), '<a href="'.\Environment::get('script').'?&amp;act=edit&amp;rt='.REQUEST_TOKEN.'" title="Umgebung"><img src="system/themes/default/images/edit.gif" width="12" height="16" alt="Test"></a> ', true, array(1)),
			array('test', 'act=edit', 'Test', 'Umgebung', 'notexists.gif', '', array('noTable' => true, 'noId' => true), '<a href="'.\Environment::get('script').'?&amp;act=edit&amp;rt='.REQUEST_TOKEN.'" title="Umgebung"></a> ', true, array(1)),
			array('test', 'act=edit', 'Test', 'Umgebung', 'edit_.gif', '', array('disable' => true), '<img src="system/themes/default/images/edit_.gif" width="12" height="16" alt="Test"> ', true, array(1)),
			
			// global operations
			array('test', 'act=edit', 'Test', 'Umgebung', 'header_all', '', array(), '<a href="'.\Environment::get('script').'?do=&amp;act=edit&amp;rt='.REQUEST_TOKEN.'" class="header_all" title="Umgebung">Test</a>', true),
			array('test', 'act=edit', 'Test', 'Umgebung', 'header_all', '', array('table' => true), '<a href="'.\Environment::get('script').'?do=&amp;act=edit&amp;table=tl_data_container&amp;rt='.REQUEST_TOKEN.'" class="header_all" title="Umgebung">Test</a>', true),
			array('test', 'act=edit', 'Test', 'Umgebung', 'header_all', '', array('table' => true, 'id' => true), '<a href="'.\Environment::get('script').'?do=&amp;act=edit&amp;table=tl_data_container&amp;id=&amp;rt='.REQUEST_TOKEN.'" class="header_all" title="Umgebung">Test</a>', true),
			array('test', 'http://www.test.de', 'Test', 'Umgebung', 'header_all', '', array('plain' => true), '<a href="http://www.test.de" class="header_all" title="Umgebung">Test</a>', true),
		);			
	}
	
	// butonRuleDisableIcon
	
	
	/**
	 * @dataProvider provideButtonRuleDisableIcon
	 */
	public function testButtonRuleDisableIcon($arrAttributes, $icon, $args)
	{
		$dc = new Netzmacht\Utils\DataContainer();
		$rule = static::getMethod('buttonRuleDisableIcon');
		
		$strButton = $strHref = $strLabel = $strTitle = $strAttributes = 'test';
		$strIcon = 'edit.gif';
		$arrRow = array(1);

		$rule->invokeArgs($dc, array(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow));
		$this->assertEquals($icon, $strIcon);
		$this->assertEquals($args, $arrAttributes);
	}
	
	public function provideButtonRuleDisableIcon()
	{
		return array(
			array(array('value' => false), 'edit_.gif', array('disable' => true, '__set__' => array('disable'), 'value' => false)),
			array(array('value' => false, 'icon' => 'disable.gif'), 'disable.gif', array('disable' => true, '__set__' => array('disable'), 'value' => false, 'icon' => 'disable.gif')),
			array(array('value' => true), 'edit.gif', array('value' => true)),
		);
	}
	
	
	// buttonRuleReferer	
	public function testButtonRuleReferer()
	{
		$dc = new Netzmacht\Utils\DataContainer();
		$rule = static::getMethod('buttonRuleReferer');		
		$rule->invokeArgs($dc, array(&$strButton, &$strHref, &$strLabel, &$strTitle, &$strIcon, &$strAttributes, &$arrAttributes, $arrRow));
		$this->assertEquals(array('plain' => true, '__set__' => array('plain')), $arrAttributes);
	}
	
	
	// helper
	
	protected static function getMethod($name) 
	{
	  $method  = new ReflectionMethod('Netzmacht\Utils\DataContainer', $name);
	  $method->setAccessible(true);
	  return $method;
	}
	
}
