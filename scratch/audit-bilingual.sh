#!/bin/bash
# Comprehensive bilingual audit of mori.vylence.com
BASE="https://mori.vylence.com"

PUBLIC=(
  "/"
  "/about.php"
  "/investment-style.php"
  "/fund-eastern-european.php"
  "/fund-ottoman.php"
  "/fund-performance.php"
  "/documents.php"
  "/company-policies.php"
  "/other-documents.php"
  "/updates-during-suspension.php"
  "/announcements.php"
  "/team.php"
  "/insights.php"
  "/contact.php"
  "/legal.php"
  "/privacy.php"
  "/cookies.php"
)

# Common English strings that should NOT appear on DE pages
EN_FLAGS=(
  "Read more"
  "Learn more"
  "View all"
  "Get in touch"
  "About Mori Capital"
  "The Mori Style"
  "Discover the Mori Style"
  "Send a message"
  "Document Hub"
  "All funds"
  "All types"
  "Search"
  "No documents"
  "No policies"
  "No announcements"
  "No suspension"
  "Both languages"
  "Date"
  "Download"
  "Performance"
  "Share class"
  "Fund Announcements"
  "Other Documents"
  "Updates During Suspension"
  "Company Policies"
  "Share Class Documents"
  "Subscribe"
  "Stay Informed"
  "Last updated"
  "Notes:"
  "Phone Number"
  "Email Address"
  "Quick Links"
  "Follows On Socials"
)

echo "════════════════════════════════════════════════════════════════════"
echo "  MORI CAPITAL — BILINGUAL AUDIT"
echo "  $(date +'%Y-%m-%d %H:%M')"
echo "════════════════════════════════════════════════════════════════════"

echo ""
echo "── 1. HTTP STATUS (EN + DE) ──"
printf "  %-35s  %-6s  %-6s\n" "PAGE" "EN" "DE"
for url in "${PUBLIC[@]}"; do
  en=$(curl -sk -o /dev/null -w "%{http_code}" "$BASE$url")
  sep="?"; [[ "$url" == *"?"* ]] && sep="&"
  de=$(curl -sk -o /dev/null -w "%{http_code}" "$BASE${url}${sep}lang=de")
  printf "  %-35s  %-6s  %-6s\n" "$url" "$en" "$de"
done

echo ""
echo "── 2. PHP ERRORS ──"
errfound=0
for url in "${PUBLIC[@]}"; do
  for lang in "" "?lang=de"; do
    body=$(curl -sk "$BASE${url}${lang}")
    errs=$(echo "$body" | grep -oE '(Fatal error|Warning:|Parse error|Deprecated:)' | head -3)
    if [ -n "$errs" ]; then
      errfound=1
      echo "  $url $lang  →  $errs"
    fi
  done
done
[ $errfound -eq 0 ] && echo "  No PHP errors detected."

echo ""
echo "── 3. HARDCODED EN STRINGS ON DE PAGES ──"
totalflags=0
for url in "${PUBLIC[@]}"; do
  sep="?"; [[ "$url" == *"?"* ]] && sep="&"
  body=$(curl -sk "$BASE${url}${sep}lang=de")
  found=""
  for s in "${EN_FLAGS[@]}"; do
    cnt=$(echo "$body" | grep -c "$s")
    if [ "$cnt" -gt 0 ]; then
      found="$found\n      ⚠ '$s' ×$cnt"
      totalflags=$((totalflags+cnt))
    fi
  done
  if [ -n "$found" ]; then
    echo "  $url ?lang=de:"
    echo -e "$found"
  fi
done
echo ""
echo "  TOTAL hardcoded EN occurrences on DE pages: $totalflags"

echo ""
echo "── 4. PAGE TITLES (EN vs DE) ──"
for url in "${PUBLIC[@]}"; do
  en_title=$(curl -sk "$BASE$url" | grep -oE '<title>[^<]+</title>' | head -1 | sed 's/<[^>]*>//g')
  sep="?"; [[ "$url" == *"?"* ]] && sep="&"
  de_title=$(curl -sk "$BASE${url}${sep}lang=de" | grep -oE '<title>[^<]+</title>' | head -1 | sed 's/<[^>]*>//g')
  printf "  %-35s\n     EN: %s\n     DE: %s\n" "$url" "${en_title:0:80}" "${de_title:0:80}"
done

echo ""
echo "── 5. DOCUMENT COUNTS (EN vs DE) ──"
for url in /documents.php /company-policies.php /other-documents.php /updates-during-suspension.php /announcements.php; do
  en=$(curl -sk "$BASE$url" | grep -c 'fa-file-pdf')
  sep="?"; [[ "$url" == *"?"* ]] && sep="&"
  de=$(curl -sk "$BASE${url}${sep}lang=de" | grep -c 'fa-file-pdf')
  printf "  %-35s  EN: %3d  DE: %3d\n" "$url" "$en" "$de"
done

echo ""
echo "── 6. NAVIGATION (DE check) ──"
curl -sk "$BASE/?lang=de" | grep -oE 'class="nav-link" href="[^"]+">[^<]+' | head -20 | sed 's|class="nav-link" href="\([^"]*\)">\(.*\)|     \1  →  \2|'

echo ""
echo "════════════════════════════════════════════════════════════════════"
echo "  AUDIT DONE"
echo "════════════════════════════════════════════════════════════════════"
