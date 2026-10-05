# Run `npm ci` in frontend first. All brand assets use the root logo.png.
$taskBrandScript = Join-Path $PSScriptRoot 'build-brand.mjs'
& node $taskBrandScript
if ($LASTEXITCODE -ne 0) { throw 'Norocel brand asset generation failed.' }
