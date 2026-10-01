# Text contrast, Phase 1.1

Measured in headless Chrome on 87 URLs (every sitemap URL, a search, the 404, sign-in and the admin pages), each in light and dark at 375px and 1280px, then again with every link and button forced into :hover. Colours are the rendered sRGB values after compositing translucent backgrounds and ancestor opacity. Ratios are WCAG 2.1. Large = 24px+, or 18.66px+ at weight 700+.

Result: 54 distinct pairs, none below its threshold (4.5:1 normal, 3:1 large). Lowest: light 5.00:1, dark 5.28:1.

Brand values: navy #0B4F7C, teal #14B8B0 (mark and shapes only, 2.47:1 on white), text teal #047873 (light) and #3ACCC4 (dark).

## Light mode

| Text | Background | Ratio | Size | State | Where |
|---|---|---:|---|---|---|
| #047873 | #F0F9FF | 5.00:1 | large (min 97.5px) | default, hover | h1.enough "Enough." |
| #047873 | #F0F9FF | 5.00:1 | normal (min 12px) | default, hover | a in .cluster "Alabama"; a in .footer-col "Find a school" |
| #047873 | #F8FDFF | 5.20:1 | normal (min 13px) | default | a in .help-bar "1-800-656-4673" |
| #8E5400 | #FFEBD0 | 5.28:1 | normal (min 12px) | default, hover | p in .alert "If you are in immediate danger, call"; strong in .alert "911" |
| #047873 | #FFFFFF | 5.33:1 | normal (min 13px) | default | a in .card-body "online.rainn.org"; a in .h5 "Get help now" |
| #FFFFFF | #B53437 | 5.98:1 | normal (min 13px) | default | button.btn.btn-danger "Delete"; button.btn.btn-danger.btn-sm "Delete state page" |
| #00662A | #D9F3DE | 6.08:1 | normal (min 12px) | default, hover | span.badge.badge-good "Published"; span.badge.badge-good "complete" |
| #4A606C | #F0F9FF | 6.19:1 | normal (min 12px) | default, hover | a in .cluster "Schools"; p.help "8 schools from federal IPEDS data, with " |
| #4A606C | #F8FDFF | 6.44:1 | normal (min 12px) | default, hover | th in .table "Reports per 1,000 students"; th.text-end "This school" |
| #4A606C | #FFFFFF | 6.60:1 | normal (min 9.5px) | default, hover | input.input::placeholder "e.g. State University"; p.text-sm.text-muted "Free, confidential support 24 hours a da" |
| #0B4F7C | #F0F9FF | 8.13:1 | large (min 26.3px) | default, hover | text.logo-wordmark "UNSILENCED" |
| #0B4F7C | #F0F9FF | 8.13:1 | normal (min 12px) | hover | a in .cluster "Alabama"; a in .prose "U.S. Department of Education, Campus Saf" |
| #FFFFFF | #922428 | 8.38:1 | normal (min 13px) | hover | button.btn.btn-danger "Delete"; button.btn.btn-danger.btn-sm "Delete state page" |
| #0B4F7C | #F8FDFF | 8.45:1 | normal (min 13px) | hover | a in .help-bar "1-800-656-4673" |
| #FFFFFF | #0B4F7C | 8.66:1 | normal (min 15px) | default, hover | a.skip-link "Skip to content"; button.btn.btn-primary "Search" |
| #0B4F7C | #FFFFFF | 8.66:1 | normal (min 13px) | hover | a in .card-body "online.rainn.org"; a in .h5 "Get help now" |
| #0B4F7C | #FFFFFF | 8.66:1 | large (min 26.3px) | default, hover | text.logo-wordmark "UNSILENCED" |
| #113F61 | #EEF8FF | 10.23:1 | normal (min 12px) | default, hover | p.alert-title "About the zero in 2022 and 2023"; p.alert-body "This school reported zero rapes for 2022" |
| #113F61 | #F8FDFF | 10.74:1 | normal (min 12px) | default, hover | code in .table "/resources/get-help"; code in .help "## Heading" |
| #FFFFFF | #003C63 | 11.50:1 | normal (min 15px) | hover | button.btn.btn-primary "Search"; a.btn.btn-primary "Home" |
| #0F1E26 | #F0F9FF | 15.96:1 | normal (min 12px) | default, hover | p.lede "Unsilenced tracks how U.S. colleges hand"; label.label "Find a school by name or city" |
| #0F1E26 | #F0F9FF | 15.96:1 | large (min 25.6px) | default, hover | h2 in .stack "How we get our data"; h1.h2 "How we get our data" |
| #0F1E26 | #F8FDFF | 16.60:1 | normal (min 13px) | default, hover | strong in .help-bar "Need help now?"; p in .help-bar "Call" |
| #0F1E26 | #FFFFFF | 17.02:1 | normal (min 10.4px) | default, hover | h2.h4 "Need help now?"; p in .card-body "The RAINN National Sexual Assault Hotlin" |
| #0F1E26 | #FFFFFF | 17.02:1 | large (min 25.6px) | default, hover | a.hotline-number "1-800-656-4673"; dd.stat-value "0" |
| #FFFFFF | #040F16 | 19.35:1 | normal (min 11px) | default | span in .quick-exit "Quick exit"; span.quick-exit-hint "or press Esc twice" |
| #FFFFFF | #00060B | 20.37:1 | normal (min 11px) | hover | span in .quick-exit "Quick exit"; span.quick-exit-hint "or press Esc twice" |

