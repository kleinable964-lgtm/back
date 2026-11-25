@echo off
setlocal
rem Child processes, including PHPUnit and the dev server, inherit this config.
if defined PHP_INI_SCAN_DIR (
    set "PHP_INI_SCAN_DIR=%PHP_INI_SCAN_DIR%;%~dp0"
) else (
    set "PHP_INI_SCAN_DIR=%~dp0"
)
php %*
