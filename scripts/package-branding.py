"""Package the built Norocel branding update without runtime data or secrets."""
from pathlib import Path
import hashlib
import json
import re
import subprocess
import zipfile

root = Path(__file__).resolve().parent.parent
html = (root / "backend/public/build/index.html").read_text(encoding="utf-8")
worker = (root / "backend/resources/pwa/sw.js").read_text(encoding="utf-8")
assert '/icons/loader.svg' in html and '/icons/boot.js' in html
assert '/icons/logo.svg' in worker and '/icons/maskable-512.png' in worker
version = re.search(r"norocel-shell-([a-f0-9]{16})", worker).group(1)
commit = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=root, text=True).strip()
files = {
    "backend/public/build/index.html", "backend/public/favicon.ico",
    "backend/resources/pwa/sw.js", "backend/resources/pwa/manifest.webmanifest",
    "backend/public/site/site.css", "backend/public/site/favicon.svg",
    "backend/public/img/norocel/social-preview.png",
    "public-site/app/Providers/AdminPanelProvider.php",
    "public-site/resources/views/layouts/site.blade.php",
    "public-site/resources/views/components/preview.blade.php",
    "public-site/resources/views/blocks/cta.blade.php",
    "public-site/public/favicon.ico", "public-site/public/site/site.css",
    "public-site/public/site/favicon.svg",
    "public-site/public/img/norocel/social-preview.png",
}
for directory in ["backend/public/build/assets", "backend/public/icons", "public-site/public/icons"]:
    files.update(p.relative_to(root).as_posix() for p in (root / directory).iterdir() if p.is_file())
files = sorted(files)
manifest = "".join(f"{hashlib.sha256((root / name).read_bytes()).hexdigest()}  {name}\n" for name in files)
release = {"commit": commit, "pwa_version": version, "files": files}
archive = root / f".runtime/norocel-branding-{commit[:7]}-{version}.zip"
archive.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(archive, "w", zipfile.ZIP_DEFLATED, compresslevel=6) as bundle:
    for name in files:
        bundle.write(root / name, name)
    bundle.writestr("branding-files.sha256", manifest)
    bundle.writestr("branding-release.json", json.dumps(release, indent=2) + "\n")
    bundle.writestr("deploy-branding.sh", (root / "scripts/deploy-branding-timeweb.sh").read_text().replace("\r\n", "\n"))
print(json.dumps({"archive": str(archive), "sha256": hashlib.sha256(archive.read_bytes()).hexdigest(), "bytes": archive.stat().st_size, "files": len(files), "commit": commit, "pwa_version": version}, indent=2))
