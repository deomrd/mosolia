@echo off
rem Construit l'APK Mosolia sous Windows : double-cliquez sur ce fichier, ou tapez construire.bat dans cmd.
rem Il faut : Java 17 (JDK) et le SDK Android (voir README.md, section "Avec cmd").
cd /d "%~dp0"
if not exist local.properties (
  if defined ANDROID_HOME (
    echo sdk.dir=%ANDROID_HOME:\=\\%> local.properties
  ) else (
    if exist "%LOCALAPPDATA%\Android\Sdk" (
      echo sdk.dir=%LOCALAPPDATA:\=\\%\\Android\\Sdk> local.properties
    ) else (
      echo SDK Android introuvable. Installez-le ou definissez ANDROID_HOME.
      pause
      exit /b 1
    )
  )
)
call gradlew.bat assembleDebug
if errorlevel 1 (
  echo.
  echo ECHEC : copiez les lignes qui commencent par "e:" et envoyez-les.
  pause
  exit /b 1
)
copy /y app\build\outputs\apk\debug\app-debug.apk mosolia.apk >nul
echo.
echo APK pret : %~dp0mosolia.apk
pause
