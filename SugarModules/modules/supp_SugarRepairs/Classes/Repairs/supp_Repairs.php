<?php

abstract class supp_Repairs
{
    protected $loggerTitle = "Logger Title";
    protected $cycle_id = '';
    protected $isTesting = true; //always default to true
    protected $backupTables = array();

    /**
     * Construct to set the cycle id
     * supp_Repairs constructor.
     */
    function __construct()
    {
        $this->cycle_id = create_guid();
    }

    /**
     * Logger for repair actions
     * @param $message
     * @param string $level
     */
    protected function log($message, $level = 'info')
    {
        $log = "Sugar Repairs :: {$this->loggerTitle} :: {$message}";
        $GLOBALS['log']->{$level}("Sugar Repairs :: {$this->loggerTitle} :: {$message}");

        if (php_sapi_name() === 'cli') {
            if (
                isset($_SERVER['argv'][0])
                && (
                    strpos($_SERVER['argv'][0], 'phpunit') !== FALSE
                    || $_SERVER['argv'][0] == '/usr/bin/phpunit'
                )
            ) {
                //in phpunit tests
            } else {
                echo $log . "\n";
            }
        }
    }

    /**
     * Captures a database record
     * @param $cycle_id
     * @param $repair_type
     * @param $target_type
     * @param $target
     * @param $value_before
     * @param $value_after
     * @param $description
     * @param $status
     * @param $priority
     */
    protected function capture($cycle_id, $repair_type, $target_type, $target, $value_before, $value_after, $description, $status, $priority)
    {
        $sugarRepair = BeanFactory::newBean('supp_SugarRepairs');
        $sugarRepair->name = $target;
        $sugarRepair->cycle_id = $cycle_id;
        $sugarRepair->type = $repair_type;
        $sugarRepair->target_type = $target_type;
        $sugarRepair->target = $target;
        $sugarRepair->value_before = $value_before;
        $sugarRepair->value_after = $value_after;
        $sugarRepair->description = $description;
        $sugarRepair->status = $status;
        $sugarRepair->priority = $priority;
        $sugarRepair->save();
    }

    /**
     * Returns the custom language directories in Sugar.
     * @return string
     */
    protected function getLangRegex()
    {
        $langRegexes = array(
            //include
            '(\\/|\\\)custom(\\/|\\\)include(\\/|\\\)language(\\/|\\\)(.*?)\.lang.php$',
            //application extensions
            '(\\/|\\\)custom(\\/|\\\)Extension(\\/|\\\)application(\\/|\\\)Ext(\\/|\\\)Language(\\/|\\\)(.*?)\.php$',
            //module extensions
            '(\\/|\\\)custom(\\/|\\\)Extension(\\/|\\\)modules(\\/|\\\)(.*?)(\\/|\\\)Ext(\\/|\\\)Language(\\/|\\\)(.*?)\.php$',
            //module builder application
            '(\\/|\\\)custom(\\/|\\\)modulebuilder(\\/|\\\)packages(\\/|\\\)(.*?)(\\/|\\\)language(\\/|\\\)application(\\/|\\\)(.*?)\.lang.php$',
            //module builder modules
            '(\\/|\\\)custom(\\/|\\\)modulebuilder(\\/|\\\)packages(\\/|\\\)(.*?)(\\/|\\\)modules(\\/|\\\)(.*?)(\\/|\\\)language(\\/|\\\)(.*?)\.lang.php$',
            //custom modules
            '(\\/|\\\)custom(\\/|\\\)modules(\\/|\\\)(.*?)(\\/|\\\)language(\\/|\\\)(.*?)\.lang.php$',
        );

        return '/(' . implode(')|(', $langRegexes) . ')/';
    }

    /**
     * Returns the custom language directories in Sugar.
     * @return string
     */
    protected function getVardefRegex()
    {
        $langRegexes = array(
            //module extensions
            '(\\/|\\\)custom(\\/|\\\)Extension(\\/|\\\)modules(\\/|\\\)(.*?)(\\/|\\\)Ext(\\/|\\\)Vardefs(\\/|\\\)(.*?)\.php$',
            //module builder application
            '(\\/|\\\)custom(\\/|\\\)modulebuilder(\\/|\\\)packages(\\/|\\\)(.*?)(\\/|\\\)modules(\\/|\\\)(.*?)(\\/|\\\)vardefs.php$',
            //custom modules
            '(\\/|\\\)custom(\\/|\\\)modules(\\/|\\\)(.*?)(\\/|\\\)vardefs.php$',
        );


        return '/(' . implode(')|(', $langRegexes) . ')/';
    }

