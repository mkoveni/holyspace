#!/usr/bin/env python3
"""Package only reviewed deployment files and the locally tested runtime images."""
import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import tarfile
import tempfile

ROOT = Path(__file__).resolve().parents[2]
COMPONENTS = ("api", "database", "tools", "web")


def digest(path):
    checksum = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            checksum.update(chunk)
    return checksum.hexdigest()


def release_files():
    files = [ROOT / name for name in ("compose.java-production.yaml", "compose.java-production-nginx.yaml",
                                     "compose.java-production-nginx-app.yaml")]
    files += sorted((ROOT / "scripts/java-production").glob("*.sh"))
    files += sorted((ROOT / "scripts/java-production").glob("*.py"))
    files += sorted((ROOT / "docker/java-production").glob("*"))
    return [path for path in files if path.is_file() and not path.is_symlink()]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("commit")
    parser.add_argument("output", type=Path)
    args = parser.parse_args()
    if not re.fullmatch(r"[0-9a-f]{40}", args.commit):
        parser.error("Commit must be a full lowercase Git SHA.")
    args.output.mkdir(parents=True, exist_ok=True)
    manifest = {"commit": args.commit, "platform": "linux/amd64", "images": {}, "files": {}}
    for component in COMPONENTS:
        source = f"holyspace-java-{component}:release-check"
        image = f"holyspace-java-{component}:{args.commit}"
        subprocess.run(["docker", "tag", source, image], check=True)
        info = json.loads(subprocess.check_output(["docker", "image", "inspect", image]))[0]
        if info["Os"] != "linux" or info["Architecture"] != "amd64":
            raise ValueError("Release images must target the AMD64 Linux VPS.")
        manifest["images"][image] = info["Id"]
    files = release_files()
    manifest["files"] = {str(path.relative_to(ROOT)): hashlib.sha256(path.read_bytes()).hexdigest()
                         for path in files}
    with tempfile.TemporaryDirectory(prefix="holyspace-package-") as directory:
        temporary = Path(directory)
        with (temporary / "images.tar.gz").open("wb") as output:
            save = subprocess.Popen(["docker", "save", *manifest["images"]], stdout=subprocess.PIPE)
            try:
                gzip = subprocess.run(["gzip", "-1"], stdin=save.stdout, stdout=output)
                save.stdout.close()
                if save.wait() or gzip.returncode:
                    raise RuntimeError("Image archive creation failed.")
            finally:
                if save.poll() is None:
                    save.kill()
                    save.wait()
        manifest["image_archive_sha256"] = digest(temporary / "images.tar.gz")
        (temporary / "manifest.json").write_text(json.dumps(manifest, indent=2) + "\n")
        archive = args.output / "holyspace-release.tar"
        with tarfile.open(archive, "w") as bundle:
            for path in files:
                bundle.add(path, arcname=str(path.relative_to(ROOT)), recursive=False)
            for name in ("images.tar.gz", "manifest.json"):
                bundle.add(temporary / name, arcname=name)
    checksum = digest(archive)
    (args.output / "holyspace-release.tar.sha256").write_text(checksum + "  holyspace-release.tar\n")
    print("Packaged the tested backend images and deployment files; no production secrets included.")


if __name__ == "__main__":
    main()
