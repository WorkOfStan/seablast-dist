#!/bin/bash

# directories where www-data MUST have permission to write
# chmod 2775 [filename or directory] - This sets the "setgid" bit (2), gives full permissions to the user and group (7), and read/execute permissions to others (5).
chmod 2775 cache
chmod 2775 log
chmod 2775 uploads

# legacy files (or those written by www-data AND composerit - PHPUnut) in those directories MUST have the right permissions in order to be writable (No legacy files: chmod: cannot access 'cache/*' ...)
chmod 660 log/*.log
# chmod 660 MUST be run by admin not just composerit (TODO: what about chmod 2775 above?)
