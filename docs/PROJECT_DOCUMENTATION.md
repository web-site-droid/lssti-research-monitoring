# Project documentation excerpts (for capstone revision)

**Title:** Lanao School of Science and Technology Digital Research Repository  
**SDLC:** Iterative waterfall  
**Platform:** Web-based (PHP, MySQL), deployed on XAMPP on the school LAN  

---

## Statement of the problem

1. Undergraduate research outputs at LSSTI are often stored in scattered folders or individual adviser copies, making retrieval, ethics tracking, and consistent approval difficult.

2. There is no centralized, **school-controlled** system that supports the full **research function**—from submission and ethics review through approval, search, reading, and reporting—while keeping manuscripts **off the public internet**.

3. Faculty and students need **auto-search**, **filtration**, **custom date ranges**, and **read-aloud** access to **approved** studies without relying on external online services.

4. The Research Director lacks **audit trails** (who read which study) and **summary reports** for compliance, plagiarism review, and pending workflows.

*(Problem #2 is stated separately from the abstract; objectives below align to these problems.)*

---

## Objectives

**General objective:** To design and implement a web-based digital research repository for LSSTI undergraduate studies that operates on the local school network.

**Specific objectives:**

1. To manage users and role-based access with the **Research Director** as system administrator.  
2. To support study submission, versioning, and revision workflows.  
3. To enforce **thorough ethics review** and block approval when requirements fail.  
4. To provide **auto-search**, **auto-suggest**, **filtration**, and **custom date range** for approved studies only.  
5. To deliver an in-browser **reader** with **table of contents** navigation, **text-to-speech**, and no active hyperlinks in displayed literature text.  
6. To record **read logs** and **audit trails** and to **generate reports** for institutional use.

---

## Scope

**Included:** Web application hosted on a **school PC (XAMPP)** accessible via **Wi‑Fi/LAN** to authenticated LSSTI users; modules for users, submission, ethics/review, search/read, logs/reports; offline vendor libraries.

**Excluded:** Public internet hosting, open registration, email notifications, external plagiarism APIs, and access to unapproved manuscripts by readers.

**Delimitation:** The word *repository* here means an **institutional research management and reading system**, not a worldwide open-access portal.

---

## Introduction note (formatting)

Where the introduction lists items in series, use **commas** between clauses rather than em dashes, per panel comment (apply when pasting into the formal chapter document).
