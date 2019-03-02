@echo off
setlocal DISABLEDELAYEDEXPANSION
SET BIN_TARGET=%~dp0/../bin/spip
php "%BIN_TARGET%" %*