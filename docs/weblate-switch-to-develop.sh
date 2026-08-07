#!/usr/bin/env bash
# Weblate auf Branch develop umstellen (Quelle + Update) und Clone neu ziehen.
# Auf dem Droplet: curl … | bash   oder lokal nach /opt/weblate kopieren.
set -euo pipefail
cd /opt/weblate

docker compose exec -T weblate weblate shell <<'PY'
from weblate.trans.models import Component

base = Component.objects.get(project__slug="ematchef", slug="app-ui")
base.branch = "develop"
if hasattr(base, "push_branch"):
    base.push_branch = "weblate"
base.save()
print("app-ui branch=", base.branch, "push_branch=", getattr(base, "push_branch", None))

for slug in ["de-varianten", "fr-varianten", "it-varianten"]:
    c = Component.objects.filter(project__slug="ematchef", slug=slug).first()
    if not c:
        continue
    if hasattr(c, "branch"):
        c.branch = "develop"
        c.save(update_fields=["branch"])
    print(slug, "branch=", getattr(c, "branch", None))
print("settings OK")
PY

docker compose exec -T -u weblate weblate sh -c '
  set -e
  export GIT_SSH_COMMAND="ssh -o UserKnownHostsFile=/app/data/ssh/known_hosts -o IdentityFile=/app/data/ssh/id_ed25519 -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes"
  rm -rf /app/data/vcs/ematchef/app-ui
  mkdir -p /app/data/vcs/ematchef
  git clone --branch develop --single-branch git@github.com:eMatChef/eMatChef1.git /app/data/vcs/ematchef/app-ui
  cd /app/data/vcs/ematchef/app-ui
  git fetch origin weblate:weblate 2>/dev/null || git branch weblate develop
  echo "==== HEAD ===="
  git log -1 --oneline
  ls frontend/src/locales/
'

docker compose exec -T weblate weblate shell <<'PY'
from weblate.trans.models import Component

base = Component.objects.get(project__slug="ematchef", slug="app-ui")
try:
    base.do_update(None)
    print("do_update OK")
except Exception as e:
    print("do_update:", type(e).__name__, e)

for c in Component.objects.filter(project__slug="ematchef").exclude(slug="glossary"):
    try:
        c.create_translations(force=True)
        print("scan", c.full_slug, "OK")
    except Exception as e:
        print("scan", c.full_slug, type(e).__name__, e)
print("DONE")
PY
