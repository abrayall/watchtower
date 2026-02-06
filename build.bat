@echo off
setlocal enabledelayedexpansion

REM Build both Watchtower plugins
REM Agent must be built first so it can be bundled with Manager

set SCRIPT_DIR=%~dp0

echo Building Watchtower Agent...
cd /d "%SCRIPT_DIR%agent"
wordsmith build --quiet

REM Get the built agent ZIP
for /f "delims=" %%i in ('dir /b /o-d build\*.zip 2^>nul') do (
    set AGENT_ZIP=%%i
    goto :found_agent
)
:found_agent

REM Extract version from filename
set VERSION=%AGENT_ZIP:watchtower-agent-=%
set VERSION=%VERSION:.zip=%

echo Building Watchtower Manager...
cd /d "%SCRIPT_DIR%manager"

REM Copy agent ZIP to manager assets for bundling
copy "%SCRIPT_DIR%agent\build\%AGENT_ZIP%" assets\ >nul

wordsmith build --quiet

REM Clean up the copied agent ZIP from source
del /q assets\watchtower-agent-*.zip 2>nul

echo.
echo Build complete!
echo   - agent/build/watchtower-agent-%VERSION%.zip
echo   - manager/build/watchtower-%VERSION%.zip

endlocal
