#!/usr/bin/env bash
# Auf dem Weblate-Droplet ausführen (nach Merge der Locale-Stubs auf den Track-Branch).
# Erweitert DE Varianten um Jubla und legt FR/IT Varianten an.
#
# WICHTIG: Dateimaske muss frontend/src/locales/*.json sein.
# NICHT de-*.json — sonst wird aus Code de-jubla die Datei de-de-jubla.json.
set -euo pipefail
cd /opt/weblate

docker compose exec -T weblate weblate shell <<'PY'
from django.db import transaction
from weblate.trans.models import Component, Project, Translation
from weblate.lang.models import Language, Plural
from pathlib import Path

p = Project.objects.get(slug="ematchef")
base = Component.objects.get(project=p, slug="app-ui")
LOC = Path("/app/data/vcs/ematchef/app-ui/frontend/src/locales")
FILEMASK = "frontend/src/locales/*.json"

# Aliases (Komma-getrennt)
p.language_aliases = "ch-rm:rm,de:de_CH"
p.save(update_fields=["language_aliases"])
print("aliases:", p.language_aliases)

# Falsche Doppel-Präfix-Dateien entsorgen (falls früher angelegt)
for bad in [
    "de-de-jubla.json",
    "fr-fr-pfadi.json", "fr-fr-cevi.json", "fr-fr-jubla.json",
    "it-it-pfadi.json", "it-it-cevi.json", "it-it-jubla.json",
]:
    path = LOC / bad
    if path.exists():
        path.unlink()
        print("deleted bad", path)

src_de = Language.objects.filter(code="de").first() or Language.objects.get(code="de_CH")
defs = [
    ("de-pfadi", "Deutsch (Pfadi)"),
    ("de-cevi", "Deutsch (Cevi)"),
    ("de-jubla", "Deutsch (Jubla)"),
    ("fr-pfadi", "Français (Pfadi)"),
    ("fr-cevi", "Français (Cevi)"),
    ("fr-jubla", "Français (Jubla)"),
    ("it-pfadi", "Italiano (Pfadi)"),
    ("it-cevi", "Italiano (Cevi)"),
    ("it-jubla", "Italiano (Jubla)"),
]
for code, name in defs:
    lang, created = Language.objects.get_or_create(code=code, defaults={"name": name, "direction": "ltr"})
    if not created and lang.name != name:
        lang.name = name
        lang.save(update_fields=["name"])
    if lang.plural_set.count() == 0:
        for pl in src_de.plural_set.all():
            Plural.objects.get_or_create(
                language=lang,
                source=pl.source,
                defaults={"number": pl.number, "formula": pl.formula, "type": pl.type},
            )
    print("lang", code, "created=", created)

def ensure_variant_component(slug, name, template, regex, source_code, variant_codes):
    c = Component.objects.filter(project=p, slug=slug).first()
    source_lang = Language.objects.get(code=source_code)
    if c is None:
        c = Component.objects.create(
            project=p,
            name=name,
            slug=slug,
            repo=f"weblate://{base.project.slug}/{base.slug}",
            filemask=FILEMASK,
            template=template,
            file_format="json-nested",
            language_regex=regex,
            new_lang="none",
            language_code_style="bcp",
            source_language=source_lang,
        )
        print("CREATED", c.full_slug)
    else:
        c.filemask = FILEMASK
        c.template = template
        c.file_format = "json-nested"
        c.language_regex = regex
        c.new_lang = "none"
        c.language_code_style = "bcp"
        c.source_language = source_lang
        c.save()
        print("UPDATED", c.full_slug, "mask=", c.filemask)

    # Stubs + Translations mit korrektem Dateinamen (code.json, nicht base-code.json)
    for code in variant_codes:
        stub = LOC / f"{code}.json"
        if not stub.exists():
            stub.write_text("{}\n", encoding="utf-8")
            print(" stub", stub)
        lang = Language.objects.get(code=code)
        filename = f"frontend/src/locales/{code}.json"
        # Alte falsche Translation entfernen
        for t in list(c.translation_set.filter(language=lang)):
            if t.filename != filename:
                print(" remove wrong", t.filename)
                t.remove()
        t = c.translation_set.filter(language=lang).first()
        if t is None:
            plural = lang.plural_set.order_by("id").first()
            t = Translation.objects.create(
                component=c,
                language=lang,
                filename=filename,
                plural=plural,
            )
            print(" attached", code, "->", filename)
        else:
            if t.filename != filename:
                t.filename = filename
                t.save(update_fields=["filename"])
            print(" ok", code, "->", t.filename)
        with transaction.atomic():
            t.check_sync(force=True)
        t.invalidate_cache()
        print("  total=", t.stats.all, "translated=", t.stats.translated)

    # Quelle syncen
    src = c.translation_set.filter(language=c.source_language).first()
    if src:
        with transaction.atomic():
            src.check_sync(force=True)
        src.invalidate_cache()
        print(" source", src.language.code, "total=", src.stats.all)

ensure_variant_component(
    "de-varianten",
    "DE Varianten",
    "frontend/src/locales/de.json",
    r"^(de-pfadi|de-cevi|de-jubla)$",
    "de_CH",
    ["de-pfadi", "de-cevi", "de-jubla"],
)
ensure_variant_component(
    "fr-varianten",
    "FR Varianten",
    "frontend/src/locales/fr.json",
    r"^(fr-pfadi|fr-cevi|fr-jubla)$",
    "fr",
    ["fr-pfadi", "fr-cevi", "fr-jubla"],
)
ensure_variant_component(
    "it-varianten",
    "IT Varianten",
    "frontend/src/locales/it.json",
    r"^(it-pfadi|it-cevi|it-jubla)$",
    "it",
    ["it-pfadi", "it-cevi", "it-jubla"],
)
print("DONE")
PY
