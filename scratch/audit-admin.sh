#!/bin/bash
# Admin module check — without login (just verify 302 redirect not 500)
BASE="https://mori.vylence.com"

PAGES=(
  "dashboard.php"
  "homepage.php"
  "hero.php"
  "pages.php"
  "page-edit.php?id=1"
  "page-builder.php?id=1"
  "insights.php"
  "team.php"
  "funds.php"
  "performance.php"
  "documents.php"
  "documents.php?category=share_class"
  "documents.php?category=company_policy"
  "documents.php?category=other"
  "documents.php?category=suspension_update"
  "announcements.php"
  "messages.php"
  "newsletter.php"
  "newsletter-send.php"
  "media.php"
  "seo.php"
  "users.php"
  "settings.php"
  "database.php"
  "audit.php"
  "guide.php"
)

echo "════════════════════════════════════════════════════════════════════"
echo "  MORI ADMIN — ENDPOINT CHECK  (no-auth: 302=ok, 500=broken)"
echo "════════════════════════════════════════════════════════════════════"
echo ""

bad=0
for p in "${PAGES[@]}"; do
  code=$(curl -sk -o /dev/null -w "%{http_code}" "$BASE/admin/$p")
  marker="OK "
  if [ "$code" = "500" ]; then marker="✗ FAIL"; bad=$((bad+1)); fi
  if [ "$code" = "404" ]; then marker="✗ 404 "; bad=$((bad+1)); fi
  printf "  %-45s  %s  %s\n" "$p" "$code" "$marker"
done

echo ""
echo "════════════════════════════════════════════════════════════════════"
if [ $bad -eq 0 ]; then
  echo "  All admin pages reachable (302=auth-redirect or 200=public)."
else
  echo "  $bad pages broken — see above."
fi
echo "════════════════════════════════════════════════════════════════════"

# Now check the PHP files for syntax integrity locally
echo ""
echo "── LOCAL PHP SYNTAX CHECK ──"
for f in /home/user/mori-capital-2/admin/*.php /home/user/mori-capital-2/admin/api/*.php /home/user/mori-capital-2/admin/partials/*.php; do
  out=$(php -l "$f" 2>&1)
  if ! echo "$out" | grep -q 'No syntax errors'; then
    echo "  ✗ $f"
    echo "$out" | sed 's/^/      /'
  fi
done
echo "  Done."
