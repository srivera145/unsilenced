<?php

declare(strict_types=1);

/**
 * Builds src/App/Services/Survivor/case-key-words.txt, the wordlist case keys
 * are drawn from, from the EFF large wordlist.
 *
 *   curl -O https://www.eff.org/files/2016/07/18/eff_large_wordlist.txt
 *   php scripts/case-key-words/build.php eff_large_wordlist.txt
 *
 * The EFF list (7,776 words, CC BY 3.0 US, Electronic Frontier Foundation) is
 * built for passphrases: common, concrete, easy to spell. A survivor reads
 * these words on the screen where her report has just gone in, and keeps
 * them, so this removes every word that is violent, sexual, about the body,
 * drink or drugs, illness, crime, shame or fear, plus words that merely
 * contain one of those (grape, therapist). Plenty remain: CaseKeyServiceTest
 * fails if six words carry less than 70 bits.
 *
 * Expected input SHA-256 (the 2016 file):
 * addd35536511597a02fa0a9ff1e5284677b8883b83e986e43f15a3db996b903e
 */

if ($argc < 2 || !is_file($argv[1])) {
    fwrite(STDERR, "Usage: php scripts/case-key-words/build.php eff_large_wordlist.txt\n");
    exit(1);
}

$source = (string) file_get_contents($argv[1]);
$hash = hash('sha256', $source);
if ($hash !== 'addd35536511597a02fa0a9ff1e5284677b8883b83e986e43f15a3db996b903e') {
    fwrite(STDERR, "Warning: input SHA-256 is {$hash}, not the 2016 EFF file's.\n");
}

$blocked = require __DIR__ . '/blocklist.php';

$words = [];
foreach (preg_split('/\R/', $source) ?: [] as $line) {
    $parts = preg_split('/\s+/', trim($line));
    $word = strtolower((string) end($parts));

    // Letters only, 3 to 9 of them: no hyphens ("t-shirt"), nothing long to type.
    if (!preg_match('/^[a-z]{3,9}$/', $word)) {
        continue;
    }

    foreach ($blocked['contains'] as $stem) {
        if (str_contains($word, $stem)) {
            continue 2;
        }
    }

    if (in_array($word, $blocked['exact'], true) || in_array($word, $blocked['reviewed'], true)) {
        continue;
    }

    foreach ($blocked['prefix'] as $prefix) {
        if (str_starts_with($word, $prefix)) {
            continue 2;
        }
    }

    $words[$word] = true;
}

$words = array_keys($words);
sort($words, SORT_STRING);

$target = dirname(__DIR__, 2) . '/src/App/Services/Survivor/case-key-words.txt';
file_put_contents($target, implode("\n", $words) . "\n");

$bits = 6 * log(count($words), 2);
fwrite(STDOUT, sprintf("%d words written to %s (%.1f bits for six words)\n", count($words), $target, $bits));
