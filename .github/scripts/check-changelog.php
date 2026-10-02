<?php

declare(strict_types=1);

/*
 * This file is part of the StixxOpenApiCommandBundle package.
 *
 * (c) Stixx
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

const REPOSITORY = 'https://github.com/stixx/openapi-command-bundle';
const CHANGELOG = 'CHANGELOG.md';

/**
 * @return list<string>
 */
function git(string ...$arguments): array
{
    $command = 'git '.implode(' ', array_map(escapeshellarg(...), $arguments)).' 2>/dev/null';
    exec($command, $output, $status);

    return $status === 0 ? $output : [];
}

/**
 * @return array{headings: array<string, array{line: int, date: ?string}>, sections: array<string, string>, links: array<string, array{line: int, url: string}>}
 */
function parse(string $changelog): array
{
    $headings = [];
    $sections = [];
    $links = [];
    $current = null;

    foreach (explode("\n", $changelog) as $index => $line) {
        if (preg_match('/^## \[([^\]]+)\](?: - (\d{4}-\d{2}-\d{2}))?\s*$/', $line, $match)) {
            $current = $match[1];
            $headings[$current] = ['line' => $index + 1, 'date' => $match[2] ?? null];
            $sections[$current] = '';
            continue;
        }

        if (preg_match('/^\[([^\]]+)\]: (\S+)\s*$/', $line, $match)) {
            $links[$match[1]] = ['line' => $index + 1, 'url' => $match[2]];
            $current = null;
            continue;
        }

        if ($current !== null) {
            $sections[$current] .= $line."\n";
        }
    }

    return ['headings' => $headings, 'sections' => array_map(trim(...), $sections), 'links' => $links];
}

/**
 * @param list<string> $tags sorted ascending
 */
function previousTag(array $tags, string $version): ?string
{
    $previous = null;
    foreach ($tags as $tag) {
        if (version_compare($tag, $version, '>=')) {
            break;
        }
        $previous = $tag;
    }

    return $previous;
}

$releasing = $argv[1] ?? null;
$errors = [];
$error = static function (int $line, string $message) use (&$errors): void {
    $errors[] = sprintf('::error file=%s,line=%d::%s', CHANGELOG, $line, $message);
};

$changelog = parse((string) file_get_contents(CHANGELOG));
$headings = $changelog['headings'];
$links = $changelog['links'];

$tags = array_values(array_filter(git('tag', '--list'), static fn (string $tag): bool => preg_match('/^\d+\.\d+\.\d+$/', $tag) === 1));
usort($tags, static fn (string $left, string $right): int => version_compare($left, $right));

if ($tags === []) {
    fwrite(STDERR, "No tags found; fetch them first (actions/checkout with fetch-depth: 0).\n");
    exit(1);
}

$versions = array_values(array_filter(array_keys($headings), static fn (string $name): bool => $name !== 'Unreleased'));

if (array_key_first($headings) !== 'Unreleased') {
    $error(1, 'The first entry must be "## [Unreleased]".');
}

foreach ($tags as $tag) {
    if (!isset($headings[$tag])) {
        $error(1, sprintf('Tag %s has no "## [%s]" entry.', $tag, $tag));
    }
}

$newest = $versions[0] ?? null;
foreach ($versions as $version) {
    $line = $headings[$version]['line'];

    if ($headings[$version]['date'] === null) {
        $error($line, sprintf('Entry %s has no date ("## [%s] - YYYY-MM-DD").', $version, $version));
    }

    $previous = previousTag($tags, $version);
    $expected = $previous === null ? REPOSITORY.'/releases/tag/'.$version : REPOSITORY.'/compare/'.$previous.'...'.$version;
    if (!isset($links[$version])) {
        $error($line, sprintf('Entry %s has no compare link; add "[%s]: %s".', $version, $version, $expected));
    } elseif ($links[$version]['url'] !== $expected) {
        $error($links[$version]['line'], sprintf('The link for %s should be %s.', $version, $expected));
    }

    if (!in_array($version, $tags, true)) {
        if ($version !== $newest) {
            $error($line, sprintf('Entry %s is not tagged, but a newer entry follows it.', $version));
        }
        continue;
    }

    $shipped = git('show', $version.':'.CHANGELOG);
    if ($shipped === []) {
        continue;
    }

    $released = parse(implode("\n", $shipped))['sections'][$version] ?? null;
    if ($released !== null && $released !== $changelog['sections'][$version]) {
        $error($line, sprintf('Entry %s differs from the one shipped in tag %s. Released entries are frozen: put new changes under the next version.', $version, $version));
    }
}

if ($newest !== null) {
    $expected = REPOSITORY.'/compare/'.$newest.'...HEAD';
    if (($links['Unreleased']['url'] ?? null) !== $expected) {
        $error($links['Unreleased']['line'] ?? 1, sprintf('The [Unreleased] link should be %s.', $expected));
    }
}

if ($releasing !== null) {
    if ($releasing !== $newest) {
        $error(1, sprintf('Tag %s is not the newest entry (%s).', $releasing, $newest ?? 'none'));
    } else {
        $tagged = git('for-each-ref', 'refs/tags/'.$releasing, '--format=%(creatordate:short)')[0] ?? null;
        if ($tagged !== null && $headings[$releasing]['date'] !== $tagged) {
            $error($headings[$releasing]['line'], sprintf('Entry %s is dated %s but was tagged on %s.', $releasing, $headings[$releasing]['date'] ?? 'never', $tagged));
        }
    }
}

if ($errors !== []) {
    echo implode("\n", $errors), "\n";
    exit(1);
}

printf("CHANGELOG.md is consistent with %d tags%s.\n", count($tags), $releasing === null ? '' : ' and release '.$releasing);
