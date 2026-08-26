@echo off
echo.
echo ==============================================
echo   Asistente de Subida y Actualizacion a GitHub
echo ==============================================
echo.

:: 1. Verificar si la carpeta local de Git existe. Si no, inicializarla.
if not exist .git (
    echo [INFO] El repositorio local no estaba inicializado. Configurando Git...
    git init
    git branch -M main
    git remote add origin https://github.com/19webs/wp-document-signer-pro.git
    echo [OK] Repositorio Git local conectado a GitHub.
    echo.
)

:: 2. Preguntar al usuario si desea incrementar la version y crear un tag
set /p version="Introduce la nueva version (ej: 1.0.6) o pulsa ENTER para subir cambios simples: "

if "%version%"=="" (
    echo.
    echo [INFO] Subiendo cambios normales sin incremento de version...
    set commit_message=Auto-actualizacion
    goto git_push
)

echo.
echo [INFO] Actualizando version a v%version% en wp-document-signer.php...
:: Modificar version de forma segura en UTF-8 sin BOM (evita corrupcion de caracteres y cabeceras invalidas)
powershell -Command "$content = Get-Content 'wp-document-signer.php' -Raw; $content = $content -replace 'Version:\s+[0-9.]+', 'Version:     %version%'; $content = $content -replace 'define\(\s*''WPDS_VERSION''\s*,\s*''[0-9.]+''\s*\)', 'define( ''WPDS_VERSION'', ''%version%'' )'; [System.IO.File]::WriteAllText('wp-document-signer.php', $content, (New-Object System.Text.UTF8Encoding $false));"
echo [OK] Archivo wp-document-signer.php modificado.

set commit_message=Version v%version%

:git_push
:: 3. Añadir y hacer commit
git add .
git commit -m "%commit_message%"

:: 4. Subir archivos al repositorio
echo.
echo [INFO] Subiendo archivos a GitHub...
git push -f origin main

:: 5. Si se especifico version, crear y subir el tag correspondiente
if not "%version%"=="" (
    echo.
    echo [INFO] Creando y subiendo etiqueta de version v%version%...
    :: Eliminar tag local y remoto previo si existiera por error para evitar colisiones
    git tag -d v%version% >nul 2>&1
    git push origin :refs/tags/v%version% >nul 2>&1
    
    :: Crear y subir nueva etiqueta
    git tag v%version%
    git push origin v%version%
    echo [OK] Etiqueta v%version% subida correctamente a GitHub.
)

echo.
echo ==============================================
echo   Proceso completado con exito.
echo ==============================================
echo.
pause
