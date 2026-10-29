# Panel feedback alignment (Group 5)

Mapping of panel suggestions to the system and documentation.

| Panel suggestion | Implementation |
|------------------|----------------|
| **Not public** — repository for school use, not open internet | Login required for all routes except sign-in; optional IP allowlist; login notice states LAN-only |
| **Research Director = administrator** | Role `research_director` manages users, settings, approval, reports |
| **Offline / local application** | XAMPP on LAN; no CDN; bundled mammoth.js and PDF.js |
| **Thorough ethics checking** | Full checklist with passed/failed/pending, remarks required on fail, backend blocks approval |
| **Research objectives visible to users** | `research_objectives` field on submission; shown on study view, search results, and reader |
| **Statement of the problem not limited to abstract** | Separate objectives field; capstone text in `PROJECT_DOCUMENTATION.md` |
| **Audit trail — who reads articles** | `read_logs` on each reader open; Reports → Read logs |
| **Table of contents links in reader** | TOC from headings (DOCX) or pages (PDF); anchor scroll |
| **Remove hyperlinks in RRL** | Reader strips `<a>` tags and neutralizes `http(s)://` text in displayed HTML |
| **Text-to-speech / text-to-voice** | Web Speech API panel on reader (includes objectives) |
| **Auto-search** | Debounced search without Enter (`search.js`) |
| **Auto-suggest** | `search/suggest` endpoint |
| **Custom date range** | Filters on submission, approval, defense dates |
| **Filtration** | Program, year, adviser, type, keyword |
| **Reports** | Director reports, CSV export, print-friendly summary |
| **Context diagram, Level 0 DFD, process flow** | `docs/CONTEXT_DFD.md` |
| **SDLC iterative waterfall** | `docs/SDLC.md` |
| **Scope: online within school** | `docs/PROJECT_DOCUMENTATION.md` § Scope |

## Signatories (reference)

- Chairman: Ferdinand G. Mayol, MSIT  
- Panelist: Melanie N. Arpay, LPT, MIT  
- Panelist: Engr. Veldin A. Talorete Jr., MSCA  
