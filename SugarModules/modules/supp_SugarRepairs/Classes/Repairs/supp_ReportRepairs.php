<?php

require_once('modules/supp_SugarRepairs/Classes/Repairs/supp_Repairs.php');
require_once('modules/Reports/templates/templates_reports.php');
require_once('modules/Reports/Report.php');

class supp_ReportRepairs extends supp_Repairs
{
    protected $loggerTitle = "Reports";
    protected $foundIssues = array();

    function __construct()
    {
        parent::__construct();
    }

    /**
     * Iterates the filter
     * @param $filters
     */
    public function repairFilters(&$filters, $report, $allFields)
    {
        for ($i = 0; $i < count($filters) - 1; $i++) {
            if (isset($filters[$i]['operator'])) {
                $this->repairFilters($filters[$i], $report, $allFields);
            } else {

                $module = $report->module;
                $field = $filters[$i]['name'];
                if (isset($filters[$i]['table_key']) && $filters[$i]['table_key'] !== 'self') {

                    $fieldKey = $filters[$i]['table_key'] . ":" . $field;
                    if (isset($allFields[$fieldKey]['module'])) {
                        $module = $allFields[$fieldKey]['module'];
                    } else {
                        $this->log("Report '{$report->name}' ({$report->id}) has a filter with an invalid mapping key of '{$fieldKey}'. The field {$field} may have been deleted.");
                        $this->foundIssues[$report->id] = $report->id;
                        $this->markReportBroken($report->id);
                        continue;
                    }
                }

                $type = $this->getFieldType($module, $field);

                if ($type) {

                    if (
                        in_array($type, array('enum', 'multienum'))
                        && isset($filters[$i]['qualifier_name'])
                        && in_array($filters[$i]['qualifier_name'], array('is', 'is_not', 'one_of', 'not_one_of'))
                    ) {
                        $listKeys = $this->getFieldOptionKeys($module, $field);
                        $selectedKeys = unencodeMultienum($filters[$i]['input_name0']);
                        $modifiedSelectedKeys = $selectedKeys;
                        foreach ($selectedKeys as $id => $selectedKey) {
                            $issue = false;
                            if (!in_array($selectedKey, $listKeys)) {
                                $issue = true;
                            }
                            if ($issue) {
                                $testKey = $this->getValidLanguageKeyName($selectedKey);
                                //try to fix the key if it was updated in the lang repair script
                                if ($testKey !== $selectedKey) {
                                    if (in_array($testKey, $listKeys)) {
                                        $issue = false;
                                        $modifiedSelectedKeys[$id] = $testKey;
                                        if (!$this->isTesting) {
                                            $this->log("Report '{$report->name}' ({$report->id}) has an invalid key '{$selectedKey}' that was updated to '{$testKey}'. Allowed keys for {$module} / {$field} are: " . print_r($listKeys, true));
                                        } else {
                                            $this->log("Report '{$report->name}' ({$report->id}) has an invalid key '{$selectedKey}' that will be updated to '{$testKey}'. Allowed keys for {$report->name} / {$field} are: " . print_r($listKeys, true));
                                        }
                                    }
                                }
                            }
                            if ($issue) {
                                $this->log("Report '{$report->name}' ({$report->id}) has an action with an invalid key '{$selectedKey}'. Allowed keys for {$module} / {$field} are: " . print_r($listKeys, true));
                                $this->foundIssues[$report->id] = $report->id;
                                $this->markReportBroken($report->id);
                            }
                        }
                        if ($modifiedSelectedKeys !== $selectedKeys) {
                            $filters[$i]['input_name0'] = array_values($modifiedSelectedKeys);
                        }
                    }


                } else {
                    $this->log("Report '{$report->name}' ({$report->id}) has a filter with a deleted or missing field on {$module} / {$field}");
                    $this->foundIssues[$report->id] = $report->id;
                    $this->markReportBroken($report->id);
                }
            }
        }
    }

    /**
     * Repairs various issues in reports
     */
    public function repairReports()
    {
        $_REQUEST['module'] = 'supp_SugarRepairs'; //hack to prevent the reports module from rebuilding the language files
        $sql = "SELECT id FROM saved_reports WHERE deleted = 0";
        //$sql = "SELECT id FROM saved_reports WHERE id = '1433867d-f97a-020e-da67-56d531e415d3' AND deleted = 0";

        $result = $GLOBALS['db']->query($sql);

        $this->foundIssues = array();
        $jsonObj = getJSONobj();
        while ($row = $GLOBALS['db']->fetchByAssoc($result)) {
            $savedReport = BeanFactory::getBean('Reports', $row['id']);
            $beforeJson = html_entity_decode($savedReport->content);
            $report = new Report($beforeJson);
            $report->id = $savedReport->id; //hack to pass id
            $content = $jsonObj->decode($beforeJson, false);

            $this->log("Processing report '{$savedReport->name}' ({$savedReport->id})...");
            //print_r($content['filters_def']['Filter_1']);
            if (isset($content['filters_def']) && isset($content['filters_def']['Filter_1']) && !empty($content['filters_def']['Filter_1'])) {
                $this->repairFilters($content['filters_def']['Filter_1'], $savedReport, $report->all_fields);
            } else {
                $this->log("-> Skipping filter repair as no filter def was found.");
                continue;
            }

            $afterJson = $jsonObj->encode($content, false);

            if ($beforeJson !== $afterJson) {
                $this->foundIssues[$savedReport->id] = $savedReport->id;
                $this->log("before " . $beforeJson);
                $this->log("After " . $afterJson);

                if (!$this->isTesting) {
                    $this->log("Updating report '{$savedReport->name}' ({$savedReport->id}).");
                    $savedReport->content = $afterJson;
                    $savedReport->save();
                } else {
                    $this->log("Will update '{$savedReport->name}' ({$savedReport->id}).");
                }
            }

            $this->log("-> Report scan complete.");
        }

        unset($_REQUEST['module']); //removing hack to prevent the reports module from rebuilding the language files
        $foundIssuesCount = count($this->foundIssues);
        $this->log("Found {$foundIssuesCount} bad reports.");
    }


    /**
     * Executes the TeamSet repairs
     * @param array $args
     */
    public function execute(array $args)
    {
        if ($this->isCE()) {
            $this->log('Repair ignored as it does not apply to CE');
            return false;
        }

        //check for testing an other reapir generic params
        parent::execute($args);

        $stamp = time();

        if (
            $this->backupTable('saved_reports', $stamp)
            && $this->backupTable('report_cache', $stamp)
        ) {
            $this->repairReports();
        }
    }

}
