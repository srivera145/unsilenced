# Spot-check: stored Clery figures next to the source rows

Generated 2026-10-01 from the dev database after the Phase 1.2 import, with:

    php database/console.php clery:spot-check 190415 204796 216287 119164 135717

How to read it. For each year and location, **Stored: sum of campuses** is the figure Unsilenced keeps for the school. Under it, each campus (UNITID_P) shows the figure we keep for that campus and which file it came from, then that campus's raw cells (`↳ raw`) in every imported file that covers the year, newest file first. `Oncampus*222324` means Oncampuscrime222324.csv (rape, fondling) and Oncampusvawa222324.csv (dating violence, domestic violence, stalking). `blank` is an empty cell in the source. For each campus the newest file with a figure wins. **Total on the page** is what the school page shows: on campus + noncampus + public property (student housing is already part of on campus).

The Campus Safety website (https://ope.ed.gov/campussafety/) shows one campus at a time and, by default, the newest survey (2022–2024). Compare a campus's `↳ raw` row from the `*222324` files with the site, and our stored sum with the sum of the campus rows.

## Cornell University (UNITID 190415)

Ithaca, NY · Private nonprofit · 26,793 students (IPEDS 2024) · page: /schools/ny/cornell-university

Campus rows in the files (UNITID_P, BRANCH): 190415001 Endowed and Contract College Campuses; 190415002 Cornell Tech Campus; 190415003 Cornell AgriTech Campus.

| Year | Location | Row | Rape | Fondling | Dating violence | Domestic violence | Stalking |
|---|---|---|--:|--:|--:|--:|--:|
| 2024 | On campus | **Stored: sum of campuses** | **23** | **21** | **24** | **1** | **23** |
|  |  | 190415001 stored (from the 2022–24 file) | 23 | 21 | 24 | 1 | 22 |
|  |  | ↳ raw, Oncampus*222324 | 23 | 21 | 24 | 1 | 22 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 1 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | On-campus student housing | **Stored: sum of campuses** | **21** | **11** | **19** | **1** | **6** |
|  |  | 190415001 stored (from the 2022–24 file) | 21 | 11 | 19 | 1 | 5 |
|  |  | ↳ raw, Residencehall*222324 | 21 | 11 | 19 | 1 | 5 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Residencehall*222324 | 0 | 0 | 0 | 0 | 1 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | Noncampus | **Stored: sum of campuses** | **2** | **1** | **0** | **1** | **0** |
|  |  | 190415001 stored (from the 2022–24 file) | 1 | 1 | 0 | 1 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 1 | 1 | 0 | 1 | 0 |
|  |  | 190415002 stored (from the 2022–24 file) | 1 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 1 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 190415001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | **Total on the page** (on campus + noncampus + public property) |  | **25** | **22** | **24** | **2** | **23** |
| 2023 | On campus | **Stored: sum of campuses** | **28** | **22** | **35** | **8** | **41** |
|  |  | 190415001 stored (from the 2022–24 file) | 28 | 22 | 35 | 7 | 40 |
|  |  | ↳ raw, Oncampus*222324 | 28 | 22 | 35 | 7 | 40 |
|  |  | ↳ raw, Oncampus*212223 | 28 | 22 | 35 | 7 | 40 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 1 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 1 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 1 | 1 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | On-campus student housing | **Stored: sum of campuses** | **25** | **6** | **35** | **8** | **20** |
|  |  | 190415001 stored (from the 2022–24 file) | 25 | 6 | 35 | 7 | 19 |
|  |  | ↳ raw, Residencehall*222324 | 25 | 6 | 35 | 7 | 19 |
|  |  | ↳ raw, Residencehall*212223 | 25 | 6 | 35 | 7 | 19 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 1 | 1 |
|  |  | ↳ raw, Residencehall*222324 | 0 | 0 | 0 | 1 | 1 |
|  |  | ↳ raw, Residencehall*212223 | 0 | 0 | 0 | 1 | 1 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | Noncampus | **Stored: sum of campuses** | **0** | **1** | **3** | **2** | **3** |
|  |  | 190415001 stored (from the 2022–24 file) | 0 | 1 | 3 | 2 | 3 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 1 | 3 | 2 | 3 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 1 | 3 | 2 | 3 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | Public property | **Stored: sum of campuses** | **0** | **0** | **1** | **0** | **0** |
|  |  | 190415001 stored (from the 2022–24 file) | 0 | 0 | 1 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 1 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 1 | 0 | 0 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | **Total on the page** (on campus + noncampus + public property) |  | **28** | **23** | **39** | **10** | **44** |
| 2022 | On campus | **Stored: sum of campuses** | **25** | **24** | **40** | **0** | **29** |
|  |  | 190415001 stored (from the 2022–24 file) | 25 | 24 | 40 | 0 | 28 |
|  |  | ↳ raw, Oncampus*222324 | 25 | 24 | 40 | 0 | 28 |
|  |  | ↳ raw, Oncampus*212223 | 25 | 24 | 40 | 0 | 28 |
|  |  | ↳ raw, Oncampus*202122 | 25 | 24 | 40 | 0 | 28 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 1 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | On-campus student housing | **Stored: sum of campuses** | **21** | **22** | **35** | **0** | **12** |
|  |  | 190415001 stored (from the 2022–24 file) | 21 | 22 | 35 | 0 | 12 |
|  |  | ↳ raw, Residencehall*222324 | 21 | 22 | 35 | 0 | 12 |
|  |  | ↳ raw, Residencehall*212223 | 21 | 22 | 35 | 0 | 12 |
|  |  | ↳ raw, Residencehall*202122 | 21 | 22 | 35 | 0 | 12 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | Noncampus | **Stored: sum of campuses** | **5** | **2** | **3** | **0** | **1** |
|  |  | 190415001 stored (from the 2022–24 file) | 5 | 2 | 3 | 0 | 1 |
|  |  | ↳ raw, Noncampus*222324 | 5 | 2 | 3 | 0 | 1 |
|  |  | ↳ raw, Noncampus*212223 | 5 | 2 | 3 | 0 | 1 |
|  |  | ↳ raw, Noncampus*202122 | 5 | 2 | 3 | 0 | 1 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 190415001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | **Total on the page** (on campus + noncampus + public property) |  | **30** | **26** | **43** | **0** | **30** |
| 2021 | On campus | **Stored: sum of campuses** | **7** | **19** | **10** | **2** | **18** |
|  |  | 190415001 stored (from the 2021–23 file) | 7 | 19 | 10 | 2 | 17 |
|  |  | ↳ raw, Oncampus*212223 | 7 | 19 | 10 | 2 | 17 |
|  |  | ↳ raw, Oncampus*202122 | 7 | 19 | 10 | 2 | 17 |
|  |  | 190415002 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 1 |
|  |  | 190415003 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | On-campus student housing | **Stored: sum of campuses** | **7** | **15** | **10** | **2** | **10** |
|  |  | 190415001 stored (from the 2021–23 file) | 7 | 15 | 10 | 2 | 10 |
|  |  | ↳ raw, Residencehall*212223 | 7 | 15 | 10 | 2 | 10 |
|  |  | ↳ raw, Residencehall*202122 | 7 | 15 | 10 | 2 | 10 |
|  |  | 190415002 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | Noncampus | **Stored: sum of campuses** | **2** | **1** | **1** | **0** | **1** |
|  |  | 190415001 stored (from the 2021–23 file) | 2 | 1 | 1 | 0 | 1 |
|  |  | ↳ raw, Noncampus*212223 | 2 | 1 | 1 | 0 | 1 |
|  |  | ↳ raw, Noncampus*202122 | 2 | 1 | 1 | 0 | 1 |
|  |  | 190415002 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 190415001 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415002 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | **Total on the page** (on campus + noncampus + public property) |  | **9** | **20** | **11** | **2** | **19** |
| 2020 | On campus | **Stored: sum of campuses** | **13** | **6** | **17** | **2** | **14** |
|  |  | 190415001 stored (from the 2020–22 file) | 13 | 5 | 17 | 2 | 14 |
|  |  | ↳ raw, Oncampus*202122 | 13 | 5 | 17 | 2 | 14 |
|  |  | 190415002 stored (from the 2020–22 file) | 0 | 1 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 1 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | On-campus student housing | **Stored: sum of campuses** | **10** | **2** | **17** | **1** | **5** |
|  |  | 190415001 stored (from the 2020–22 file) | 10 | 1 | 17 | 1 | 5 |
|  |  | ↳ raw, Residencehall*202122 | 10 | 1 | 17 | 1 | 5 |
|  |  | 190415002 stored (from the 2020–22 file) | 0 | 1 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*202122 | 0 | 1 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Residencehall*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | Noncampus | **Stored: sum of campuses** | **0** | **1** | **0** | **0** | **1** |
|  |  | 190415001 stored (from the 2020–22 file) | 0 | 1 | 0 | 0 | 1 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 1 | 0 | 0 | 1 |
|  |  | 190415002 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 190415003 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | Public property | **Stored: sum of campuses** | **0** | **2** | **0** | **0** | **0** |
|  |  | 190415001 stored (from the 2020–22 file) | 0 | 2 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 2 | 0 | 0 | 0 |
|  |  | 190415002 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 190415003 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | **Total on the page** (on campus + noncampus + public property) |  | **13** | **9** | **17** | **2** | **15** |

Student housing never exceeds on campus for this school.

## Ohio State University-Main Campus (UNITID 204796)

Columbus, OH · Public · 61,443 students (IPEDS 2024) · page: /schools/oh/ohio-state-university-main-campus

Campus rows in the files (UNITID_P, BRANCH): 204796001 Main Campus.

| Year | Location | Row | Rape | Fondling | Dating violence | Domestic violence | Stalking |
|---|---|---|--:|--:|--:|--:|--:|
| 2024 | On campus | **Stored: sum of campuses** | **58** | **62** | **31** | **20** | **71** |
|  |  | 204796001 stored (from the 2022–24 file) | 58 | 62 | 31 | 20 | 71 |
|  |  | ↳ raw, Oncampus*222324 | 58 | 62 | 31 | 20 | 71 |
| 2024 | On-campus student housing | **Stored: sum of campuses** | **43** | **14** | **12** | **1** | **14** |
|  |  | 204796001 stored (from the 2022–24 file) | 43 | 14 | 12 | 1 | 14 |
|  |  | ↳ raw, Residencehall*222324 | 43 | 14 | 12 | 1 | 14 |
| 2024 | Noncampus | **Stored: sum of campuses** | **9** | **5** | **3** | **3** | **2** |
|  |  | 204796001 stored (from the 2022–24 file) | 9 | 5 | 3 | 3 | 2 |
|  |  | ↳ raw, Noncampus*222324 | 9 | 5 | 3 | 3 | 2 |
| 2024 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 204796001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | **Total on the page** (on campus + noncampus + public property) |  | **67** | **67** | **34** | **23** | **73** |
| 2023 | On campus | **Stored: sum of campuses** | **59** | **366** | **24** | **12** | **76** |
|  |  | 204796001 stored (from the 2022–24 file) | 59 | 366 | 24 | 12 | 76 |
|  |  | ↳ raw, Oncampus*222324 | 59 | 366 | 24 | 12 | 76 |
|  |  | ↳ raw, Oncampus*212223 | 59 | 366 | 24 | 12 | 76 |
| 2023 | On-campus student housing | **Stored: sum of campuses** | **34** | **10** | **15** | **1** | **22** |
|  |  | 204796001 stored (from the 2022–24 file) | 34 | 10 | 15 | 1 | 22 |
|  |  | ↳ raw, Residencehall*222324 | 34 | 10 | 15 | 1 | 22 |
|  |  | ↳ raw, Residencehall*212223 | 34 | 10 | 15 | 1 | 22 |
| 2023 | Noncampus | **Stored: sum of campuses** | **1** | **12** | **0** | **2** | **2** |
|  |  | 204796001 stored (from the 2022–24 file) | 1 | 12 | 0 | 2 | 2 |
|  |  | ↳ raw, Noncampus*222324 | 1 | 12 | 0 | 2 | 2 |
|  |  | ↳ raw, Noncampus*212223 | 1 | 12 | 0 | 2 | 2 |
| 2023 | Public property | **Stored: sum of campuses** | **0** | **1** | **1** | **1** | **1** |
|  |  | 204796001 stored (from the 2022–24 file) | 0 | 1 | 1 | 1 | 1 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 1 | 1 | 1 | 1 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 1 | 1 | 1 | 1 |
| 2023 | **Total on the page** (on campus + noncampus + public property) |  | **60** | **379** | **25** | **15** | **79** |
| 2022 | On campus | **Stored: sum of campuses** | **86** | **53** | **22** | **9** | **70** |
|  |  | 204796001 stored (from the 2022–24 file) | 86 | 53 | 22 | 9 | 70 |
|  |  | ↳ raw, Oncampus*222324 | 86 | 53 | 22 | 9 | 70 |
|  |  | ↳ raw, Oncampus*212223 | 86 | 53 | 22 | 9 | 70 |
|  |  | ↳ raw, Oncampus*202122 | 86 | 53 | 22 | 9 | 70 |
| 2022 | On-campus student housing | **Stored: sum of campuses** | **63** | **8** | **17** | **0** | **26** |
|  |  | 204796001 stored (from the 2022–24 file) | 63 | 8 | 17 | 0 | 26 |
|  |  | ↳ raw, Residencehall*222324 | 63 | 8 | 17 | 0 | 26 |
|  |  | ↳ raw, Residencehall*212223 | 63 | 8 | 17 | 0 | 26 |
|  |  | ↳ raw, Residencehall*202122 | 63 | 8 | 17 | 0 | 26 |
| 2022 | Noncampus | **Stored: sum of campuses** | **15** | **12** | **2** | **6** | **5** |
|  |  | 204796001 stored (from the 2022–24 file) | 15 | 12 | 2 | 6 | 5 |
|  |  | ↳ raw, Noncampus*222324 | 15 | 12 | 2 | 6 | 5 |
|  |  | ↳ raw, Noncampus*212223 | 15 | 12 | 2 | 6 | 5 |
|  |  | ↳ raw, Noncampus*202122 | 15 | 12 | 2 | 6 | 5 |
| 2022 | Public property | **Stored: sum of campuses** | **0** | **1** | **0** | **2** | **1** |
|  |  | 204796001 stored (from the 2022–24 file) | 0 | 1 | 0 | 2 | 1 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 1 | 0 | 2 | 1 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 1 | 0 | 2 | 1 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 1 | 0 | 2 | 1 |
| 2022 | **Total on the page** (on campus + noncampus + public property) |  | **101** | **66** | **24** | **17** | **76** |
| 2021 | On campus | **Stored: sum of campuses** | **128** | **547** | **34** | **14** | **68** |
|  |  | 204796001 stored (from the 2021–23 file) | 128 | 547 | 34 | 14 | 68 |
|  |  | ↳ raw, Oncampus*212223 | 128 | 547 | 34 | 14 | 68 |
|  |  | ↳ raw, Oncampus*202122 | 128 | 547 | 34 | 14 | 68 |
| 2021 | On-campus student housing | **Stored: sum of campuses** | **73** | **25** | **18** | **1** | **20** |
|  |  | 204796001 stored (from the 2021–23 file) | 73 | 25 | 18 | 1 | 20 |
|  |  | ↳ raw, Residencehall*212223 | 73 | 25 | 18 | 1 | 20 |
|  |  | ↳ raw, Residencehall*202122 | 73 | 25 | 18 | 1 | 20 |
| 2021 | Noncampus | **Stored: sum of campuses** | **6** | **4** | **0** | **3** | **3** |
|  |  | 204796001 stored (from the 2021–23 file) | 6 | 4 | 0 | 3 | 3 |
|  |  | ↳ raw, Noncampus*212223 | 6 | 4 | 0 | 3 | 3 |
|  |  | ↳ raw, Noncampus*202122 | 6 | 4 | 0 | 3 | 3 |
| 2021 | Public property | **Stored: sum of campuses** | **0** | **2** | **0** | **0** | **0** |
|  |  | 204796001 stored (from the 2021–23 file) | 0 | 2 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 2 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 2 | 0 | 0 | 0 |
| 2021 | **Total on the page** (on campus + noncampus + public property) |  | **134** | **553** | **34** | **17** | **71** |
| 2020 | On campus | **Stored: sum of campuses** | **179** | **530** | **37** | **12** | **57** |
|  |  | 204796001 stored (from the 2020–22 file) | 179 | 530 | 37 | 12 | 57 |
|  |  | ↳ raw, Oncampus*202122 | 179 | 530 | 37 | 12 | 57 |
| 2020 | On-campus student housing | **Stored: sum of campuses** | **100** | **16** | **15** | **3** | **15** |
|  |  | 204796001 stored (from the 2020–22 file) | 100 | 16 | 15 | 3 | 15 |
|  |  | ↳ raw, Residencehall*202122 | 100 | 16 | 15 | 3 | 15 |
| 2020 | Noncampus | **Stored: sum of campuses** | **11** | **30** | **3** | **5** | **3** |
|  |  | 204796001 stored (from the 2020–22 file) | 11 | 30 | 3 | 5 | 3 |
|  |  | ↳ raw, Noncampus*202122 | 11 | 30 | 3 | 5 | 3 |
| 2020 | Public property | **Stored: sum of campuses** | **2** | **2** | **2** | **1** | **0** |
|  |  | 204796001 stored (from the 2020–22 file) | 2 | 2 | 2 | 1 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 2 | 2 | 2 | 1 | 0 |
| 2020 | **Total on the page** (on campus + noncampus + public property) |  | **192** | **562** | **42** | **18** | **60** |

Student housing never exceeds on campus for this school.

## Swarthmore College (UNITID 216287)

Swarthmore, PA · Private nonprofit · 1,623 students (IPEDS 2024) · page: /schools/pa/swarthmore-college

Campus rows in the files (UNITID_P, BRANCH): 216287001 Main Campus.

| Year | Location | Row | Rape | Fondling | Dating violence | Domestic violence | Stalking |
|---|---|---|--:|--:|--:|--:|--:|
| 2024 | On campus | **Stored: sum of campuses** | **0** | **4** | **2** | **1** | **5** |
|  |  | 216287001 stored (from the 2022–24 file) | 0 | 4 | 2 | 1 | 5 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 4 | 2 | 1 | 5 |
| 2024 | On-campus student housing | **Stored: sum of campuses** | **0** | **1** | **1** | **0** | **1** |
|  |  | 216287001 stored (from the 2022–24 file) | 0 | 1 | 1 | 0 | 1 |
|  |  | ↳ raw, Residencehall*222324 | 0 | 1 | 1 | 0 | 1 |
| 2024 | Noncampus | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 216287001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
| 2024 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | **Total on the page** (on campus + noncampus + public property) |  | **0** | **4** | **2** | **1** | **5** |
| 2023 | On campus | **Stored: sum of campuses** | **9** | **4** | **8** | **2** | **6** |
|  |  | 216287001 stored (from the 2022–24 file) | 9 | 4 | 8 | 2 | 6 |
|  |  | ↳ raw, Oncampus*222324 | 9 | 4 | 8 | 2 | 6 |
|  |  | ↳ raw, Oncampus*212223 | 9 | 4 | 8 | 2 | 6 |
| 2023 | On-campus student housing | **Stored: sum of campuses** | **5** | **2** | **8** | **1** | **2** |
|  |  | 216287001 stored (from the 2022–24 file) | 5 | 2 | 8 | 1 | 2 |
|  |  | ↳ raw, Residencehall*222324 | 5 | 2 | 8 | 1 | 2 |
|  |  | ↳ raw, Residencehall*212223 | 5 | 2 | 8 | 1 | 2 |
| 2023 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | **Total on the page** (on campus + noncampus + public property) |  | **9** | **4** | **8** | **2** | **6** |
| 2022 | On campus | **Stored: sum of campuses** | **4** | **1** | **6** | **0** | **4** |
|  |  | 216287001 stored (from the 2022–24 file) | 4 | 1 | 6 | 0 | 4 |
|  |  | ↳ raw, Oncampus*222324 | 4 | 1 | 6 | 0 | 4 |
|  |  | ↳ raw, Oncampus*212223 | 4 | 1 | 6 | 0 | 4 |
|  |  | ↳ raw, Oncampus*202122 | 4 | 1 | 6 | 0 | 4 |
| 2022 | On-campus student housing | **Stored: sum of campuses** | **4** | **0** | **6** | **0** | **2** |
|  |  | 216287001 stored (from the 2022–24 file) | 4 | 0 | 6 | 0 | 2 |
|  |  | ↳ raw, Residencehall*222324 | 4 | 0 | 6 | 0 | 2 |
|  |  | ↳ raw, Residencehall*212223 | 4 | 0 | 6 | 0 | 2 |
|  |  | ↳ raw, Residencehall*202122 | 4 | 0 | 6 | 0 | 2 |
| 2022 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | **Total on the page** (on campus + noncampus + public property) |  | **4** | **1** | **6** | **0** | **4** |
| 2021 | On campus | **Stored: sum of campuses** | **2** | **2** | **1** | **0** | **0** |
|  |  | 216287001 stored (from the 2021–23 file) | 2 | 2 | 1 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 2 | 2 | 1 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 2 | 2 | 1 | 0 | 0 |
| 2021 | On-campus student housing | **Stored: sum of campuses** | **2** | **2** | **1** | **0** | **0** |
|  |  | 216287001 stored (from the 2021–23 file) | 2 | 2 | 1 | 0 | 0 |
|  |  | ↳ raw, Residencehall*212223 | 2 | 2 | 1 | 0 | 0 |
|  |  | ↳ raw, Residencehall*202122 | 2 | 2 | 1 | 0 | 0 |
| 2021 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | **Total on the page** (on campus + noncampus + public property) |  | **2** | **2** | **1** | **0** | **0** |
| 2020 | On campus | **Stored: sum of campuses** | **9** | **4** | **11** | **0** | **0** |
|  |  | 216287001 stored (from the 2020–22 file) | 9 | 4 | 11 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 9 | 4 | 11 | 0 | 0 |
| 2020 | On-campus student housing | **Stored: sum of campuses** | **9** | **2** | **9** | **0** | **0** |
|  |  | 216287001 stored (from the 2020–22 file) | 9 | 2 | 9 | 0 | 0 |
|  |  | ↳ raw, Residencehall*202122 | 9 | 2 | 9 | 0 | 0 |
| 2020 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 216287001 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | **Total on the page** (on campus + noncampus + public property) |  | **9** | **4** | **11** | **0** | **0** |

Student housing never exceeds on campus for this school.

## Mt San Antonio College (UNITID 119164)

Walnut, CA · Public · 29,971 students (IPEDS 2024) · page: /schools/ca/mt-san-antonio-college

Campus rows in the files (UNITID_P, BRANCH): 119164001 Main Campus.

| Year | Location | Row | Rape | Fondling | Dating violence | Domestic violence | Stalking |
|---|---|---|--:|--:|--:|--:|--:|
| 2024 | On campus | **Stored: sum of campuses** | **0** | **2** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2022–24 file) | 0 | 2 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 2 | 0 | 0 | 0 |
| 2024 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 119164001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
| 2024 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | **Total on the page** (on campus + noncampus + public property) |  | **0** | **2** | **0** | **0** | **0** |
| 2023 | On campus | **Stored: sum of campuses** | **2** | **4** | **0** | **0** | **1** |
|  |  | 119164001 stored (from the 2022–24 file) | 2 | 4 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 2 | 4 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 2 | 4 | 0 | 0 | 1 |
| 2023 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 119164001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
| 2023 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | **Total on the page** (on campus + noncampus + public property) |  | **2** | **4** | **0** | **0** | **1** |
| 2022 | On campus | **Stored: sum of campuses** | **3** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2022–24 file) | 3 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 3 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 3 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 3 | 0 | 0 | 0 | 0 |
| 2022 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 119164001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
| 2022 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | **Total on the page** (on campus + noncampus + public property) |  | **3** | **0** | **0** | **0** | **0** |
| 2021 | On campus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 119164001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
| 2021 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | **Total on the page** (on campus + noncampus + public property) |  | **0** | **0** | **0** | **0** | **0** |
| 2020 | On campus | **Stored: sum of campuses** | **0** | **0** | **0** | **1** | **2** |
|  |  | 119164001 stored (from the 2020–22 file) | 0 | 0 | 0 | 1 | 2 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 1 | 2 |
| 2020 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 119164001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
| 2020 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **1** | **0** |
|  |  | 119164001 stored (from the 2020–22 file) | 0 | 0 | 0 | 1 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 1 | 0 |
| 2020 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 119164001 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | **Total on the page** (on campus + noncampus + public property) |  | **0** | **0** | **0** | **2** | **2** |

