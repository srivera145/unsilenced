-- Resource guide pages. Admin-editable; the body is the Markdown subset in
-- src/App/Support/Markdown.php. The hotline panel on these pages comes from
-- config/unsilenced.php, not from these rows, so no edit can remove it.
-- browser_title, when set, replaces title in the <title> tag, so the heading can
-- say "Save your evidence" while the tab and history say "Keeping records".
CREATE TABLE IF NOT EXISTS resource_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) NOT NULL,
    title VARCHAR(160) NOT NULL,
    browser_title VARCHAR(160) NULL,
    summary VARCHAR(300) NOT NULL DEFAULT '',
    body MEDIUMTEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    updated_by INT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_slug (slug),
    CONSTRAINT fk_resource_pages_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO resource_pages (slug, title, browser_title, summary, body, sort_order, is_published) VALUES
('get-help', 'Get help now', NULL, 'Free, confidential support 24 hours a day: call the RAINN National Sexual Assault Hotline at 1-800-656-4673 or chat at online.rainn.org. In immediate danger, call 911.', 'If you are in immediate danger, call **911**.

## Talk to someone now

The RAINN National Sexual Assault Hotline is free, confidential and open 24 hours a day. Call [1-800-656-4673](tel:+18006564673) or chat online at [online.rainn.org](https://online.rainn.org). RAINN can connect you with a sexual assault service provider near you.

You do not have to decide anything right now. Calling a hotline does not mean you have to report to the police or to your school.

## When you are ready

- [Your options](/resources/your-options): medical care, reporting, and confidential support, in plain language.
- [Save your evidence](/resources/save-evidence): how to keep texts, messages and records safe.
- [Your state](/states): time limits and local resources, as we add them.

## Browse more safely

- Press **Esc** twice, or use the **Quick exit** button, to leave this site at once. It loads weather.com in this tab.
- Quick exit cannot erase your browser history. If someone else uses or checks this device, use a private or incognito window, or clear your history afterward.
- Our public pages set no cookies and load no trackers, analytics or outside scripts.', 10, 1),
('your-options', 'Your options', NULL, 'Plain-language options after a sexual assault: medical care and forensic exams, reporting to police, Title IX complaints, civil attorneys and confidential advocates.', 'There is no right order, and you can choose some, all or none of these. A confidential advocate can go through them with you: call the RAINN hotline at [1-800-656-4673](tel:+18006564673) to be connected with one.

## Medical care and a forensic exam

You can get medical care whether or not you want to report. A hospital can check for injuries, offer medicine to prevent pregnancy and sexually transmitted infections, and, if you want, collect evidence in a sexual assault forensic exam (sometimes called a "rape kit").

- Evidence is best collected as soon as possible. Many hospitals collect it up to about five days afterward. Ask the hospital or an advocate what applies where you are.
- You can usually have a forensic exam without reporting to the police, and you should not be charged for the exam itself. Other medical care may be billed; an advocate can help with costs.
- You can bring an advocate with you, and you can stop or skip any part of the exam.

## Reporting to the police

You can report to local police, or to campus police if your school has them. You can report right away or later, but time limits (statutes of limitations) vary by state and by crime. Check [your state''s page](/states) as we add details, or ask an advocate or attorney.

You can ask to bring an advocate. A police report starts a criminal process that is separate from anything your school does.

## Filing a Title IX complaint

Title IX is the federal law against sex discrimination in education, including sexual harassment and assault. You can contact your school''s Title IX coordinator to report what happened. Even without a formal complaint, schools can offer supportive measures such as changes to classes, housing or work schedules.

If you believe your school did not respond properly, you can also file a complaint with the U.S. Department of Education''s Office for Civil Rights (OCR) using [OCR''s online complaint form](https://ocrcas.ed.gov). OCR complaints generally must be filed within 180 days of the last act you are complaining about, though OCR can extend that in some cases.

The federal rules for how schools handle these cases have changed several times in recent years. An advocate or attorney can explain what applies to you now.

## Talking to a civil attorney

A civil lawsuit is separate from a criminal case. Depending on the facts and your state, you may be able to sue the person who harmed you, and in some cases a school or other institution. Deadlines vary by state and can be short. Some attorneys who handle these cases offer a free first consultation.

## Confidential advocates

Advocates help you understand your options, can go with you to the hospital, the police or school meetings, and support you whatever you decide.

- **Rape crisis centers** offer free, confidential advocacy. The RAINN hotline can connect you with one near you.
- **On campus**, counseling and health center staff, and some campus advocates, are usually confidential: they do not have to pass on what you tell them to the Title IX office. If you are not sure whether someone is confidential, ask before you share details.', 20, 1),
('save-evidence', 'Save your evidence', 'Keeping records', 'How to screenshot and back up texts, export chats, keep clothing and request records, so you have them if you decide to report or talk to an attorney.', 'Evidence can help if you decide to report, file a complaint or talk to an attorney, now or later. Saving it does not commit you to anything.

**Never upload intimate images anywhere**, including to this site, social media or cloud storage someone else can see. If there are intimate images, give them only to the police or to an attorney.

## Texts and messages

- Take screenshots that show the message, the date and time, and the sender''s name or number.
- Let each screenshot overlap the one before, so nothing is missing between them.
- Many messaging apps have an export, download or backup option in their settings. Use it to save whole conversations.
- Do not delete the conversation until you have saved what you need. If you plan to block the person, save the conversation first.
- Email copies to yourself, or save them to an account only you can reach.

## Social media and email

- Screenshot posts, comments and direct messages, including the profile they came from and the web address if you can.
- Many platforms let you download a copy of your account data from your settings.
- Save emails as files, or forward them to a private account.

## Clothing and physical items

- If you can, keep the clothes you were wearing without washing them.
- Store each item separately in a paper bag, not plastic. Plastic can damage evidence.
- Keep anything else that may matter, such as bedding, the same way.

## Write down what you remember

When you are able, write down what happened, when and where, and who you talked to afterward. Date your notes. Small details can matter later.

## Request records

- **Medical records** from any hospital or clinic visit. You have a right to request copies.
- **Police reports** from local or campus police, including the report number.
- **School records**, including Title IX reports and emails with school staff. Students generally have a right to see their education records.

## Keep copies safe

If someone else can see your phone, email or accounts, store copies somewhere they cannot reach, such as with a trusted person, an advocate or an attorney.', 30, 1);