## Dark mode

| Text | Background | Ratio | Size | State | Where |
|---|---|---:|---|---|---|
| #8E5400 | #FFEBD0 | 5.28:1 | normal (min 12px) | default, hover | p in .alert "If you are in immediate danger, call"; strong in .alert "911" |
| #FFFFFF | #B53437 | 5.98:1 | normal (min 13px) | default | button.btn.btn-danger "Delete"; button.btn.btn-danger.btn-sm "Delete state page" |
| #00662A | #D9F3DE | 6.08:1 | normal (min 12px) | default, hover | span.badge.badge-good "Published"; span.badge.badge-good "complete" |
| #8EA5B1 | #0F1E26 | 6.62:1 | normal (min 12px) | default, hover | th in .table "Reports per 1,000 students"; th.text-end "This school" |
| #8EA5B1 | #040F16 | 7.53:1 | normal (min 9.5px) | default, hover | input.input::placeholder "e.g. State University"; p.text-sm.text-muted "Free, confidential support 24 hours a da" |
| #8EA5B1 | #00060B | 7.92:1 | normal (min 12px) | default, hover | a in .cluster "Schools"; p.help "8 schools from federal IPEDS data, with " |
| #FFFFFF | #922428 | 8.38:1 | normal (min 13px) | hover | button.btn.btn-danger "Delete"; button.btn.btn-danger.btn-sm "Delete state page" |
| #3ACCC4 | #0F1E26 | 8.60:1 | normal (min 13px) | default | a in .help-bar "1-800-656-4673" |
| #FFFFFF | #0B4F7C | 8.66:1 | normal (min 15px) | default, hover | a.skip-link "Skip to content"; button.btn.btn-primary "Search" |
| #3ACCC4 | #040F16 | 9.78:1 | normal (min 13px) | default | a in .card-body "online.rainn.org"; a in .h5 "Get help now" |
| #3ACCC4 | #00060B | 10.30:1 | large (min 97.5px) | default, hover | h1.enough "Enough." |
| #3ACCC4 | #00060B | 10.30:1 | normal (min 12px) | default, hover | a in .cluster "Alabama"; a in .footer-col "Find a school" |
| #C0DDF7 | #0C263A | 11.02:1 | normal (min 12px) | default, hover | p.alert-title "About the zero in 2022 and 2023"; p.alert-body "This school reported zero rapes for 2022" |
| #FFFFFF | #003C63 | 11.50:1 | normal (min 15px) | hover | button.btn.btn-primary "Search"; a.btn.btn-primary "Home" |
| #C0DDF7 | #0F1E26 | 12.10:1 | normal (min 12px) | default, hover | code in .table "/resources/get-help"; code in .help "## Heading" |
| #E5F2F9 | #0F1E26 | 14.91:1 | normal (min 13px) | default, hover | strong in .help-bar "Need help now?"; p in .help-bar "Call" |
| #F0F9FF | #0F1E26 | 15.96:1 | normal (min 13px) | hover | a in .help-bar "1-800-656-4673" |
| #E5F2F9 | #040F16 | 16.96:1 | normal (min 10.4px) | default, hover | h2.h4 "Need help now?"; p in .card-body "The RAINN National Sexual Assault Hotlin" |
| #E5F2F9 | #040F16 | 16.96:1 | large (min 25.6px) | default, hover | a.hotline-number "1-800-656-4673"; dd.stat-value "0" |
| #E5F2F9 | #00060B | 17.85:1 | normal (min 12px) | default, hover | p.lede "Unsilenced tracks how U.S. colleges hand"; label.label "Find a school by name or city" |
| #E5F2F9 | #00060B | 17.85:1 | large (min 25.6px) | default, hover | h2 in .stack "How we get our data"; h1.h2 "How we get our data" |
| #F0F9FF | #040F16 | 18.15:1 | normal (min 13px) | hover | a in .card-body "online.rainn.org"; a in .h5 "Get help now" |
| #F0F9FF | #040F16 | 18.15:1 | large (min 26.3px) | default, hover | text.logo-wordmark "UNSILENCED" |
| #00060B | #F0F9FF | 19.11:1 | normal (min 11px) | default | span in .quick-exit "Quick exit"; span.quick-exit-hint "or press Esc twice" |
| #F0F9FF | #00060B | 19.11:1 | large (min 26.3px) | default, hover | text.logo-wordmark "UNSILENCED" |
| #F0F9FF | #00060B | 19.11:1 | normal (min 12px) | hover | a in .cluster "Alabama"; a in .prose "U.S. Department of Education, Campus Saf" |
| #00060B | #FFFFFF | 20.37:1 | normal (min 11px) | hover | span in .quick-exit "Quick exit"; span.quick-exit-hint "or press Esc twice" |

