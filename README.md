# Copy Email Alert Log to Field
A REDCap module to copy email alert log entries into a user-defined REDCap text field. All alerts tied to an event will appear in the corresponding field on that event.

![](img/comment_field.png)

## Prerequisites
- REDCap >= 9.3.5

## Installation
- Clone this repo into to `<redcap-root>/modules/copy_email_alert_log_to_field_v0.0.0`.
- Go to **Control Center > Manage External Modules** and enable Copy Email Alert Log to Field
- For each project you want to use this module, go to the project home page, click on **Manage External Modules** link, and then enable Copy Email Alert Log to Field for that project.

## Configuration

- **Field to pipe alert log**: Select a field to pipe email alert logs in to. The chosen field **must exist** in every **event** you have an alert set for to capture alerts for that event.
- **Search all alerts on next run**: Check this box to search the log of all alerts on the next run. Recommended to turn on only if enabling on a project which has already sent email alerts out. This turns itself off after running.
