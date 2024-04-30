#!/bin/bash

# directories where www-data MUST have permission to write
# chmod 2775 [filename or directory] - This sets the "setgid" bit (2), gives full permissions to the user and group (7), and read/execute permissions to others (5).
chmod 2775 cache
chmod 2775 log
