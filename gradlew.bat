@echo off
setlocal
set GRADLE_VERSION=9.5.0
where gradle >nul 2>nul
if %ERRORLEVEL% EQU 0 (
  gradle %*
  exit /b %ERRORLEVEL%
)
set CACHE_BASE=%USERPROFILE%\.gradle\hardwire-bootstrap
set GRADLE_HOME=%CACHE_BASE%\gradle-%GRADLE_VERSION%
if not exist "%GRADLE_HOME%\bin\gradle.bat" (
  powershell -NoProfile -ExecutionPolicy Bypass -Command "$v='%GRADLE_VERSION%'; $b='%CACHE_BASE%'; New-Item -ItemType Directory -Force -Path $b | Out-Null; $z=Join-Path $env:TEMP ('gradle-'+$v+'-bin.zip'); Invoke-WebRequest ('https://services.gradle.org/distributions/gradle-'+$v+'-bin.zip') -OutFile $z; Expand-Archive -Force $z $b; Remove-Item $z"
  if %ERRORLEVEL% NEQ 0 exit /b %ERRORLEVEL%
)
call "%GRADLE_HOME%\bin\gradle.bat" %*
