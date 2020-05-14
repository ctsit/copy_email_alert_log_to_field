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
        // hack in frequency via round(now()) modulo freq
        $sql = "SELECT record, event_id, alert_title, last_sent FROM (
                (SELECT * FROM `redcap_alerts`
                    WHERE
                    project_id = $project_id
                 ) as ra
                INNER JOIN redcap_alerts_sent AS ras ON ras.alert_id = ra.alert_id
            );";
            // grab only now() - <cron_interval> alerts
            // wouldn't catch before module turn-on, make manual switch
            // initial config: look into past, change projectSetting turn itself back off
        $result = $this->framework->query($sql);
        $results = $result->fetch_all(MYSQLI_ASSOC);

        if (empty($results)) return;

        $records = [];
        $events = [];
        foreach($results as $result) {
            array_push($records, $result['record']);
            array_push($events, $result['event_id']);
            array_push($alert_titles, $result['alert_title']);
        }


        $get_data = [
            'project_id' => $project_id,
            'records' => array_values($records),
            'events' => array_values($events),
            'fields' => ['result_comments'] // $this->framework->getProjectSetting('comment_field');
            ];

        $redcap_data = \REDCap::getData($get_data);


        foreach ($results as $result) {
            //if $result['last_sent'] in result_comments; continue
            $alert_comment = $result['alert_title'] . " alert email sent: ";
            $record = $result['record'];
            $event_id = $result['event_id'];
            $alert_comment .= $result['last_sent'];
            $current_comment = $redcap_data[$record][$event_id]['result_comments']; // configurable at the project level
            if (empty($current_comment)) {
                $redcap_data[$record][$event_id] = [
                    'result_comments' => $alert_comment
                ];
            } else if (strpos($current_comment, $alert_comment) === FALSE) { // https://www.php.net/manual/en/function.strpos.php
                // if there are already comments and they aren't this alert, append the alert
                $redcap_data[$record][$event_id] = [
                    'result_comments' => $current_comment . '\n' . $alert_comment
                ];
            } else {
                // prevent from re-writing to the field again
                // revisit
                //unset($redcap_data[$record][$event_id]);
            }
        }

        \REDCap::saveData($project_id, 'array', $redcap_data);
    }

}
