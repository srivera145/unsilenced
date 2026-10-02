<?php

/**
 * Unsilenced configuration.
 *
 * Read through Keel\App\Support\Config::get('dot.path'). Nothing in here is a
 * secret; credentials stay in .env.
 *
 * The column maps below were checked on 2026-10-01 against the real files:
 * IPEDS hd2025.csv and drvef2024.csv, and the Campus Safety and Security
 * files for 2020-2024 (Oncampuscrime202122.csv through
 * Publicpropertyvawa222324.csv). The fixtures in tests/fixtures/ copy those
 * files' headers. When a new year's files arrive, print their headers:
 *
 *   php database/console.php import:schools storage/imports/hd2025.csv --headers
 *   php database/console.php import:clery storage/imports/Oncampuscrime222324.csv --headers
 *
 * The importers match header names exactly (ignoring case, surrounding spaces
 * and a UTF-8 BOM) and refuse to run when a required column is missing, so a
 * renamed column fails loudly instead of importing blanks.
 */
return [
    'site' => [
        'name' => 'Unsilenced',
        'tagline' => 'Enough.',
        // Placeholder until the production domain is live. APP_URL in .env
        // decides every absolute URL the app prints; this is only the
        // human-readable name used in copy.
        'domain' => 'unsilenced.org',
        'description' => 'Unsilenced tracks how U.S. colleges handle sexual assault, using public federal data: Clery Act crime statistics, enrollment, and public accountability records.',
    ],

    // Shown on every public page. Never edited through the admin panel, so a
    // bad edit can never remove the hotline.
    'help' => [
        'hotline_name' => 'RAINN National Sexual Assault Hotline',
        'hotline_display' => '1-800-656-4673',
        'hotline_tel' => '+18006564673',
        'chat_url' => 'https://online.rainn.org',
        'chat_label' => 'online.rainn.org',
        'emergency_number' => '911',
    ],

    // The link-preview image (Open Graph and Twitter cards) on every public
    // page, served from this site at APP_URL + path. Rendered from the logo by
    // scripts/share-card/render.sh; re-render it if the logo changes.
    'share_card' => [
        'path' => '/share-card.png',
        'width' => 1200,
        'height' => 630,
        'alt' => 'The Unsilenced logo, a megaphone, and the word Enough.',
    ],

    // Where the quick-exit button sends the visitor. location.replace() is used
    // so the page they left is not in Back history.
    'quick_exit_url' => 'https://weather.com/',

    // When true, links and searches between public pages also use
    // location.replace(), so a whole visit is ONE Back-history entry and quick
    // exit removes all of it from Back. The cost: the browser's Back button on
    // this site leaves the site instead of returning to the previous page here.
    // With false, quick exit only removes the page it was pressed on.
    'single_history_entry' => true,

    // A school at or above this enrollment that reports zero rapes in a year
    // gets the neutral underreporting note on its page.
    'context_note_min_enrollment' => 5000,

    // A state or size group with fewer schools than this (counting the school
    // itself) is not shown as a comparison: one or two schools is not a group.
    'comparison_min_schools' => 3,

    // Peer groups for the "schools of similar size" comparison. Bands are
    // inclusive; max null means no upper bound. Keep them contiguous.
    'size_bands' => [
        ['min' => 0, 'max' => 999, 'label' => 'fewer than 1,000 students'],
        ['min' => 1000, 'max' => 4999, 'label' => '1,000 to 4,999 students'],
        ['min' => 5000, 'max' => 14999, 'label' => '5,000 to 14,999 students'],
        ['min' => 15000, 'max' => 29999, 'label' => '15,000 to 29,999 students'],
        ['min' => 30000, 'max' => null, 'label' => '30,000 or more students'],
    ],

    'sources' => [
        'clery' => [
            'name' => 'U.S. Department of Education, Campus Safety and Security Survey (Clery Act data)',
            'short' => 'Clery Act data, U.S. Dept. of Education',
            'url' => 'https://ope.ed.gov/campussafety/',
        ],
        'ipeds' => [
            'name' => 'National Center for Education Statistics, IPEDS',
            'short' => 'IPEDS, NCES',
            'url' => 'https://nces.ed.gov/ipeds/',
        ],
    ],

    // --- IPEDS institutions (import:schools) -------------------------------
    'ipeds' => [
        // field => header name in the file
        'columns' => [
            'unitid' => 'UNITID',
            'name' => 'INSTNM',
            'city' => 'CITY',
            'state' => 'STABBR',
            'control' => 'CONTROL',
            'enrollment' => 'ENRTOT',
        ],

        // A file must contain every column of at least one profile. The
        // directory file (hdyyyy.csv) carries name, place and control; total
        // enrollment lives in a separate file (drvefyyyy.csv), so the two are
        // imported separately and both key on UNITID. A combined custom
        // export that has everything satisfies both profiles at once.
        'profiles' => [
            'directory' => ['unitid', 'name', 'city', 'state', 'control'],
            'enrollment' => ['unitid', 'enrollment'],
        ],

        // IPEDS CONTROL codes. Anything else (e.g. -3, not available) is stored
        // as unknown.
        'control_values' => [
            '1' => 'public',
            '2' => 'private_nonprofit',
            '3' => 'private_for_profit',
        ],
    ],

    // --- Clery / Campus Safety and Security (import:clery) ------------------
    'clery' => [
        // Per-campus id. In the published files this is the 6-digit IPEDS
        // UNITID followed by a 3-digit campus number; the first unitid_digits
        // digits are the institution. Campus rows are summed per institution.
        'unitid_column' => 'UNITID_P',
        'unitid_digits' => 6,

        // Offense => header pattern. {yy} is the two-digit data year, {yyyy}
        // the four-digit one. Each published file covers three calendar
        // years (Oncampuscrime222324.csv has RAPE22, RAPE23 and RAPE24), and
        // the importer reads every year it finds. A file does not need every
        // offense: the crime files carry the sex offenses and the VAWA files
        // carry dating violence, domestic violence and stalking. Each import
        // writes only the offenses its file contains, so the two files for a
        // location fill the same rows.
        'offense_columns' => [
            'rape' => 'RAPE{yy}',
            'fondling' => 'FONDL{yy}',
            'incest' => 'INCES{yy}',
            'statutory_rape' => 'STATR{yy}',
            'dating_violence' => 'DATING{yy}',
            'domestic_violence' => 'DOMEST{yy}',
            'stalking' => 'STALK{yy}',
        ],

        // Where the location type comes from, in order:
        //   1. --location=... on the command line
        //   2. location_column, if set and present in the file
        //   3. the first filename pattern that matches the file's basename
        'location_column' => null,
        'location_values' => [
            // value in location_column => location key
        ],
        // Only the crime and VAWA files match. The same download has hate-crime
        // files (Oncampushate222324.csv) whose RAPE22 column counts hate
        // crimes only, plus arrest, discipline, fire, unfounded and "Reported"
        // files; none of them may be imported as these counts.
        'location_filename_patterns' => [
            '/^oncampus(crime|vawa)\d/i' => 'on_campus',
            '/^residencehall(crime|vawa)\d/i' => 'on_campus_housing',
            '/^noncampus(crime|vawa)\d/i' => 'noncampus',
            '/^publicproperty(crime|vawa)\d/i' => 'public_property',
        ],
    ],

    // Display labels. Keys are the stored values.
    'locations' => [
        'on_campus' => 'On campus',
        'on_campus_housing' => 'On-campus student housing',
        'noncampus' => 'Noncampus',
        'public_property' => 'Public property',
    ],

    'offenses' => [
        'rape' => 'Rape',
        'fondling' => 'Fondling',
        'incest' => 'Incest',
        'statutory_rape' => 'Statutory rape',
        'dating_violence' => 'Dating violence',
        'domestic_violence' => 'Domestic violence',
        'stalking' => 'Stalking',
    ],

    // The two groups the rate comparison reports. Clery files report the first
    // group as "sex offenses" and the second under the VAWA amendments.
    'offense_groups' => [
        'sex_offenses' => [
            'label' => 'Sex offenses (rape, fondling, incest, statutory rape)',
            'short' => 'Sex offenses',
            'offenses' => ['rape', 'fondling', 'incest', 'statutory_rape'],
        ],
        'vawa_offenses' => [
            'label' => 'Dating violence, domestic violence and stalking',
            'short' => 'Dating violence, domestic violence and stalking',
            'offenses' => ['dating_violence', 'domestic_violence', 'stalking'],
        ],
    ],

    'controls' => [
        'public' => 'Public',
        'private_nonprofit' => 'Private nonprofit',
        'private_for_profit' => 'Private for-profit',
    ],

    // --- Accountability record ---------------------------------------------
    'accountability' => [
        'types' => [
            'ocr_investigation' => 'Federal Title IX investigation (OCR)',
            'lawsuit' => 'Lawsuit',
            'state_review' => 'State review',
            'news_coverage' => 'News coverage',
            'other' => 'Other public record',
        ],
        'statuses' => [
            'open' => 'Open',
            'resolved' => 'Resolved',
            'settled' => 'Settled',
            'dismissed' => 'Dismissed',
            'closed' => 'Closed',
            'published' => 'Published',
        ],
        'summary_max_length' => 1000,
    ],

    // --- Survivor reports (Phase 2, docs/SURVIVOR-REPORTS.md) ---------------
    // Off until legal review: SUBMISSIONS_ENABLED=true in .env turns on
    // /submit, /my-report and /share and the school-page section. The keys
    // below are stored values; never rename one that reports already use.
    'survivor_reports' => [
        // A school's aggregate figures appear once it has this many approved
        // reports that may be counted (consent a or b).
        'stats_min_reports' => 3,
        // The national total on the home page appears from this many.
        'homepage_min_reports' => 25,
        'account_max_length' => 5000,
        // Rejected reports are deleted this long after rejection
        // (php database/console.php survivor:purge-rejected, daily).
        'rejected_retention_days' => 30,
        // /submit, /my-report and /share: the session ends after this long
        // without a request.
        'session_idle_minutes' => 30,
        // Viewing evidence needs an emailed code entered this recently.
        'admin_otp_fresh_minutes' => 15,
        // Every submission, from everyone: no IP is recorded, so the limit is
        // global. A real report that hits it keeps its answers and is asked
        // to wait a minute.
        'submissions_per_minute' => 10,
        // Proof of work on the final submit: leading zero bits of
        // SHA-256(challenge:nonce). 18 bits is about 262,000 hashes, a few
        // seconds on a phone.
        'proof_of_work_bits' => 18,
        'case_key_words' => 6,
        'share_link_expiry' => [
            '24h' => ['seconds' => 86400, 'label' => '24 hours'],
            '7d' => ['seconds' => 604800, 'label' => '7 days'],
            '30d' => ['seconds' => 2592000, 'label' => '30 days'],
        ],
        // Wrong passcodes before a share link stops working.
        'share_passcode_max_attempts' => 10,
        'share_links_max_per_case' => 25,

        'seasons' => [
            'spring' => 'Spring',
            'summer' => 'Summer',
            'fall' => 'Fall',
            'winter' => 'Winter',
        ],
        'settings' => [
            'residence_hall' => 'Residence hall',
            'greek_housing' => 'Fraternity or sorority housing',
            'off_campus_housing' => 'Off-campus housing',
            'athletics' => 'Athletics',
            'campus_building' => 'Another campus building',
            'online' => 'Online',
            'other' => 'Somewhere else',
        ],
        'perpetrators' => [
            'fellow_student' => 'A fellow student',
            'student_employee' => 'A student employee, such as an RA or TA',
            'staff_faculty' => 'A staff or faculty member',
            'coach' => 'A coach',
            'stranger' => 'Someone I did not know',
            'other' => 'Someone else',
            'prefer_not' => 'I would rather not say',
        ],
        // How a published account shows the category: short and neutral.
        'perpetrator_public' => [
            'fellow_student' => 'Fellow student',
            'student_employee' => 'Student employee',
            'staff_faculty' => 'Staff or faculty',
            'coach' => 'Coach',
            'stranger' => 'Stranger',
            'other' => 'Other',
            'prefer_not' => 'Not given',
        ],
        'school_channels' => [
            'title_ix' => 'The Title IX office',
            'campus_police' => 'Campus police or security',
            'other_office' => 'Another office, such as a dean or residence life',
        ],
        'police' => [
            'yes' => 'Yes',
            'no' => 'No',
            'prefer_not' => 'I would rather not say',
        ],
        'not_reported_reasons' => [
            'not_believed' => 'I was afraid I would not be believed',
            'retaliation' => 'I was afraid of retaliation',
            'school_discouraged' => 'Someone at the school discouraged me',
            'didnt_know_how' => 'I did not know how',
            'other' => 'Another reason',
        ],
        'school_outcomes' => [
            'investigation_opened' => 'They opened an investigation',
            'no_response' => 'They did not respond',
            'discouraged' => 'They discouraged me from going forward',
            'interim_measures' => 'They gave me support measures, such as a housing or class change or a no-contact order',
            'sanctions_issued' => 'They issued sanctions',
            'pressured_quiet' => 'They pressured me to stay quiet',
            'other' => 'Something else',
        ],
        'ratings' => [
            1 => 'Very poorly',
            2 => 'Poorly',
            3 => 'Neither well nor poorly',
            4 => 'Well',
            5 => 'Very well',
        ],
        'consents' => [
            'stats' => [
                'label' => 'Count my report in the statistics only',
                'help' => 'Your answers are added to this school\'s totals once there are enough reports. Your own words are never published.',
            ],
            'stats_and_account' => [
                'label' => 'Count my report, and publish my account',
                'help' => 'An admin edits your account to remove anything that could identify you or anyone else before it is published. Your original stays private.',
            ],
            'private' => [
                'label' => 'Keep it private',
                'help' => 'Nothing is published or counted, and no one at Unsilenced reads it. It is kept for your own record and for sharing with people you choose.',
            ],
        ],
        // Shown to the survivor on /my-report.
        'statuses' => [
            'private' => 'Kept private',
            'submitted' => 'Waiting for review',
            'in_review' => 'Being reviewed',
            'changes_requested' => 'Changes requested',
            'approved' => 'Published',
            'rejected' => 'Not published',
        ],
        // The only bracketed text an admin may put into a published account.
        // Anything else bracketed, and any word not in their original, is refused.
        'redaction_placeholders' => [
            '[name removed]',
            '[place removed]',
            '[detail removed]',
            '[date removed]',
            '[contact details removed]',
            '[organization removed]',
            '[a student]',
            '[a student employee]',
            '[a staff member]',
            '[an instructor]',
            '[role removed]',
            '[a residence hall]',
            '[a fraternity or sorority]',
            '[a team]',
            '[a club or organization]',
        ],
    ],

    // Evidence files. Types are decided by the file's first bytes, never its
    // name or the browser's claim. No video, ever: an MP4 with a video track
    // is refused even when it is named .m4a.
    'evidence' => [
        'max_file_bytes' => 20 * 1024 * 1024,
        'max_files' => 20,
        // When a case is withdrawn or purged, keep (encrypted, detached from
        // the case) any file an admin quarantined as illegal content, because
        // the law may require it to be preserved. FOR THE LAWYER TO CONFIRM:
        // docs/ILLEGAL-CONTENT.md. false deletes it with everything else.
        'preserve_quarantined' => true,
        'types' => [
            'jpeg' => ['label' => 'Photo (JPEG)', 'mime' => 'image/jpeg', 'extension' => 'jpg'],
            'png' => ['label' => 'Image (PNG)', 'mime' => 'image/png', 'extension' => 'png'],
            'heic' => ['label' => 'Photo (HEIC)', 'mime' => 'image/heic', 'extension' => 'heic'],
            'pdf' => ['label' => 'PDF', 'mime' => 'application/pdf', 'extension' => 'pdf'],
            'text' => ['label' => 'Text', 'mime' => 'text/plain', 'extension' => 'txt'],
            'm4a' => ['label' => 'Audio (M4A)', 'mime' => 'audio/mp4', 'extension' => 'm4a'],
            'mp3' => ['label' => 'Audio (MP3)', 'mime' => 'audio/mpeg', 'extension' => 'mp3'],
        ],
    ],

    // Words that never count toward "looks like a person's name" in an
    // accountability summary. Matching is case-insensitive. Adding a word here
    // makes the check miss names containing it, so prefer leaving a false
    // positive for the admin to confirm.
    'name_detector' => [
        'ignore_words' => [
            // function words that start sentences
            'a', 'an', 'the', 'in', 'on', 'at', 'for', 'of', 'and', 'or', 'but', 'by', 'to', 'from',
            'after', 'before', 'during', 'since', 'until', 'this', 'that', 'these', 'those', 'it', 'its',
            'his', 'her', 'their', 'our', 'we', 'they', 'he', 'she', 'when', 'while', 'according',
            'following', 'under', 'over', 'as', 'with', 'without', 'about', 'between', 'into', 'per',
            // institutions, law, government, media
            'university', 'college', 'institute', 'school', 'academy', 'campus', 'department', 'office',
            'title', 'ix', 'civil', 'rights', 'education', 'federal', 'state', 'district', 'court',
            'county', 'city', 'board', 'trustees', 'regents', 'association', 'council', 'committee',
            'commission', 'agency', 'administration', 'center', 'police', 'public', 'safety', 'act',
            'clery', 'violence', 'against', 'women', 'fraternity', 'sorority', 'chapter', 'national',
            'american', 'united', 'states', 'justice', 'attorney', 'general', 'governor', 'legislature',
            'senate', 'house', 'assembly', 'supreme', 'appeals', 'circuit', 'superior', 'u.s.', 'us',
            'daily', 'times', 'news', 'post', 'herald', 'sun', 'journal', 'press', 'tribune', 'gazette',
            'review', 'report', 'magazine', 'radio', 'television', 'associated', 'inc', 'llc',
            'student', 'students', 'dean', 'president', 'provost', 'chancellor', 'coordinator',
            'investigation', 'complaint', 'lawsuit', 'settlement', 'resolution', 'agreement',
            // months and days
            'january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september',
            'october', 'november', 'december', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday',
            'saturday', 'sunday',
            // Greek letters (fraternity and sorority names)
            'alpha', 'beta', 'gamma', 'delta', 'epsilon', 'zeta', 'eta', 'theta', 'iota', 'kappa',
            'lambda', 'mu', 'nu', 'xi', 'omicron', 'pi', 'rho', 'sigma', 'tau', 'upsilon', 'phi',
            'chi', 'psi', 'omega',
        ],
    ],

    // Words a browser-tab title may never contain. Titles show in tabs, history,
    // bookmarks and shared screens, so they stay neutral even where the page's
    // own heading is descriptive. Admin saves are rejected if a resource page's
    // tab title contains one, and PublicPagesFeatureTest checks every page.
    'neutral_title_blocklist' => [
        'rape', 'sexual', 'assault', 'abuse', 'violence', 'victim', 'survivor', 'stalking',
        'evidence', 'fondling', 'incest', 'title ix', 'crime', 'police', 'report',
    ],

    // Two-letter codes the importer accepts. The 50 states and DC have state
    // pages; territories get school pages but no state page.
    'states' => [
        'AL' => 'Alabama', 'AK' => 'Alaska', 'AZ' => 'Arizona', 'AR' => 'Arkansas',
        'CA' => 'California', 'CO' => 'Colorado', 'CT' => 'Connecticut', 'DE' => 'Delaware',
        'DC' => 'District of Columbia', 'FL' => 'Florida', 'GA' => 'Georgia', 'HI' => 'Hawaii',
        'ID' => 'Idaho', 'IL' => 'Illinois', 'IN' => 'Indiana', 'IA' => 'Iowa',
        'KS' => 'Kansas', 'KY' => 'Kentucky', 'LA' => 'Louisiana', 'ME' => 'Maine',
        'MD' => 'Maryland', 'MA' => 'Massachusetts', 'MI' => 'Michigan', 'MN' => 'Minnesota',
        'MS' => 'Mississippi', 'MO' => 'Missouri', 'MT' => 'Montana', 'NE' => 'Nebraska',
        'NV' => 'Nevada', 'NH' => 'New Hampshire', 'NJ' => 'New Jersey', 'NM' => 'New Mexico',
        'NY' => 'New York', 'NC' => 'North Carolina', 'ND' => 'North Dakota', 'OH' => 'Ohio',
        'OK' => 'Oklahoma', 'OR' => 'Oregon', 'PA' => 'Pennsylvania', 'RI' => 'Rhode Island',
        'SC' => 'South Carolina', 'SD' => 'South Dakota', 'TN' => 'Tennessee', 'TX' => 'Texas',
        'UT' => 'Utah', 'VT' => 'Vermont', 'VA' => 'Virginia', 'WA' => 'Washington',
        'WV' => 'West Virginia', 'WI' => 'Wisconsin', 'WY' => 'Wyoming',
    ],
    'territories' => [
        'AS' => 'American Samoa', 'GU' => 'Guam', 'MP' => 'Northern Mariana Islands',
        'PR' => 'Puerto Rico', 'VI' => 'U.S. Virgin Islands', 'FM' => 'Federated States of Micronesia',
        'MH' => 'Marshall Islands', 'PW' => 'Palau',
    ],
];
