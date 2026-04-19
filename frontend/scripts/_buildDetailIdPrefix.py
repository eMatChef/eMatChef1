#!/usr/bin/env python3
"""
Build id.-prefixed detail template + emit whitelist for provide() from ActivitiesView.
Excludes v-for / slot scope iterator names and HTML noise.
"""
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
VIEW = ROOT / "src/views/ActivitiesView.vue"


def extract_script_names(script: str) -> set[str]:
    names: set[str] = set()
    names |= set(re.findall(r"(?:const|let)\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*(?::|=)", script))
    names |= set(re.findall(r"const\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*=\s*computed\(", script))
    names |= set(re.findall(r"function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(", script))
    names |= set(re.findall(r"async function\s+([a-zA-Z_][a-zA-Z0-9_]*)\s*\(", script))
    # const FOO = (all caps)
    names |= set(re.findall(r"const\s+([A-Z][A-Z0-9_]*)\s*=", script))
    # import { foo, bar as b } from '...'
    for m in re.finditer(r"import\s*\{([^}]+)\}\s*from", script):
        for part in m.group(1).split(","):
            part = part.strip()
            if not part:
                continue
            if " as " in part:
                part = part.split(" as ")[-1].strip()
            else:
                part = part.split(":")[0].strip()
            if re.match(r"^[a-zA-Z_][a-zA-Z0-9_]*$", part):
                names.add(part)
    return names


def extract_v_for_locals(template: str) -> set[str]:
    out: set[str] = set()
    for m in re.finditer(r'v-for\s*=\s*"([^"]+)"', template):
        inner = m.group(1).strip()
        if " in " not in inner:
            continue
        left, _right = inner.split(" in ", 1)
        left = left.strip()
        if left.startswith("(") and left.endswith(")"):
            left = left[1:-1]
        for part in left.split(","):
            part = part.strip().split(":")[0].strip()
            if re.match(r"^[a-zA-Z_][a-zA-Z0-9_]*$", part):
                out.add(part)
    return out


def extract_slot_scope_locals(template: str) -> set[str]:
    out: set[str] = set()
    for m in re.finditer(r"#\w+\s*=\s*\"\{([^}]+)\}\"", template):
        inner = m.group(1)
        for part in inner.split(","):
            part = part.strip()
            if ":" in part:
                part = part.split(":")[0].strip()
            if re.match(r"^[a-zA-Z_][a-zA-Z0-9_]*$", part):
                out.add(part)
    return out


def main() -> None:
    raw = VIEW.read_text(encoding="utf-8")
    script = raw.split("<script setup lang=\"ts\">", 1)[1].split("</script>", 1)[0]
    script_names = extract_script_names(script)

    # detail template = inner panel + modal (extracted by sed in caller)
    tpl_path = Path(sys.argv[1]) if len(sys.argv) > 1 else Path("/tmp/detail_combined.txt")
    template = tpl_path.read_text(encoding="utf-8")

    v_for_excl = extract_v_for_locals(template)
    slot_excl = extract_slot_scope_locals(template)

    # token candidates: word-like in {{ }} and directives — approximate: all \b identifiers
    tokens = set(re.findall(r"\b([a-zA-Z_][a-zA-Z0-9_]*)\b", template))

    SKIP_HTML = {
        "div", "span", "button", "svg", "path", "line", "circle", "polyline", "rect", "template",
        "label", "h1", "h2", "h3", "h4", "p", "ul", "li", "a", "br", "strong", "table", "thead",
        "tbody", "tr", "th", "td", "select", "option", "input", "textarea", "img", "pre", "code",
        "transition", "teleport",
    }
    SKIP_JS = {
        "true", "false", "null", "undefined", "Date", "Math", "Number", "String", "Object", "Array",
        "JSON", "window", "Promise", "parseInt", "parseFloat", "console", "Intl",
    }

    whitelist: list[str] = []
    for t in tokens:
        if t in SKIP_HTML or t in SKIP_JS:
            continue
        if t[0].isupper() and t not in script_names:
            # PascalCase component names — never prefix
            continue
        if len(t) == 1:
            continue
        if t in v_for_excl or t in slot_excl:
            continue
        if t not in script_names:
            continue
        whitelist.append(t)

    # Ensure common detail symbols even if regex missed
    for extra in (
        "selectedActivity",
        "activityDetail",
        "showDetail",
        "closeDetail",
        "PACK_STAGES",
        "DEPT_MW_ROLES",
    ):
        if extra in script_names and extra not in whitelist:
            whitelist.append(extra)

    whitelist = sorted(set(whitelist), key=len, reverse=True)

    # prefix — avoid:
    # - kebab-case class fragments (detail-title-row): hyphen is not \w so \b wrongly matches "row"
    # - property access (foo.bar): dot must not precede identifier
    # - single-quoted string literals ('accepted'): do not prefix inside '...'
    # - HTML attribute names (type="datetime-local"): forbid match when followed by =
    def prefix_ident(text: str, ident: str) -> str:
        esc = re.escape(ident)
        # Lookahead must allow `.` so `completionBlockers.open_*` prefixes the root name.
        # Forbid `=` after ident: `:max="..."`, `type="..."` stay intact.
        # Do not forbid `"` — needed so `@click="tryShowNativePicker"` gets prefixed.
        # Kebab-case: after `type` in `type-badge` next is `-` (in class) → no match.
        lookbehind = r"(?<!id\.)(?<![a-zA-Z0-9_.\-`'])"
        lookahead = r"(?![a-zA-Z0-9_\-`'=])"
        pat = lookbehind + esc + lookahead
        return re.sub(pat, "id." + ident, text)

    text = template
    for ident in whitelist:
        text = prefix_ident(text, ident)

    # Static `value="ok"` etc.: prefix step would produce `value="id.ok"` — revert for plain literals.
    text = re.sub(r'value="id\.([a-z][a-z0-9_]*)"', r'value="\1"', text)

    out_path = Path(sys.argv[2]) if len(sys.argv) > 2 else Path("/tmp/detail_id.txt")
    out_path.write_text(text, encoding="utf-8")

    keys_path = Path(sys.argv[3]) if len(sys.argv) > 3 else Path("/tmp/detail_inject_keys.txt")
    keys_path.write_text("\n".join(sorted(set(whitelist), key=len, reverse=True)) + "\n", encoding="utf-8")

    print(f"Wrote {out_path} ({len(text)} chars), {len(whitelist)} whitelist ids -> {keys_path}")


if __name__ == "__main__":
    main()