    /**
     * Determines if we've already backed up a table
     * @param $table
     * @return bool
     */
    protected function isBackedUpTable($table)
    {
        if (in_array($table, $this->backupTables)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Backs up a database table
     * @param $table
     */
    protected function backupTable($table, $stamp = '')
    {
        if ($this->isTesting) {
            return true;
        }

        if (empty($stamp)) {
            $stamp = time();
        }

        $this->log("Backing up {$table}...");
        $backupTable = preg_replace('{/$}', '', "{$table}_{$stamp}");

        global $sugar_config;

        $maxTableLength = 128;
        if ($sugar_config['dbconfig']['db_type'] == 'mysql') {
            $maxTableLength = 64;
        } else if ($sugar_config['dbconfig']['db_type'] == 'oci8') {
            $maxTableLength = 30;
        } else if ($sugar_config['dbconfig']['db_type'] == 'mssql') {
            $maxTableLength = 128;
        } else if ($sugar_config['dbconfig']['db_type'] == 'ibm_db2') {
            $maxTableLength = 128;
        }

        if (strlen($backupTable) > $maxTableLength) {
            //limit table name - max length for oracle is 30
            $backupTable = substr($backupTable, 0, $maxTableLength);
        }

        $result = $GLOBALS['db']->tableExists($backupTable);
        if ($result) {
            $this->log("Database table '{$backupTable}' already exists. Renaming.");
            $backupTable . '_' . time();
        }

        if ($sugar_config['dbconfig']['db_type'] == 'mysql') {
            $GLOBALS['db']->query("CREATE TABLE {$backupTable} LIKE {$table}");
            $GLOBALS['db']->query("INSERT {$backupTable} SELECT * FROM {$table}");
        } else if ($sugar_config['dbconfig']['db_type'] == 'oci8') {
            $GLOBALS['db']->query("CREATE TABLE {$backupTable} AS SELECT * {$table}");
        } else if ($sugar_config['dbconfig']['db_type'] == 'mssql') {
            $GLOBALS['db']->query("SELECT * INTO {$backupTable} FROM {$table}");
        } else if ($sugar_config['dbconfig']['db_type'] == 'ibm_db2') {
            $GLOBALS['db']->query("CREATE TABLE {$backupTable} LIKE {$table}");
            $GLOBALS['db']->query("INSERT INTO {$backupTable} (SELECT * FROM {$table})");
        } else {
            $this->log("Database type '{$sugar_config['dbconfig']['db_type']}' not yet supported.");
            return false;
        }

        //capture list
        $this->backupTables[$backupTable] = $table;

        $result = $GLOBALS['db']->tableExists($backupTable);
        if ($result) {
            $this->log("Created {$backupTable} from {$table}.");
        } else {
            $this->log("Could not create {$backupTable} from {$table}.");
        }

        return $result;
    }

    /**
     * Fetches all custom language files
     */
    protected function getCustomLanguageFiles()
    {
        $path = realpath('custom');

        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);

        $filter = new RegexIterator($iterator, $this->getLangRegex());
        $fileList = array();
        foreach ($filter as $file) {
            $fullPath = $file->__toString();
            $relativePath = str_replace(realpath(''), '', $fullPath);

            //we need to order files by date modified
            $fileList[$file->getMTime()][$fullPath] = $relativePath;
        }

        ksort($fileList);
        $fileList = call_user_func_array('array_merge', $fileList);

        return $fileList;
    }

    /**
     * Fetches all custom language files
     */
    protected function getCustomVardefFiles()
    {
        $path = realpath('custom');

        $directory = new RecursiveDirectoryIterator($path);
        $iterator = new RecursiveIteratorIterator($directory);

        $filter = new RegexIterator($iterator, $this->getVardefRegex());
        $fileList = array();
        foreach ($filter as $file) {
            $fullPath = $file->__toString();
            $relativePath = str_replace(realpath(''), '', $fullPath);

            //we need to order files by date modified
            $fileList[$file->getMTime()][$fullPath] = $relativePath;
        }

        ksort($fileList);
        $fileList = call_user_func_array('array_merge', $fileList);

        return $fileList;
    }

    /**
     * Runs a QRAR against the instance
     */
    public function runQRAR()
    {
        require_once('modules/Administration/QuickRepairAndRebuild.php');
        $RAC = new RepairAndClear();
        $actions = array('clearAll');
        $RAC->repairAndClearAll($actions, array('All Modules'), false, false);
    }

    public function runRebuildWorkflow()
    {
        require_once('include/workflow/plugin_utils.php');

        global $beanFiles;
        global $mod_strings;
        global $db;

        $workflow_object = new WorkFlow();


        $module_array = $workflow_object->get_module_array();

        foreach ($module_array as $key => $module) {
            $dir = "custom/modules/" . $module . "/workflow";
            if (file_exists($dir)) {
                if ($elements = glob($dir . "/*")) {
                    foreach ($elements as $element) {
                        is_dir($element) ? remove_workflow_dir($element) : unlink($element);
                    }
                }
            }
        }

        $workflow_object->repair_workflow();
        build_plugin_list();
    }

    /**
     * Identifies syntax errors
     * @param $code
     * @return mixed|string
     */
    protected function testPHPSyntax($code)
    {
        $braces = 0;
        $inString = 0;

        // First of all, we need to know if braces are correctly balanced.
        // This is not trivial due to variable interpolation which
        // occurs in heredoc, backticked and double quoted strings
        foreach (token_get_all($code) as $token) {
            if (is_array($token)) {
                switch ($token[0]) {
                    case T_CURLY_OPEN:
                    case T_DOLLAR_OPEN_CURLY_BRACES:
                    case T_START_HEREDOC:
                        ++$inString;
                        break;
                    case T_END_HEREDOC:
                        --$inString;
                        break;
                }
            } else if ($inString & 1) {
                switch ($token) {
                    case '`':
                    case '"':
                        --$inString;
                        break;
                }
            } else {
                switch ($token) {
                    case '`':
                    case '"':
                        ++$inString;
                        break;

                    case '{':
                        ++$braces;
                        break;
                    case '}':
                        if ($inString) --$inString;
                        else {
                            --$braces;
                            if ($braces < 0) break 2;
                        }

                        break;
                }
            }
        }

        // Display parse error messages and use output buffering to catch them
        $inString = @ini_set('log_errors', false);
        $token = @ini_set('display_errors', true);
        ob_start();

        // If $braces is not zero, then we are sure that $code is broken.
        // We run it anyway in order to catch the error message and line number.

        // Else, if $braces are correctly balanced, then we can safely put
        // $code in a dead code sandbox to prevent its execution.
        // Note that without this sandbox, a function or class declaration inside
        // $code could throw a "Cannot redeclare" fatal error.

        $braces || $code = "if(0){{$code}\n}";

        $code = str_replace("<?php", "", $code);
        $code = str_replace("?>", "", $code);

        if (false === eval($code)) {
            if ($braces) {
                $braces = PHP_INT_MAX;
            } else {
                // Get the maximum number of lines in $code to fix a border case
                false !== strpos($code, "\r") && $code = strtr(str_replace("\r\n", "\n", $code), "\r", "\n");
                $braces = substr_count($code, "\n");
            }

            $code = ob_get_clean();
            $code = strip_tags($code);

            // Get the error message and line number
            if (preg_match("'syntax error, (.+) in .+ on line (\d+)$'s", $code, $code)) {
                $code[2] = (int)$code[2];
                $code = $code[2] <= $braces
                    ? array($code[1], $code[2])
                    : array('unexpected $end' . substr($code[1], 14), $braces);
            } else {
                $code = array('syntax error', 0);
            }
        } else {
            ob_end_clean();
            $code = false;
        }

        @ini_set('display_errors', $token);
        @ini_set('log_errors', $inString);

        return $code;
    }

    /**
     * Determines is the edition is CE
     * @return bool
     */
    public function isCE()
    {
        return $GLOBALS['sugar_flavor'] == 'CE';
    }

    /**
     * Determines is the edition is Professional
     * @return bool
     */
    public function isPro()
    {
        return $GLOBALS['sugar_flavor'] == 'PRO';
    }

    /**
     * Determines is the edition is Corporate
     * @return bool
     */
    public function isCorp()
    {
        return $GLOBALS['sugar_flavor'] == 'CORP';
    }

    /**
     * Determines is the edition is Enterprise
     * @return bool
     */
    public function isEnt()
    {
        return $GLOBALS['sugar_flavor'] == 'ENT';
    }

    /**
     * Determines is the edition is Ultimate
     * @return bool
     */
    public function isUlt()
    {
        return $GLOBALS['sugar_flavor'] == 'ULT';
    }

    /**
     * Allows a developer to toggle the isTesting flag
     * @param $isTesting
     */
    public function setTesting($isTesting)
    {
        $this->isTesting = $isTesting;
    }

    /**
     * Prefixes a given report with Broken: to let users know problematic reports
     * @param $id
     */
    public function markReportBroken($id)
    {
        $message = "Broken: ";

        $savedReport = BeanFactory::getBean('Reports', $id);

        if (!$this->isTesting) {
            if (substr($savedReport->name, 0, strlen($message)) !== $message) {
                $this->log("Marking report '{$savedReport->name}' ({$savedReport->id}) as broken.");
                $savedReport->name = $message . $savedReport->name;
                $savedReport->save();
            } else {
                $this->log("Report '{$savedReport->name}' ({$savedReport->id}) is already marked as broken.");
            }
        } else {
            $this->log("Will mark report '{$savedReport->name}' ({$savedReport->id}) as broken.");
        }
    }

    /**
     * Disables a specific workflow
     * @param $id
     */
    public function disableWorkflow($id)
    {
        $workflow = BeanFactory::getBean('WorkFlow', $id);

        if ($workflow->status != 0) {

            if (!$this->isTesting) {
                $this->log("Disabling workflow '{$workflow->name}' ({$id})...");
                $workflow->status = 0;
                $workflow->save();
            } else {
                $this->log("Will disable workflow '{$workflow->name}' ({$id}).");
            }
        } else {
            $this->log("Workflow '{$workflow->name}' ({$id}) is already disabled.");
        }
    }

    /**
     * Returns the field def for a specific field
     * @param $module
     * @param $field
     * @return bool
     */
    public function getFieldDefinition($module, $field)
    {
        $bean = BeanFactory::getBean($module);

        if (isset($bean->field_defs[$field])) {
            return $bean->field_defs[$field];
        } else {
            $this->log("The field '{$field}' was not found on the '{$module}' module. It may have been deleted.");
            return false;
        }
    }

    /**
     * Determines a fields type
     * @param $module
     * @param $field
     */
    public function getFieldType($module, $field)
    {
        $def = $this->getFieldDefinition($module, $field);
        if ($def && isset($def['type'])) {
            return $def['type'];
        } else {
            $this->log("Type definition not found for {$module} / {$field}");
            return false;
        }
    }

    /**
     * Returns the option keys for a list
     * @param $module
     * @param $field
     * @return array|bool
     */
    public function getFieldOptionKeys($module, $field)
    {
        $definition = $this->getFieldDefinition($module, $field);

        $listName = '';
        if ($definition && isset($definition['options'])) {
            $listName = $definition['options'];
        } else {
            $this->log("No options list found for {$module} / {$field}: " . print_r($definition, true));
            return false;
        }

        $app_list_strings = return_app_list_strings_language('en_us');

        if (isset($app_list_strings[$listName])) {
            $this->log("Found list '{$listName}' for {$module} / {$field}.");
            $list = array_keys($app_list_strings[$listName]);
            return $list;
        } else {
            $this->log("The list '{$listName}' was not found");
        }
    }

    /**
     * Returns valid key names give a string
     * @param $key
     * @return mixed
     */
    public function getValidLanguageKeyName($key)
    {
        //Now go through and remove the characters [& / - ( )] and spaces (in some cases) from array keys
        $badChars = array(' & ', '&', ' - ', '-', ' / ', '/', '(', ')');
        $goodChars = array('_', '_', '_', '_', '_', '_', '', '');
        $newKey = str_replace($badChars, $goodChars, $key, $count);
        return $newKey;
    }

    /**
     * Executes the repairs
     * @param array $args
     */
    public function execute(array $args)
    {
        if (isset($args['t']) && ($args['t'] == 'false' || $args['t'] == '0' || $args['t'] == false)) {
            $this->setTesting(false);
        }

        if ($this->isTesting) {
            $this->log("Running in test mode.");
        }
    }
}