## Error and warning states (both modes)

Reached by submitting the admin forms with bad input (each rejected with 422, nothing saved): an invalid school, an invalid accountability record, and a summary the name check flags.

| Mode | Text | Background | Ratio | Where |
|---|---|---|---:|---|
| dark | #8E5400 | #FFEBD0 | 5.28:1 | p.alert-title "This summary may contain a person's name" |
| dark | #922428 | #FFE3E0 | 6.91:1 | p in .alert "Fix the fields marked below." |
| dark | #8EA5B1 | #040F16 | 7.53:1 | p.help "The last part of /schools//… Left blank," |
| dark | #8EA5B1 | #00060B | 7.92:1 | a in .cluster "Overview" |
| dark | #F98E88 | #040F16 | 8.53:1 | p.error "Enter the IPEDS UNITID, a whole number." |
| dark | #FFFFFF | #0B4F7C | 8.66:1 | a.skip-link "Skip to content" |
| dark | #C0DDF7 | #0C263A | 11.02:1 | span.badge.badge-brand "Admin" |
| dark | #E5F2F9 | #040F16 | 16.96:1 | button.btn.btn-sm "Sign out" |
| dark | #E5F2F9 | #00060B | 17.85:1 | a.btn.btn-ghost.btn-sm "View site" |
| dark | #E5F2F9 | #00060B | 17.85:1 | h1.h2 "New school" |
| dark | #00060B | #F0F9FF | 19.11:1 | span in .quick-exit "Quick exit" |
| dark | #F0F9FF | #00060B | 19.11:1 | text.logo-wordmark "UNSILENCED" |
| light | #8E5400 | #FFEBD0 | 5.28:1 | p.alert-title "This summary may contain a person's name" |
| light | #4A606C | #F0F9FF | 6.19:1 | a in .cluster "Overview" |
| light | #4A606C | #FFFFFF | 6.60:1 | p.help "The last part of /schools//… Left blank," |
| light | #922428 | #FFE3E0 | 6.91:1 | p in .alert "Fix the fields marked below." |
| light | #0B4F7C | #F0F9FF | 8.13:1 | text.logo-wordmark "UNSILENCED" |
| light | #922428 | #FFFFFF | 8.38:1 | p.error "Enter the IPEDS UNITID, a whole number." |
| light | #FFFFFF | #0B4F7C | 8.66:1 | a.skip-link "Skip to content" |
| light | #113F61 | #EEF8FF | 10.23:1 | span.badge.badge-brand "Admin" |
| light | #0F1E26 | #F0F9FF | 15.96:1 | a.btn.btn-ghost.btn-sm "View site" |
| light | #0F1E26 | #F0F9FF | 15.96:1 | h1.h2 "New school" |
| light | #0F1E26 | #FFFFFF | 17.02:1 | button.btn.btn-sm "Sign out" |
| light | #FFFFFF | #040F16 | 19.35:1 | span in .quick-exit "Quick exit" |

Not measured: :focus and :active states, and a failed import page (no failed run exists; it uses the same .alert-bad as the form errors above). Focus outlines use --focus: navy #0B4F7C in light (8.66:1 on white), #3ACCC4 in dark.
