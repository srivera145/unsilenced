<?php
use EchoDial\Deck\Deck;
use Keel\App\Support\Config;
use Keel\App\Support\Format;

$navCurrent = 'methodology';
$clery = (array) Config::get('sources.clery', []);
$ipeds = (array) Config::get('sources.ipeds', []);
$bands = (array) Config::get('size_bands', []);
$threshold = (int) Config::get('context_note_min_enrollment', 5000);
$locations = (array) Config::get('locations', []);
$cleryYears = $cleryYears ?? [];
?>
<!DOCTYPE html>
<html <?= Deck::htmlAttributes(lang: 'en') ?>>
<head>
<?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
    <?php require __DIR__ . '/partials/public-header.php'; ?>

    <main id="main-content" tabindex="-1" class="container public-page">
        <article class="prose">
            <h1 class="h2">How we get our data</h1>
            <p class="lede">Unsilenced uses only public data. Every figure on a school page is shown with its source and year. This page explains where the numbers come from, how we calculate the rest, and what the numbers cannot tell you.</p>

            <h2>Where the numbers come from</h2>
            <h3>Clery Act crime statistics</h3>
            <p>The Jeanne Clery Disclosure of Campus Security Policy and Campus Crime Statistics Act (the Clery Act) requires colleges and universities that take part in federal student aid programs to report certain crimes each year. Schools submit their figures to the U.S. Department of Education, which publishes them. We import the department's published data files: <a href="<?= Format::e($clery['url'] ?? '') ?>" rel="noopener noreferrer"><?= Format::e($clery['name'] ?? '') ?></a>.</p>
            <p>We show seven offenses:</p>
            <ul>
                <li><strong>Sex offenses:</strong> rape, fondling, incest and statutory rape.</li>
                <li><strong>Dating violence, domestic violence and stalking</strong>, which the Violence Against Women Reauthorization Act of 2013 added to what schools must report.</li>
            </ul>
            <p>Clery figures are reported by calendar year. The year on our pages is the calendar year the department's data covers<?= $cleryYears !== [] ? '; we currently have ' . Format::e(Format::list($cleryYears)) : '' ?>.</p>
            <p>Schools report each offense by where it happened:</p>
            <ul>
                <li><strong><?= Format::e($locations['on_campus'] ?? 'On campus') ?></strong>, which includes on-campus student housing.</li>
                <li><strong><?= Format::e($locations['on_campus_housing'] ?? 'On-campus student housing') ?></strong>, reported separately but already counted in on campus.</li>
                <li><strong><?= Format::e($locations['noncampus'] ?? 'Noncampus') ?></strong>: buildings the school or a recognized student organization owns or controls away from campus.</li>
                <li><strong><?= Format::e($locations['public_property'] ?? 'Public property') ?></strong>: streets, sidewalks and similar areas next to campus.</li>
            </ul>
            <p>Clery reporting does not cover most places students live and socialize off campus, such as private apartments.</p>

            <h3>Enrollment and school details</h3>
            <p>School names, locations, public or private status and total enrollment come from the <a href="<?= Format::e($ipeds['url'] ?? '') ?>" rel="noopener noreferrer"><?= Format::e($ipeds['name'] ?? '') ?></a> (Integrated Postsecondary Education Data System), the federal government's core data on colleges. Each school page names the IPEDS year its enrollment comes from.</p>

            <h2>How we calculate</h2>
            <ul>
                <li><strong>Totals</strong> add on-campus, noncampus and public-property reports. We never add student housing again, because it is already part of on campus.</li>
                <li><strong>Rate per 1,000 students</strong> is total reports divided by total enrollment, times 1,000. We use the most recent IPEDS enrollment we have for every year shown, and say which year that is.</li>
                <li><strong>State comparison:</strong> all reports at every school in the state that reported for that year, divided by all of those schools' enrolled students, times 1,000. Each school counts in proportion to its size, so very small schools do not swing the figure.</li>
                <li><strong>Similar-size comparison:</strong> the same calculation across every school in the country in the same enrollment band:
                    <?= Format::e(Format::list(array_map(static fn (array $band): string => (string) $band['label'], $bands))) ?>.</li>
                <li>A school without an enrollment figure, or without Clery data for that year, is left out of comparisons. The school's own figures are included in its state and size groups.</li>
                <li>Some institutions report several campuses under one federal ID. We add those campuses together.</li>
            </ul>
            <p>We do not give schools grades or scores. We show the figures, their sources and how they compare.</p>

            <h2 id="underreporting">What the numbers cannot show</h2>
            <p>Clery figures count <strong>reports</strong> made to the school or to police, not every assault that happened. National research has found that most sexual assaults involving college students are never reported:</p>
            <ul>
                <li>The U.S. Bureau of Justice Statistics found that 80% of rape and sexual assault victimizations of female college students aged 18 to 24 were not reported to police (<a href="https://bjs.ojp.gov/content/pub/pdf/rsavcaf9513.pdf" rel="noopener noreferrer">Rape and Sexual Assault Victimization Among College-Age Females, 1995–2013</a>, 2014).</li>
                <li>The Association of American Universities' 2019 campus climate survey of students at 33 schools found that most students who experienced nonconsensual sexual contact did not contact a campus program or resource (<a href="https://www.aau.edu/key-issues/campus-climate-and-safety/aau-campus-climate-survey-2019" rel="noopener noreferrer">AAU Campus Climate Survey, 2019</a>).</li>
            </ul>
            <p>Because of this, a low number can reflect underreporting, and a higher number can reflect a school where more students feel able to come forward. Neither, on its own, shows whether a campus is more or less safe. When a school with <?= Format::number($threshold) ?> or more students reports zero rapes in a year, its page shows a short note saying so. The note appears for every school that meets that rule and is not a finding about any school.</p>
            <p>Clery data also says nothing about how a school responded to a report. That is what the accountability record is for.</p>

            <h2>Accountability records</h2>
            <p>We add public records about how a school has handled sexual violence: federal Title IX investigations by the U.S. Department of Education's Office for Civil Rights, lawsuits, state reviews and news coverage. Each record has a date, a status and a link to its source. We write every summary in our own words; we do not copy articles. We never publish the name of any individual, including anyone accused.</p>

            <h2>What we do not do</h2>
            <ul>
                <li>We do not collect or publish personal stories, and this site has no submission forms.</li>
                <li>Our public pages set no cookies, and load no analytics, trackers, ads, fonts or scripts from other websites.</li>
                <li>Every page has a quick-exit button, and pressing Esc twice leaves the site at once.</li>
            </ul>

            <h2>Corrections</h2>
            <p>If you believe a figure is wrong, first compare it with the school's own Annual Security Report, which it must publish each year, and with the Department of Education's data. Our figures match the department's files as published; if a school later corrects its report, we pick that up when we import the updated data.</p>
        </article>
    </main>

    <?php require __DIR__ . '/partials/public-footer.php'; ?>
</body>
</html>
