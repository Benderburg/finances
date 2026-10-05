"""Package the prepared no-dev Noros runtime and explicit finance integration files."""
from pathlib import Path
import hashlib
import shutil
import zipfile

root = Path(__file__).resolve().parent.parent
release = root / ".runtime/public-site-release"
site = release / "public-site"
assert site.is_dir() and (site / "vendor/autoload.php").is_file()
for name in ["app", "config", "lang", "resources", "routes"]:
    shutil.copytree(root / "public-site" / name, site / name, dirs_exist_ok=True)
shutil.copy2(root / "public-site/bootstrap/app.php", site / "bootstrap/app.php")
shutil.copy2(root / "public-site/bootstrap/providers.php", site / "bootstrap/providers.php")
shutil.copytree(root / "public-site/database/seeders", site / "database/seeders", dirs_exist_ok=True)
shutil.copytree(root / "public-site/database/content", site / "database/content", dirs_exist_ok=True)
shutil.copytree(root / "public-site/public", site / "public", dirs_exist_ok=True)
for name in ["public/index.php", "bootstrap/application-path.php", "app/Http/Controllers/AuthController.php", "resources/pwa/sw.js", "resources/pwa/manifest.webmanifest", "public/sw.js", "public/manifest.webmanifest"]:
    target = release / "backend" / name
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(root / "backend" / name, target)
for name in ["build", "site", "img/norocel", "icons", "js", "css", "fonts"]:
    shutil.copytree(root / "backend/public" / name, release / "backend/public" / name, dirs_exist_ok=True)
shutil.copy2(root / "backend/public/favicon.ico", release / "backend/public/favicon.ico")
for source, target in [("timeweb-stage-b-check.php", "public-site-check.php"), ("public-site-owner.php", "public-site-owner.php"), ("deploy-public-site-timeweb.sh", "deploy-public-site.sh")]:
    content = (root / "scripts" / source).read_text(encoding="utf-8").replace("\r\n", "\n")
    (release / target).write_text(content, encoding="utf-8", newline="\n")
files = sorted(p for p in release.rglob("*") if p.is_file() and p.name != "public-site-files.sha256")
for path in files:
    relative = path.relative_to(release).as_posix()
    assert path.name != ".env" and not path.name.endswith((".sqlite", ".sqlite-wal", ".sqlite-shm")), relative
    assert "/storage/" not in relative and "/tests/" not in relative.lower(), relative
    assert not relative.startswith("public-site/bootstrap/cache/"), relative
manifest = "".join(f"{hashlib.sha256(p.read_bytes()).hexdigest()}  {p.relative_to(release).as_posix()}\n" for p in files)
(release / "public-site-files.sha256").write_text(manifest, encoding="utf-8", newline="\n")
archive = root / ".runtime/norocel-public-site-20261004.zip"
with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED, compresslevel=6) as bundle:
    for path in files + [release / "public-site-files.sha256"]:
        bundle.write(path, path.relative_to(release).as_posix())
print(f"{archive}\nFiles: {len(files)}; bytes: {archive.stat().st_size}\nSHA256: {hashlib.sha256(archive.read_bytes()).hexdigest()}")
