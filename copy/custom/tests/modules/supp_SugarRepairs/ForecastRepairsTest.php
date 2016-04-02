<?php

require_once('modules/supp_SugarRepairs/Classes/Repairs/supp_ForecastWorksheetRepairs.php');

/**
 * @group support
 * @group forecastRepairs
 */
class suppSugarRepairsForecastWorksheetRepairsTest extends Sugar_PHPUnit_Framework_TestCase
{

    protected $reportIDs = array();

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        $bean = BeanFactory::newBean("Users");
        $bean->id = '38c90c70-7788-13a2-668d-513e2b8df5e1';
        $bean->new_with_id = true;
        $bean->first_name = 'Manager';
        $bean->first_name = 'Test';
        $bean->user_name = 'mtest';
        $bean->save();

        $bean = BeanFactory::newBean("Users");
        $bean->id = '2c78445c-f795-11e5-9b16-a19e342a368f';
        $bean->new_with_id = true;
        $bean->first_name = 'Worker';
        $bean->first_name = 'Test';
        $bean->user_name = 'wtest';
        $bean->reports_to_id = '38c90c70-7788-13a2-668d-513e2b8df5e1';
        $bean->save();

        $sql_setup[] = "CREATE TABLE forecast_manager_worksheets_repairTemp LIKE forecast_manager_worksheets;";
        $sql_setup[] = "INSERT forecast_manager_worksheets_repairTemp SELECT * FROM forecast_manager_worksheets;";
        $sql_setup[] = "DELETE FROM forecast_manager_worksheets;";

        $sql_setup[] = "
            INSERT INTO forecast_manager_worksheets(id,timeperiod_id) 
            VALUES ('6b542c24-f79d-11e5-9b16-a19e342a368f','736d000c-f79d-11e5-9b16-a19e342a368f')
        ";

        foreach ($sql_setup as $q_setup) {
            $res = $GLOBALS['db']->query($q_setup);
        }
    }

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();

        $sql_teardown[] = "
            DELETE FROM users
            WHERE id in (
                '38c90c70-7788-13a2-668d-513e2b8df5e1',
                '2c78445c-f795-11e5-9b16-a19e342a368f'
            )
        ";

        // $sql_teardown[] = "DELETE FROM forecast_manager_worksheets;";
        // $sql_teardown[] = "INSERT forecast_manager_worksheets SELECT * FROM forecast_manager_worksheets_repairTemp;";
        // $sql_teardown[] = "DROP TABLE forecast_manager_worksheets_repairTemp;";

        foreach ($sql_teardown as $q_teardown) {
            $res = $GLOBALS['db']->query($q_teardown);
        }
    }

    /**
     * Test for returning time period ids
     * @covers supp_ForecastWorksheetRepairs::getAllTimePeriodIds
     */
    public function testGetAllTimePeriodIds()
    {
        $repairs = new supp_ForecastWorksheetRepairs();
        $repairs->setTesting(false);
        $result = $repairs->getAllTimePeriodIds();

        $this->assertTrue(is_array($result));
        $this->assertGreaterThan(0,count($result));
    }

    /**
     * Test for returning level 1 managers
     * @covers supp_ForecastWorksheetRepairs::getLevelOneManagers
     */
    public function testGetLevelOneManagers()
    {
        $repairs = new supp_ForecastWorksheetRepairs();
        $repairs->setTesting(false);
        $result = $repairs->getLevelOneManagers();

        $this->assertTrue(is_array($result));
        $this->assertGreaterThan(0,count($result));

        $this->assertTrue(is_array($repairs->usersToProcess));
        $this->assertGreaterThan(0,count($repairs->usersToProcess));

        $this->assertArrayHasKey('38c90c70-7788-13a2-668d-513e2b8df5e1',$repairs->usersToProcess[1]);
    }

    /**
     * Test for returning level workers to managers
     * @covers supp_ForecastWorksheetRepairs::getNextLevelUsersByManager
     */
    public function testGetNextLevelUsersByManager()
    {
        $repairs = new supp_ForecastWorksheetRepairs();
        $repairs->setTesting(false);
        $repairs->getNextLevelUsersByManager(2,array('38c90c70-7788-13a2-668d-513e2b8df5e1'));

        $this->assertTrue(is_array($repairs->usersToProcess));
        $this->assertGreaterThan(0,count($repairs->usersToProcess));

        $this->assertArrayHasKey('2c78445c-f795-11e5-9b16-a19e342a368f',$repairs->usersToProcess[2]);
    }

    /**
     * Test for clearing the forecast_manager_worksheets table
     * @covers supp_ForecastWorksheetRepairs::clearForecastWorksheet
     */
    public function testClearForecastWorksheet()
    {
        $repairs = new supp_ForecastWorksheetRepairs();
        $repairs->setTesting(false);
        $results = $repairs->clearForecastWorksheet('736d000c-f79d-11e5-9b16-a19e342a368f');

        $this->assertEquals(1,$results['affected_row_count']);

        $sql = "
            SELECT id
            FROM forecast_manager_worksheets
        ";
        $result = $GLOBALS['db']->query($sql);
        $affected_row_count =  $GLOBALS['db']->getAffectedRowCount($result);

        $this->assertEquals(0,$affected_row_count);
    }

    /**
     * Test for running the whole shebang!
     * @covers supp_ForecastWorksheetRepairs::repairForecastWorksheets
     */
    public function testRepairForecastWorksheets()
    {
        $repairs = new supp_ForecastWorksheetRepairs();
        $repairs->setTesting(false);

        $repairs->repairForecastWorksheets();

        $sql = "
            SELECT id
            FROM forecast_manager_worksheets
        ";
        $result = $GLOBALS['db']->query($sql);
        $affected_row_count =  $GLOBALS['db']->getAffectedRowCount($result);

        $this->assertGreaterThan(0,$affected_row_count);

    }
}
