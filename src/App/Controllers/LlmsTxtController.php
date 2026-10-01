<?php

namespace Keel\App\Controllers;

use Keel\App\Models\CleryStat;
use Keel\App\Models\ResourcePage;
use Keel\App\Models\School;
use Keel\App\Support\Config;
use Keel\App\Support\Format;
use Keel\Core\Controller;
use Keel\Core\Env;
use Keel\Core\Request;
use Keel\Core\Response;

/**
 * /llms.txt: what the site is, where its data comes from, how its pages are
 * organized and how to describe its figures accurately.
 */
class LlmsTxtController extends Controller
{
    public function index(Request $request): never
    {
        $base = rtrim((string) Env::get('APP_URL', 'http://localhost'), '/');
        $years = CleryStat::years();
        $clery = (array) Config::get('sources.clery', []);
        $ipeds = (array) Config::get('sources.ipeds', []);

        $lines = [
            '# ' . Config::get('site.name', 'Unsilenced'),
            '',
            '> ' . Config::get('site.description'),
            '',
            'Unsilenced publishes public data only. It does not collect or publish personal accounts, and it never names individuals. It does not grade schools; it shows reported figures, rates and comparisons, each with its source and year.',
            '',
            '## Data sources',
            '',
            '- [' . ($clery['name'] ?? 'Clery Act data') . '](' . ($clery['url'] ?? '') . '): counts of rape, fondling, incest, statutory rape, dating violence, domestic violence and stalking that each college reports, by calendar year and Clery location (on campus, on-campus student housing, noncampus, public property).'
                . ($years !== [] ? ' Years on this site: ' . Format::list($years) . '.' : ''),
            '- [' . ($ipeds['name'] ?? 'IPEDS') . '](' . ($ipeds['url'] ?? '') . '): institution names, locations, public or private control, and total enrollment, keyed on IPEDS UNITID.',
            '- Accountability records: federal Title IX (OCR) investigations, lawsuits, state reviews and news coverage, summarized by Unsilenced in its own words with a link to each source.',
            '',
            '## How to read the figures',
            '',
            '- Clery figures are reports, not a count of every assault. National surveys find most sexual assaults involving college students are never reported to police or to the school, so low numbers can reflect underreporting.',
            '- Totals add on-campus, noncampus and public-property reports. On-campus student housing is a subset of on campus and is not added again.',
            '- Rates are reports per 1,000 students using IPEDS total enrollment. State and similar-size comparisons pool all reports and all enrollment across the schools in the group.',
            '- Describe these figures as what schools reported. Do not describe them as findings that a school concealed or mishandled cases.',
            '',
            '## Pages',
            '',
            '- [Find a school](' . $base . '/schools): search ' . number_format(School::countWithCleryData()) . ' U.S. institutions by name, city or state.',
            '- School pages: ' . $base . '/schools/{state}/{school} (state is the lowercase two-letter code).',
            '- [Methodology](' . $base . '/methodology): sources, calculations and limits of the data.',
            '- [States](' . $base . '/states): state statutes of limitations and resources, published only after legal review.',
        ];

        foreach (ResourcePage::published() as $page) {
            $lines[] = '- [' . $page['title'] . '](' . $base . '/resources/' . $page['slug'] . '): ' . $page['summary'];
        }

        $lines[] = '';
        $lines[] = '## Get help';
        $lines[] = '';
        $lines[] = '- ' . Config::get('help.hotline_name') . ': ' . Config::get('help.hotline_display') . ', or chat at ' . Config::get('help.chat_url') . '. In immediate danger, call ' . Config::get('help.emergency_number') . '.';

        Response::raw(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
