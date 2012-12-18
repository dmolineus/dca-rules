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
 
// fake class for Contao 2.x backwards compatibility
require_once TL_ROOT . '/system/modules/dca-rules/classes/DataContainer.php';

/*
 * in Contao 2.x you have to use DataContainerRules
 */
class DataContainerRules extends Netzmacht\Utils\DataContainer
{
}