Student housing never exceeds on campus for this school.

## Miami Dade College (UNITID 135717)

Miami, FL · Public · 58,941 students (IPEDS 2024) · page: /schools/fl/miami-dade-college

Campus rows in the files (UNITID_P, BRANCH): 135717001 Main-Wolfson; 135717002 North Campus; 135717003 Kendall Campus; 135717005 Medical Center Campus; 135717006 Homestead Campus; 135717007 Eduardo J. Padron Campus; 135717008 West Campus; 135717009 Hialeah Campus.

| Year | Location | Row | Rape | Fondling | Dating violence | Domestic violence | Stalking |
|---|---|---|--:|--:|--:|--:|--:|
| 2024 | On campus | **Stored: sum of campuses** | **0** | **0** | **3** | **0** | **8** |
|  |  | 135717001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 0 | 3 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 3 | 0 | 1 |
|  |  | 135717003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 2 |
|  |  | 135717005 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 2 |
|  |  | 135717006 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 1 |
|  |  | 135717007 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 1 |
|  |  | 135717008 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 1 |
| 2024 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | 135717003 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
| 2024 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717003 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
| 2024 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717005 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717006 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717008 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
| 2024 | **Total on the page** (on campus + noncampus + public property) |  | **0** | **0** | **3** | **0** | **8** |
| 2023 | On campus | **Stored: sum of campuses** | **0** | **4** | **0** | **1** | **6** |
|  |  | 135717001 stored (from the 2022–24 file) | 0 | 0 | 0 | 1 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 1 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 1 | 0 |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 1 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 1 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 1 | 0 | 0 | 1 |
|  |  | 135717003 stored (from the 2022–24 file) | 0 | 2 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 2 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 2 | 0 | 0 | 1 |
|  |  | 135717005 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717006 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 1 |
|  |  | 135717008 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 1 |
|  |  | 135717009 stored (from the 2022–24 file) | 0 | 1 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 1 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 1 | 0 | 0 | 2 |
| 2023 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | 135717003 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
| 2023 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **1** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 1 |
|  |  | 135717003 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
| 2023 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717005 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717006 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717008 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
| 2023 | **Total on the page** (on campus + noncampus + public property) |  | **0** | **4** | **0** | **1** | **7** |
| 2022 | On campus | **Stored: sum of campuses** | **0** | **3** | **0** | **0** | **3** |
|  |  | 135717001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 2 |
|  |  | 135717003 stored (from the 2022–24 file) | 0 | 1 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 1 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 1 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 1 | 0 | 0 | 0 |
|  |  | 135717005 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717006 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717008 stored (from the 2022–24 file) | 0 | 1 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 1 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 1 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 1 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2022–24 file) | 0 | 1 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*222324 | 0 | 1 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 1 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 1 | 0 | 0 | 1 |
| 2022 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717003 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
| 2022 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717003 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*222324 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
| 2022 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717003 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717005 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717006 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717008 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2022–24 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*222324 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2022 | **Total on the page** (on campus + noncampus + public property) |  | **0** | **3** | **0** | **0** | **3** |
| 2021 | On campus | **Stored: sum of campuses** | **0** | **1** | **0** | **0** | **4** |
|  |  | 135717001 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2021–23 file) | 0 | 1 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 1 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 1 | 0 | 0 | 2 |
|  |  | 135717003 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 1 |
|  |  | 135717005 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 1 |
|  |  | 135717006 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717008 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717003 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
| 2021 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717003 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*212223 | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
| 2021 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717003 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717005 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717006 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717008 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2021–23 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*212223 | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2021 | **Total on the page** (on campus + noncampus + public property) |  | **0** | **1** | **0** | **0** | **4** |
| 2020 | On campus | **Stored: sum of campuses** | **1** | **0** | **2** | **0** | **4** |
|  |  | 135717001 stored (from the 2020–22 file) | 1 | 0 | 0 | 0 | 1 |
|  |  | ↳ raw, Oncampus*202122 | 1 | 0 | 0 | 0 | 1 |
|  |  | 135717002 stored (from the 2020–22 file) | 0 | 0 | 1 | 0 | 1 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 1 | 0 | 1 |
|  |  | 135717003 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 2 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 2 |
|  |  | 135717005 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717006 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2020–22 file) | 0 | 0 | 1 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 1 | 0 | 0 |
|  |  | 135717008 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Oncampus*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | On-campus student housing | **Stored: sum of campuses** | **blank** | **blank** | **blank** | **blank** | **blank** |
|  |  | 135717001 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717002 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717003 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Residencehall*202122 | blank | blank | blank | blank | blank |
| 2020 | Noncampus | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717003 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717005 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717006 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717007 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Noncampus*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717008 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
|  |  | 135717009 stored (no figure in any file) | blank | blank | blank | blank | blank |
|  |  | ↳ raw, Noncampus*202122 | blank | blank | blank | blank | blank |
| 2020 | Public property | **Stored: sum of campuses** | **0** | **0** | **0** | **0** | **0** |
|  |  | 135717001 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717002 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717003 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717005 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717006 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717007 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717008 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
|  |  | 135717009 stored (from the 2020–22 file) | 0 | 0 | 0 | 0 | 0 |
|  |  | ↳ raw, Publicproperty*202122 | 0 | 0 | 0 | 0 | 0 |
| 2020 | **Total on the page** (on campus + noncampus + public property) |  | **1** | **0** | **2** | **0** | **4** |

Student housing never exceeds on campus for this school.

