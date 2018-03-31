<?php

/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

/**
 * Class ilTestParticipantsGUI
 *
 * @author    Björn Heyser <info@bjoernheyser.de>
 * @version    $Id$
 *
 * @package    Modules/Test(QuestionPool)
 * 
 * @ilCtrl_Calls ilTestParticipantsGUI: ilTestFixedParticipantsGUI
 * @ilCtrl_Calls ilTestParticipantsGUI: ilTestParticipantsTimeExtensionGUI
 */
class ilTestParticipantsGUI
{
	/**
	 * @var ilObjTest
	 */
	protected $testObj;
	
	/**
	 * @var ilTestAccess
	 */
	protected $testAccess;
	
	/**
	 * @var ilTestTabsManager
	 */
	protected $testTabs;
	
	/**
	 * ilTestParticipantsGUI constructor.
	 * @param ilObjTest $testObj
	 */
	public function __construct(ilObjTest $testObj)
	{
		$this->testObj = $testObj;
	}
	
	/**
	 * @return ilTestAccess
	 */
	public function getTestAccess()
	{
		return $this->testAccess;
	}
	
	/**
	 * @param ilTestAccess $testAccess
	 */
	public function setTestAccess($testAccess)
	{
		$this->testAccess = $testAccess;
	}
	
	/**
	 * @return ilTestTabsManager
	 */
	public function getTestTabs()
	{
		return $this->testTabs;
	}
	
	/**
	 * @param ilTestTabsManager $testTabs
	 */
	public function setTestTabs($testTabs)
	{
		$this->testTabs = $testTabs;
	}
	
	/**
	 * Execute Command
	 */
	public function	executeCommand()
	{
		global $DIC; /* @var ILIAS\DI\Container $DIC */
		
		if( !$this->getTestAccess()->checkManageParticipantsAccess() )
		{
			ilObjTestGUI::accessViolationRedirect();
		}
		
		$this->getTestTabs()->activateTab(ilTestTabsManager::TAB_ID_PARTICIPANTS);
		$this->getTestTabs()->getParticipantsSubTabs();
		
		switch( $DIC->ctrl()->getNextClass() )
		{
			case 'iltestfixedparticipantsgui':
				
				$this->getTestTabs()->activateSubTab(ilTestTabsManager::SUBTAB_ID_FIXED_PARTICIPANTS);
				
				require_once 'Modules/Test/classes/class.ilTestFixedParticipantsGUI.php';
				$gui = new ilTestFixedParticipantsGUI($this->testObj);
				$gui->setTestAccess($this->getTestAccess());
				$DIC->ctrl()->forwardCommand($gui);
				break;
				
			case 'iltestparticipantstimeextensiongui':
				
				$this->getTestTabs()->activateSubTab(ilTestTabsManager::SUBTAB_ID_TIME_EXTENSION);
				
				require_once 'Modules/Test/classes/class.ilTestParticipantsTimeExtensionGUI.php';
				$gui = new ilTestParticipantsTimeExtensionGUI($this->testObj);
				$gui->setTestAccess($this->getTestAccess());
				$DIC->ctrl()->forwardCommand($gui);
				break;
		}
	}
}