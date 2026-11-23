# UTHM Institutional Research

Research date: 2026-11-24. Method: official UTHM pages were read in full and the two RMC KPI reports (PDF) were text-extracted. Search-engine summaries were used only to find pages, never as evidence. Nothing here required a login, and no private system was accessed. No individual staff member's name, email or phone number is recorded in this document.

**Labels.** **VERIFIED** = supported by an official page or document read directly. **HISTORICAL** = official but old, may have changed. **UNVERIFIED** = not sufficiently supported. **ASSUMPTION** = a hypothesis needing confirmation. **RECOMMENDATION** = a proposed ARAMS decision.

**A caution on dates.** Every `uthm.edu.my` page read carries the same footer line, "Last Modified: Friday 20 November 2026", and every `icc.uthm.edu.my` page carries 28 September 2026 beside a 2020 copyright. These look like site-wide template timestamps, not per-page edit dates. They show the pages were live and recently deployed, not that each fact was reviewed that day.

---

## 1. Executive Summary

- UTHM's organisation in ARAMS is **complete**: ARAMS's 12 units match, by name, the 8 faculties plus Johor Business School and 3 academic centres that UTHM's own RMC uses as its reporting units. No unit is missing and none is invented.
- **Five of the 12 unit codes do not match the codes UTHM uses.** ARAMS inherited them from ARAMS 1.0: `FKAAS` (Applied Sciences; UTHM says `FAST`), `FKTN` (Engineering Technology; `FTK`), and `CLS`/`CDS`/`CGSC` (UTHM: `PPB`/`PPD`/`PPUK`). Worse, `FKAAS` is the *retired* UTHM code for the Civil faculty (now `FKAAB`), so it is actively misleading to a UTHM reader. The affected records are attributed to the correct *named* faculty; only the label is wrong.
- UTHM measures research through a **monthly RMC KPI report** with a stable structure (publications by type and index, collaboration, citations, funds by source). ARAMS's data model already captures nearly all of it. The real gaps are per-publication citation counts and a PI-headcount indicator.
- **Scale is ten times what ARAMS has seen.** UTHM's public expert dashboard reports about 1,136 active academic staff and about 21,000 Scopus-indexed publications; ARAMS 1.0 held 105 accounts.
- Several integrations are plausible (public UEMAS dashboard, MyRA journal lists, ORCID, Scopus) but **none is usable without authorisation**, and nothing here authorises scraping.
- Nothing in this research conflicts with locked decisions D1–D6.

## 2. Institutional Profile

