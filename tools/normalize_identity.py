from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REPLACEMENTS = (
    ("decaropeople", "xdecaropeople"),
    ("DECAROPEOPLE", "XDECAROPEOPLE"),
    ("Decaropeople", "People"),
    ("0.1.0", "0.2.0"),
)
TEXT_SUFFIXES = {".php", ".xml", ".ini", ".md", ".json", ".yml", ".yaml", ".txt", ".sql", ".sh"}
TEXT_NAMES = {"VERSION"}

def ignored(path: Path) -> bool:
    return ".git" in path.parts or ".github" in path.parts or path == Path(__file__)

for path in sorted(ROOT.rglob("*")):
    if not path.is_file() or ignored(path):
        continue
    if path.suffix.lower() not in TEXT_SUFFIXES and path.name not in TEXT_NAMES:
        continue
    text = path.read_text(encoding="utf-8")
    new = text
    for old, replacement in REPLACEMENTS:
        new = new.replace(old, replacement)
    if new != text:
        path.write_text(new, encoding="utf-8")

for path in sorted((p for p in ROOT.rglob("*") if not ignored(p)), key=lambda p: len(p.parts), reverse=True):
    new_name = path.name
    for old, replacement in REPLACEMENTS:
        new_name = new_name.replace(old, replacement)
    if new_name != path.name:
        path.rename(path.with_name(new_name))
