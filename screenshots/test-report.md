# PédagoLens — Post-CSS-Fix Test Report

**Date:** Auto-generated after CSS deployment  
**Base URL:** `http://pedagolens.34.199.149.247.nip.io`  
**Method:** Cache-busted reload (`ignoreCache: true`) + JS `pl-*` class audit + layout check  

---

## Overall Status: ⚠️ PARTIAL PASS (6/7 pages OK)

---

## Per-Page Results

### 1. `/dashboard-enseignant/` — ✅ PASS
| Metric | Value |
|---|---|
| Missing `pl-*` classes | **0** |
| Admin bar height | 32px |
| Sidebar top | 32px |
| Header top | 32px |
| Main padding | 104px 0px 0px |
| Main content padding | N/A (element absent) |
| Screenshot | `screenshots/post-fix-dashboard-enseignant.png` |

### 2. `/dashboard-etudiant/` — ✅ PASS
| Metric | Value |
|---|---|
| Missing `pl-*` classes | **0** |
| Admin bar height | 32px |
| Sidebar top | 32px |
| Header top | 32px |
| Main padding | 104px 0px 0px |
| Main content padding | N/A (element absent) |
| Screenshot | `screenshots/post-fix-dashboard-etudiant.png` |

### 3. `/compte/` — ✅ PASS
| Metric | Value |
|---|---|
| Missing `pl-*` classes | **0** |
| Admin bar height | 32px |
| Sidebar top | 32px |
| Header top | 32px |
| Main padding | 104px 0px 0px |
| Main content padding | N/A (element absent) |
| Screenshot | `screenshots/post-fix-compte.png` |

### 4. `/institutionnel/` — ✅ PASS
| Metric | Value |
|---|---|
| Missing `pl-*` classes | **0** |
| Admin bar height | 32px |
| Sidebar top | 32px |
| Header top | 32px |
| Main padding | 104px 0px 0px |
| Main content padding | N/A (element absent) |
| Screenshot | `screenshots/post-fix-institutionnel.png` |

### 5. `/cours-projets/` — ✅ PASS
| Metric | Value |
|---|---|
| Missing `pl-*` classes | **0** |
| Admin bar height | 32px |
| Sidebar top | 32px |
| Header top | 32px |
| Main padding | 104px 0px 0px |
| Main content padding | N/A (element absent) |
| Screenshot | `screenshots/post-fix-cours-projets.png` |

### 6. `/historique/` — ✅ PASS
| Metric | Value |
|---|---|
| Missing `pl-*` classes | **0** |
| Admin bar height | 32px |
| Sidebar top | 32px |
| Header top | 32px |
| Main padding | 104px 0px 0px |
| Main content padding | N/A (element absent) |
| Screenshot | `screenshots/post-fix-historique.png` |

### 7. `/parametres/` — ❌ FAIL (Critical WP Error)
| Metric | Value |
|---|---|
| Missing `pl-*` classes | **0** (page didn't render) |
| Admin bar height | 0 (absent) |
| Sidebar top | N/A |
| Header top | N/A |
| Main padding | N/A |
| Main content padding | N/A |
| Screenshot | `screenshots/post-fix-parametres.png` |
| **Error** | `"Une erreur critique est survenue sur votre site."` |

---

## Layout Consistency (pages 1–6)

All 6 working pages share identical layout values:
- **Admin bar:** 32px height (WP admin bar present, user logged in)
- **Sidebar top:** 32px (correctly offset below admin bar)
- **Header top:** 32px (correctly offset below admin bar)
- **Main padding-top:** 104px (header 72px + admin bar 32px = 104px ✅)
- **`.pl-app-main-content`:** Element not found on any page (may use different class or structure)

---

## Remaining Issues

1. **`/parametres/` — Critical WordPress error.** The page returns a fatal PHP error and does not render at all. This is a server-side plugin/theme issue, not CSS-related. Check `wp-content/debug.log` and Apache error logs for the root cause.

2. **`.pl-app-main-content` absent on all pages.** The JS audit looked for this element but it was not found on any page. Either the class name has changed or this container is not used in the current templates. Not a blocker — just a note for future reference.

---

## CSS Verdict

**All `pl-*` CSS classes used in HTML are defined in stylesheets across all 6 rendering pages. Zero missing classes detected. The CSS deployment is clean.**
