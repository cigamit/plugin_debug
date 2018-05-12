## Plugin Debug
This plugin currently allows you to select a Data Source and have the Poller run various checks against it to determine whether it is encountering any issues.
Current checks are
* RRD Owner
* Poller runs as
* Is RRA Folder writeable by poller?
* Is RRD writeable by poller?
* Does the RRD Exist?
* Is the Data Source set as Active?
* Did the poller receive valid data?
* Was the RRD File updated?		
* Were we able to convert the title?
* Does the RRA Profile match the RRD File structure?

In the future, this plugin will be expanded to troubleshoot other issues within Cacti.

## Installation

To install the plugin, simply copy the plugin_debug directory to Cacti's plugins directory and rename it to simply 'debug'. Once this is complete, goto Cacti's Plugin Management section, and Install and Enable the plugin. Once this is complete, you can grant users permission to use the plugin (admin is granted permissions by default).

After you have completed that, you should goto 'Debug' on the Console Menu under 'Utilities'.  Click 'Add' to add a new Data Source to test.

## Releases

--- 0.2 ---
* issue: debug table not created automatically
* issue: multiple issues not properly handled

--- 0.1 ---
Initial version
