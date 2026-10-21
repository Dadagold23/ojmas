# OJMAS ENHANCEMENT — MODULE 1 IMPLEMENTATION REPORT

Module: Enhanced Journal Archive, Issue Table of Contents, Author Attribution
and Citation Generation (approved in `docs/ADDENDUM_MODULE_0_AUDIT.md`).

Everything delivered is additive. No existing table, column or enum value list
was modified; no published/draft record was touched; all pre-established URLs
continue to work unchanged.

---

## 1. What was delivered

### 1.1 Database (single migration `2026_10_21_000001_add_addendum_module1_columns`)

| Change | Table | Purpose |
| --- | --- | --- |
| `issue_order` nullable int | `articles` | The journal's recorded editorial sequence inside an issue |
| `licence`, `copyright_note` nullable strings | `articles` | The statement shown on the article page and in citations |
| `orcid` nullable string | `article_authors`, `users` | ORCID iD for co-authors and the corresponding author account |
| `cover_image_path` nullable string | `issues`, `volumes` | Archival cover images per issue (and volume, schema-ready) |

Down migration drops exactly the added columns/index; nothing else.

### 1.2 Ordered attribution — `ArticleBylineService`

One service (`app/Services/Publications/ArticleBylineService.php`) is now the
single source of an article's ordered byline. Convention unchanged from the
submission form: submitting account = order 1 and corresponding; `article_authors`
rows order 2+. Honest safeguards:

- a co-author row carrying the submitting account's own e-mail is skipped
  (the same person, not two people);
- an article with no co-author rows still gets the submitting account alone —
  never an empty byline and never invented names;
- blank/near-blank names are never rendered.

### 1.3 Issues and the archive

- `Issue::orderedArticles()` — recorded `issue_order` first, then deterministic
  published-then-id order for articles without one. Editors never lose what
  they recorded, and unordered articles can never shuffle arbitrarily.
- **Public archive `/archives` reworked to be issue-centric**: published issue
  cards (volume, issue number, month/year, cover image where present, theme,
  live published-article count, full-issue PDF button) with year and volume
  filters, plus the existing full-text article search beneath — searchable and
  organised per brief §1.1.
- **Issue page `/issue/{id}`**: full ToC with sequence numbers, complete ordered
  byline, abstract preview, page range, DOI link, publication date, PDF link.
  Drafts are never listed and unpublished issues 404 (pinned in tests).
- **Article page `/article/{slug}`**: full ordered byline with affiliations,
  ORCID links, corresponding-author mark, DOI link, licence/copyright statement
  where the journal recorded one. URL unchanged.

### 1.4 Editor-side management (server-authorised)

- **`editor.issues.reorder`** — position form per article (`order[id]=position`),
  every id verified to belong to that issue (cross-issue tampering → 422),
  guest access redirects to login (pinned in tests).
- **`editor.issues.upload-cover`** — issue covers: image-only, 2 MB cap,
  validated before the previous cover is ever deleted.

### 1.5 Citation generation — `CitationService`

Deterministic APA 7 (reference-list form, correct comma-ampersand forms, 21+
author truncation), MLA, Chicago, plus **BibTeX** and **RIS** downloads:

- `/article/{slug}/cite/{apa|mla|chicago|bibtex|ris|txt}` — real downloads with
  correct content types (`application/x-bibtex`, `application/x-research-info-systems`);
- the article page's **"Cite this article"** card shows APA 7, MLA and Chicago
  (copyable read-only textareas), BibTeX/RIS/plain-text buttons, and a CSP-safe
  no-JavaScript copy flow;
- **honest degradation**: a missing DOI, page range, volume or issue number is
  never invented — the citation simply omits it and the page lists exactly
  what the formal record lacks in a visible limitations box;
- citation bylines use the same `ArticleBylineService`, so the article page,
  the issue ToC and every citation agree name-for-name.

### 1.6 Scholarly metadata

- Google Scholar tags (`citation_title`, per-author `citation_author`,
  `citation_publication_date`, `citation_journal_title`, `citation_volume`,
  `citation_issue`, `citation_firstpage/lastpage` only when the page range
  actually parses, `citation_doi`, `citation_pdf_url`) — emitted only from
  verified records, never placeholders;
- Schema.org `ScholarlyArticle` JSON-LD (non-executable script type, CSP-safe);
- canonical links and per-page meta descriptions;
- **`/sitemap.xml`** — published issues and articles only; drafts and
  unpublished issues can never appear (pinned in tests).

### 1.7 ORCID capture (forward-only)

- submission form: optional ORCID per co-author row and for the corresponding
  author (stored on the user account so it applies to every future byline);
- 16 bare digits are canonicalised to the hyphenated form; anything that is
  not an identifier is rejected silently rather than stored — no fake data;
- editorial post-publication correction UI was **not** built in this module
  (forward-only capture; a future authorisation can add it).

### 1.8 Licence and copyright capture

The admin publish form (already repointed to the pipeline) can record the
licence and copyright statement; `PublicationService::publish()` carries an
optional additive `$attributes` argument (existing callers unaffected) — and
whatever is recorded appears verbatim on the public article page (pinned).

---

## 2. Verification

- New suite `tests/Feature/Addendum/Module1ArchiveCitationsTest.php`:
  15 tests (96 assertions), 1 skipped — the cover-image upload test requires
  PHP GD, which is not installed on this local PHP; the test marks itself
  skipped with that reason rather than faking a pass.
- **Whole application suite after the work: 318 passed, 1 skipped, 0 failed**
  (1446 assertions) — the previous 303 tests remain green.
- Reordered issues publish their new order to readers immediately (pinned).
- Citation exports verified for deterministic content, headers and 404 on
  unknown formats; unpublished material unreachable in every new surface.

## 3. Engineering notes (documented deliberately)

1. **Blade adjacent-directive edge**: `@endif@if(...)`/`@endif@endforeach`
   written with no whitespace between directives compiled partially in this
   environment (one directive emitted raw, causing a PHP parse error in the
   compiled template). All new templates keep directives on their own lines.
   If a future template breaks with `unexpected token "endif"`, look for that
   pattern first, and note compiled caches under `storage/framework/views`
   can also go stale (`php artisan view:clear` after surgery).
2. `new Collection(...)->method()` chaining requires PHP ≥ 8.4; on PHP 8.2 a
   plain accumulation loop is used instead.
3. `YEAR()` SQL is not portable to the test database; the archive's year list
   is computed from dates in PHP and works on both MySQL and SQLite.
4. A stray UTF-8 BOM (from a PowerShell rewrite) broke `Volume.php` parsing
   mid-way; it was removed and the file verified.

## 4. Open items flagged (not changed without authorisation)

1. **`EditorIssueController::attachArticles` still publishes accepted
   submissions into issues directly** (publication-fee check only) — a
   pre-existing path that bypasses the Module 4 readiness gate. Out of Module 1
   scope; recommend repointing it through the gate in a later authorisation.
2. GD is absent locally; cover upload itself is queued-server verified only
   via validation-path, not full image processing.
3. Volume covers have a column but no upload UI (brief only addressed issue
   covers); trivial to add if wanted.
4. Editorial correction of author rows after submission is still future work.
5. Journal title/ISSN now read from `config/journal.php` (`JOURNAL_TITLE`,
   `JOURNAL_ISSN` env) — set them in production `.env` for citation metadata.