| Finding | Status | Source (read 2026-11-24) | ARAMS impact |
|---|---|---|---|
| Public university in Batu Pahat, Johor. Founded 16 Sep 1993 as Pusat Latihan Staf Politeknik; became Institut Teknologi Tun Hussein Onn (1996), Kolej Universiti Teknologi Tun Hussein Onn (2000), Universiti Tun Hussein Onn Malaysia (2006; name change announced 2007) | VERIFIED | https://www.uthm.edu.my/en/about | Name of record for documents and the UI |
| Vision: a global technical university in sustainable technology and transportation. Mission: technical solutions for industry and community on a tauhidic paradigm. Motto: "With Wisdom, We Explore." Direction: Global Technopreneur University 2030 (#GTU2030) | VERIFIED | https://www.uthm.edu.my/en/about/overview/corporate-profile | Wording for the About/footer text only |
| Main Campus at Parit Raja, Batu Pahat; Pagoh Branch Campus (Hab Pendidikan Tinggi Pagoh, Panchor); further land at Tanjung Laboh (327.34 acres) | VERIFIED | same two pages | ARAMS has no campus dimension today; see section 11 |
| Headline figures: about 22.3K enrolled students, 68.1K alumni (no year printed) | VERIFIED as displayed, undated | same | Context only; do not store |
| Staff numbers are not given on the profile pages | n/a | same | See section 7 for the only staff figure found |

## 3. Faculty and Academic Structure

Source: https://www.uthm.edu.my/en/about/overview/faculty-and-office (footer 20 Nov 2026). **VERIFIED** list of faculties, in the order printed:

1. Faculty of Civil Engineering and Built Environment (page note: formerly Faculty of Civil and Environmental Engineering)
2. Faculty of Electrical and Electronic Engineering
3. Faculty of Mechanical and Manufacturing Engineering
4. Faculty of Technology Management and Business
5. Faculty of Technical and Vocational Education
6. Faculty of Computer Science and Information Technology
7. Faculty of Applied Sciences and Technology (printed with the abbreviation **FAST**)
8. Faculty of Engineering Technology

Other academic units on the same list: Centre for Graduate Studies, Centre for Diploma Studies, Centre for General Studies and Co-curricular, Centre for Language Studies, Johor Business School, RailX UTHM. Institutes and support centres (BITARA, TRANSFORM, ISWJ, MyREI, and others) are listed but do not employ lecturers in the sense ARAMS models.

The page prints an abbreviation only for FAST. The full abbreviation set comes from RMC's own KPI report (section 6), which lists the reporting units as **FKAAB, FKEE, FKMP, FPTV, FPTP, FAST, FSKTM, FTK, JBS, PPB, PPD, PPUK, NA, OTHERS** (monthly report, cut-off May 2025; https://rmc.uthm.edu.my/images/KPI/2025/KPI_MAY_2025.pdf).

Mapping ARAMS units to those codes:

| ARAMS code (inherited from 1.0) | Name in ARAMS | UTHM reporting code | Status of the mapping |
|---|---|---|---|
| FSKTM, FKEE, FKMP, FKAAB, FPTP, FPTV, JBS | as official | same | VERIFIED |
| **FKAAS** | Applied Sciences and Technology | **FAST** | VERIFIED (FAST printed beside this name on the official page) |
| **FKTN** | Engineering Technology | **FTK** | VERIFIED by elimination: FTK is the only RMC faculty code left once the other seven faculties are matched |
| **CDS** | Centre for Diploma Studies | **PPD** | VERIFIED: the centre's own site is titled "Pusat Pengajian Diploma" |
| **CLS** | Centre for Language Studies | **PPB** | ASSUMPTION (high confidence): by elimination and the Malay word *Bahasa* |
| **CGSC** | Centre for General Studies and Co-curricular | **PPUK** | ASSUMPTION (high confidence): by elimination and *Umum dan Kokurikulum* |

**HISTORICAL:** `FKAAS` was the code of the Civil and Environmental Engineering faculty (the official page records the rename; faculty sites under both `fkaas.` and `fkaab.` subdomains appear in search results, which was not followed up). `FSTPi` (Science, Technology and Human Development) appears only in a 2015 management report and in no current list.

## 4. Research Ecosystem

**Research Management Centre (RMC)**: https://rmc.uthm.edu.my (footer 7 Oct 2026). VERIFIED objectives (paraphrased): implement research policy and guidelines; run initiatives building research among staff and students; plan activity to raise grant income from varied sources; provide a researcher support system; monitor progress so outputs meet funders' requirements; **manage research and publication data for reporting to relevant parties**. The RMC site organises its content as Internal Grants and External Grants (MOHE, MOSTI, MTUN, KKMM, INSPEN, Others), Research Ethics, Research Monitoring, Research Performance, MyRA, and downloadable forms. The unit structure is published only as an image ("Struktur Organisasi RMC May 2026") that could not be read; **UNVERIFIED**.

**Innovation Commercialisation Centre (ICC)**: https://icc.uthm.edu.my. VERIFIED: handles pre-commercialisation (upscaling, incubation); registers four IP types (patent, copyright, trademark, industrial design); commercialises through licensing, direct sales, equity ownership and contract research; administers three grants: **GPP** (Product Development Grant, TRL 4–6), **ICI** (Innovation and Commercialisation Incubator, TRL 7–9), **MCF** (MTUN Commercialization Fund, TRL 7–9). Its yearly objectives are stated as at least 5 patents granted, 100 IP applications protected and 10 IP commercialised with industry partners (the page carries a 2020 copyright, so treat as **HISTORICAL**). A homepage dashboard shows 66 patents granted, 105 filed, 1,394 copyrights, 19 industrial designs, 31 trademarks (undated).

**Centres of excellence, research centres, focus groups.** Two official pages disagree:

- `faculty-and-office` lists five CoEs: MiNT-SRC, AMMC, EMCentre, RECESS, MyRIVET, plus eleven research centres and focus groups (JRC, SDRC, MPRC, ATRC, CEIES, MPROVE, PREMACH, CeFM, SMC, CERCOM, SUNR).
- `research-group/centre-of-excellence-at-iie` lists six: RECESS, EMCenter, MiNT-SRC, AMMC, a TVET CoE (established 1 Sep 2017) and the Industry CoE for Railway; it omits MyRIVET.

Verdict: **UNVERIFIED which list is authoritative.** The institute named in the URL (Institute for Integrated Engineering) is not stated on the page as the host.

## 5. Research Management Structure

- RMC owns policy, grants, monitoring and reporting data. ICC owns IP and commercialisation. Both sit under the Research and Innovation menu alongside the Institute for Integrated Engineering, Centres of Excellence, My ExpertFinder and Focus Groups (VERIFIED, `innovation-in-uthm` page).
- National fund progress and final reports are submitted through **MyGRANTS-DP KPT** (VERIFIED, RMC research-monitoring page, which holds manuals dated 2021–2022 and forms dated up to Sep 2026).
- RMC's KPI report names its own data sources: funds from "**RMS**" and publications from "**Scopus, SMPU, Pejabat Penerbit & MyCite**". The expansions of RMS and SMPU are **UNVERIFIED** (not stated in anything read); do not assume them.
- Deputy Vice-Chancellor (Research and Innovation) oversight is implied by RMC's directory links; the reporting line was not verified.

## 6. Research Performance Indicators

Source: RMC's monthly "KPI Performance for Publication & Funds", indexed at https://rmc.uthm.edu.my/research-performance (2020–2026, one report per month; page footer 7 Oct 2026). Two reports were read: cut-off January 2024 and May 2025. **VERIFIED structure**, stable across both:

- **Publications**, counted per faculty against a yearly target: Scopus journal; Scopus proceedings; MyCite journal; other publications; research book; book chapter; citation count; national joint, international joint and industry joint publications (all Scopus); institutional H-index; and **number of principal investigators** expressed as a share of academic staff.
- **Funds**, counted per faculty against a yearly target: national, international and private funds; research funds versus non-research funds; external funds; in-kind funds.
- **Reporting units**: the 12 units in section 3, plus NA and OTHERS buckets.
- **Display**: percentage-achieved with four colour bands (under 45%, 45–80%, 81–100%, over 100%).
- **Data cut-off** is stated on every report; funds and publications come from different systems with their own cut-offs.

Figures printed in the January 2024 report (institutional targets, **VERIFIED as printed**): Scopus journal 1,100; MyCite journal 1,000; Scopus proceedings 400; other publications 1,000; citations 45,000; national, international and industry joint publications 300 each; PIs 75%; funds RM24.0M (national 18.5M, international 1.5M, private 4.0M). The May 2025 report revises the figures (for example a total fund target of RM16.0M, citations 50,000); because the infographic scrambles the order of the numbers in text extraction, **which 2025 number belongs to which indicator is UNVERIFIED** and was not recorded. Open the PDF visually before quoting any 2025 target.

**MyRA** (Malaysia Research Assessment): RMC publishes glossaries (2018, 2023, 2024 amendment), evidence lists, scope (2024), MyCite-indexed journal lists (2020–2024) and MAPIM publisher lists (VERIFIED, https://rmc.uthm.edu.my/myra; several sections are marked "TBA"). A UTHM news item about a 2014 MyRA result surfaced in search (**HISTORICAL**; page not opened). A 2022 SETARA listing rating UTHM "competitive" appears only in secondary sources (**HISTORICAL**, unconfirmed as current).

**These targets belong to UTHM, not to ARAMS.** ARAMS must not hard-code them. Targets stay assigned by a TDPP or Admin, as already built.

## 7. Existing Digital Ecosystem

| System | What is verified | Status |
|---|---|---|
| **UEMAS / UTHM Expert** (https://myexpertfinder.uthm.edu.my) | Public dashboard; the footer reportedly credits the university's IT Centre (read from a summarised part of the page, so not verbatim). Shows aggregate counts that move over time: about 21,358 Scopus-indexed publications, 6,831 research grants, 1,136 active academic staff (an earlier snapshot read 21,005 / 6,792 / 1,139). Per-person pages exist under `/dashboard/view/`. | Aggregates VERIFIED; whether per-person pages are open to anonymous visitors and what reuse is permitted are **UNVERIFIED**; no data-use notice was seen |
| **RMS** (funds data source) and **MyGRANTS-DP KPT** | Named as sources; access not public | UNVERIFIED beyond the name |
| **UTHM Institutional Repository** | Publicly indexed by CORE with about 11,917 outputs | Secondary source |
| **UTHM Community** profile pages | Staff profile pages exist per person | Not reviewed; personal data |
| **MyRA / MyCite / MAPIM lists** | Public downloadable lists (2020–2026) | VERIFIED as published |

## 8. Potential ARAMS Integration Opportunities

None of these is implemented, and none may be built without the stated authorisation.

| Opportunity | Academic / institutional value | Data and technical need | Security and authorisation |
|---|---|---|---|
| Validate "MyCite" and publisher flags against the MyRA lists RMC publishes | Fewer mis-classified outputs; matches how MyRA counts | Periodic import of public spreadsheets; low complexity | Public documents; confirm reuse terms with RMC |
| Scopus / Web of Science metadata (citations, indexing) | Fills the citation gap in section 6 | API key, institutional entitlement, per-researcher ID matching (ARAMS stores external IDs) | Needs a licence and key held server-side; never commit keys |
| ORCID | Researcher-controlled profile linking | OAuth with consent per researcher | Consent required; public API terms apply |
| UEMAS export | Cross-check ARAMS totals against the university's own figures | A sanctioned export or API from the IT Centre | **Do not scrape.** Ask RMC/IT Centre for an official feed |
| RMS / MyGRANTS-DP feed | Avoids double entry of grants | Not publicly documented | Private; needs written authorisation |
| Public repository metadata | Verify publication existence | OAI-PMH is standard for repositories | Check terms; low sensitivity |

## 9. Verified Facts (summary)

1. Official name, history, campuses, vision, mission, motto, GTU2030 (section 2).
2. The 8-faculty list and the 6 other academic centres (section 3).
3. RMC's six objectives; RMC handles research and publication reporting data (section 4).
4. ICC's four IP types, four commercialisation routes, and GPP/ICI/MCF with their TRL bands (section 4).
5. RMC's monthly KPI report structure, its 12 reporting units, its data-source notes, colour bands, and the January 2024 institutional targets (section 6).
6. UEMAS exists as a public dashboard with aggregate counts (section 7).
7. ARAMS's 12 units correspond one-to-one with those reporting units by name.
8. Five ARAMS unit codes differ from UTHM's (section 3); three of the five replacements are verified.

## 10. Unverified Information

- Which centre-of-excellence list is authoritative (section 4).
- The RMC unit structure (image only).
- Expansions of RMS and SMPU, and exactly what each contains.
- The mapping of 2025 KPI targets to indicators (section 6).
- The code mapping CLS → PPB and CGSC → PPUK (assumption, high confidence).
- Whether UEMAS per-person pages are public, and under what reuse terms.
- How RMC classifies a grant as "private" versus "industry" or "NGO".
- Current SETARA and MyRA standing.
- Eligibility rules for GPP, ICI and MCF (not stated on the pages).
- Any staff-number figure beyond the live UEMAS dashboard count.

## 11. Architectural Implications

All items are **RECOMMENDATION**. None reverses D1–D6.

1. **Align unit codes with UTHM's reporting codes.** Store UTHM's code as the display and reporting code and keep the ARAMS 1.0 code in a `legacy_code` column, so the migrator can still match legacy rows by their original code. Renames do not touch foreign keys, which use ids. Do the three verified renames (FAST, FTK, PPD); hold PPB and PPUK until RMC confirms. This also removes the risk that a UTHM reader takes `FKAAS` to mean Civil.
2. **Reporting units are not all faculties.** RMC reports 4 non-faculty units (JBS, PPB, PPD, PPUK) and NA/OTHERS buckets. ARAMS already stores them in `faculties`; reports should show them the way RMC does and give unattributed records an explicit "NA/Others" row rather than dropping them (this fits D4's unknown-date handling).
3. **KPI model.** Existing measures already cover publications, grants, grant value, IP, income and H-index; the schema already records publication type, quartile, indexing (Scopus, WoS, MyCite), and the national/international/industry collaboration flags that RMC's joint-publication indicators need. Real gaps: per-publication **citation count**, a **PI-headcount** indicator (derivable from grant participation), and a **fund-source view** (national/international/private) whose mapping from ARAMS grant levels needs RMC's definition first.
4. **Reference data must be administrable.** Two official pages disagree on centres of excellence and the list changes over time, so research-group categories and memberships should be editable data with effective dates, not code. This matches the enterprise review's reference-data finding.
5. **Plan for ten times the scale.** About 1,100 academic staff and 21,000+ publications versus 105 accounts: pagination, eager loading and indexing (Stage 6) now have a concrete target.
6. **Do not store UTHM's targets.** They are institutional and change yearly; ARAMS keeps assigning its own per-period targets.
7. **Campus** is not modelled and nothing found requires it. Not recommended.
8. **TRL** (technology readiness level) is central to ICC's grants and absent from ARAMS IP records. Optional future field, only if the ICC asks for it.
9. **D5 benchmarking** remains viable and improves: the 12 reporting units give a larger cohort pool than the 6 faculties that held data in 1.0, which is what minimum-cohort suppression needs. The threshold itself is a D5 decision, not set here.

## 12. Official Source References

All read 2026-11-24.

- https://www.uthm.edu.my/en/about
- https://www.uthm.edu.my/en/about/overview/corporate-profile
- https://www.uthm.edu.my/en/about/overview/faculty-and-office
- https://www.uthm.edu.my/en/research-group/centre-of-excellence-at-iie
- https://www.uthm.edu.my/en/research-innovation/innovation-in-uthm
- https://rmc.uthm.edu.my/ , https://rmc.uthm.edu.my/profile/objectives , https://rmc.uthm.edu.my/index.php/research-monitoring , https://rmc.uthm.edu.my/profile/organization-chart
- https://rmc.uthm.edu.my/research-performance , https://rmc.uthm.edu.my/myra
- https://rmc.uthm.edu.my/images/KPI/2024/KPI_JANUARY_2024.pdf , https://rmc.uthm.edu.my/images/KPI/2025/KPI_MAY_2025.pdf
- https://icc.uthm.edu.my/ , https://icc.uthm.edu.my/about , https://icc.uthm.edu.my/commercialisation/grants/gpp , `/ici` , `/mcf`
- https://myexpertfinder.uthm.edu.my/dashboard/home
- https://ppd.uthm.edu.my (Pusat Pengajian Diploma)
- Secondary, pointers only: CORE listing for the repository (https://core.ac.uk/data-providers/434), UTHM news on MyRA 2014 (https://news.uthm.edu.my/en/2015/12/uthm-achieved-three-star-ratings-in-malaysia-research-assessment-myra-2014/).
