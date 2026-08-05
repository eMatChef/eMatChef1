#!/usr/bin/env bash
# Auf dem Weblate-Droplet ausführen (nach Merge der Locale-Stubs auf den Track-Branch).
# Erweitert DE Varianten um Jubla und legt FR/IT Varianten an.
set -euo pipefail
cd /opt/weblate

docker compose exec -T weblate weblate shell <<'PY'
from django.db import transaction
from weblate.trans.models import Component, Project
from weblate.lang.models import Language, Plural

p = Project.objects.get(slug="ematchef")
base = Component.objects.get(project=p, slug="app-ui")

# Aliases (Komma-getrennt)
p.language_aliases = "ch-rm:rm,de:de_CH"
p.save(update_fields=["language_aliases"])
print("aliases:", p.language_aliases)

# Custom languages
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

def ensure_variant_component(slug, name, template, filemask, regex, source_code):
    c = Component.objects.filter(project=p, slug=slug).first()
    source_lang = Language.objects.get(code=source_code)
    if c is None:
        c = Component.objects.create(
            project=p,
            name=name,
            slug=slug,
            repo=f"weblate://{base.project.slug}/{base.slug}",
            filemask=filemask,
            template=template,
            file_format="json-nested",
            language_regex=regex,
            new_lang="none",
            language_code_style="bcp",
            source_language=source_lang,
        )
        print("CREATED", c.full_slug)
    else:
        c.filemask = filemask
        c.template = template
        c.file_format = "json-nested"
        c.language_regex = regex
        c.new_lang = "none"
        c.language_code_style = "bcp"
        c.source_language = source_lang
        c.save()
        print("UPDATED", c.full_slug)

    try:
        c.create_translations(force=True)
    except Exception as e:
        print("create_translations:", type(e).__name__, e)

    with transaction.atomic():
        for t in c.translation_set.all():
            t.check_sync(force=True)
            t.invalidate_cache()
            print(" ", t.language.code, "total=", t.stats.all, "translated=", t.stats.translated)

ensure_variant_component(
    "de-varianten",
    "DE Varianten",
    "frontend/src/locales/de.json",
    "frontend/src/locales/de-*.json",
    r"^(de-pfadi|de-cevi|de-jubla)$",
    "de_CH",
)
ensure_variant_component(
    "fr-varianten",
    "FR Varianten",
    "frontend/src/locales/fr.json",
    "frontend/src/locales/fr-*.json",
    r"^(fr-pfadi|fr-cevi|fr-jubla)$",
    "fr",
)
ensure_variant_component(
    "it-varianten",
    "IT Varianten",
    "frontend/src/locales/it.json",
    "frontend/src/locales/it-*.json",
    r"^(it-pfadi|it-cevi|it-jubla)$",
    "it",
)
print("DONE")
PY
