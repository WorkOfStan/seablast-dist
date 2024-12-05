#!/bin/bash

# Directories where www-data MUST have permission to write (may be run by the standard user)
# chmod 2775 [filename or directory] - This sets the "setgid" bit (2), gives full permissions to the user and group (7), and read/execute permissions to others (5).
chmod 2775 cache
chmod 2775 log

# Legacy files (or those written by www-data or a standard user - e.g. PHPUnit) in those directories MUST have the right permissions to be writable (No legacy files: chmod: cannot access 'cache/*' ...)
# chmod 660 MUST be run by admin, not just a standard user
chmod 660 log/*.log

# Note: user running PHPUnit locally must have `umask 002` in their  `~/.bashrc`, `~/.zshrc`, or equivalent so that the created file inherits also g+w right.
