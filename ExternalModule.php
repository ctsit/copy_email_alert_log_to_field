<?php

namespace CopyEmailAlert\ExternalModule;

use ExternalModules\AbstractExternalModule;

use REDCap;
use Records;

class ExternalModule extends AbstractExternalModule {

    protected function includeJs($path) {
        echo '<script src="' . $this->getUrl($path) . '"></script>';
    }

    protected function setJsSettings($settings) {
        echo '<script>copyEmailAlerts = ' . json_encode($settings) . ';</script>';
    }

    function addEmailAlertsToAllProjectComments() {
        $projects = $this->framework->getProjectsWithModuleEnabled();
        foreach($projects as $project_id) {
            $this->addEmailAlertsToProjectComments($project_id);
        }
    }

    function addEmailAlertsToProjectComments($project_id) {
        // TODO: hack in configurable frequency via round(now()) modulo freq and a return
        $sql = "SELECT record, event_id, alert_title, last_sent FROM (
                (SELECT * FROM `redcap_alerts`
                    WHERE
                    project_id = $project_id
                    ";
        $sql .= ($this->framework->getProjectSetting('search_all_alerts', $project_id)) ?
            $this->setProjectSetting('search_all_alerts', null, $project_id) : // turn off full search (returns null)
            "AND email_timestamp_sent >= NOW() - INTERVAL 8 MINUTE"; // this cron runs every minute with a max of runtime of 6 minutes
        $sql .= ") as ra
                INNER JOIN redcap_alerts_sent AS ras ON ras.alert_id = ra.alert_id
            );";
        $result = $this->framework->query($sql);
        $results = $result->fetch_all(MYSQLI_ASSOC);

        if (empty($results)) return;

        // TODO: upgrade to Sets if/when available
        $records = [];
        $events = [];
        foreach($results as $result) {
            array_push($records, $result['record']);
            array_push($events, $result['event_id']);
        }

        $alerts_field = $this->getProjectSetting('alerts_comment_field', $project_id);
        $get_data = [
            'project_id' => $project_id,
            'records' => array_values($records),
            'events' => array_values($events),
            'fields' => [$alerts_field]
            ];

        $redcap_data = \REDCap::getData($get_data);

        foreach ($results as $result) {
            $record = $result['record'];
            $event_id = $result['event_id'];

            $current_comment = $redcap_data[$record][$event_id][$alerts_field];
            $alert_comment = $result['alert_title'] . " alert email sent: " . $result['last_sent'];;
            if (strpos($current_comment, $alert_comment) !== FALSE) continue; // https://www.php.net/manual/en/function.strpos.php

            if (empty($current_comment)) {
                $redcap_data[$record][$event_id] = [
                    $alerts_field => $alert_comment
                ];
            } else {
                // there are already comments and they aren't this alert, append the alert
                $redcap_data[$record][$event_id] = [
                    $alerts_field => $current_comment . "\n" . $alert_comment
                ];
            }
        }

        \REDCap::saveData($project_id, 'array', $redcap_data);
    }

}
