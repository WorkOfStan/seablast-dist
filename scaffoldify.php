#!/usr/bin/env php
<?php

/**
 * scaffoldify.php
 *
 * Cross-platform PHP CLI script that:
 *  1) asks for replacement values for the current seablast-dist identity
 *  2) saves confirmed replacement values as local defaults for later runs
 *  3) recursively replaces occurrences in files under --root
 *  4) removes blocks marked by SCAFFOLDIFY:REMOVE-START/END tokens
 *  5) removes boilerplate-only files from the built-in inventory
 *  6) removes demo dependencies from composer.json
 *
 * Compatible with PHP 7.2+ and 8.1+.
 */

declare(strict_types=1);

// CLI entrypoint intentionally mixes declarations and runtime execution and keeps helper classes together in one file.
// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols
// phpcs:disable PSR1.Classes.ClassDeclaration.MissingNamespace
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

// todo ask also for database
// todo add phinx with smart

const MARKER_LABEL = 'dist-demo';
const START_TOKEN = 'SCAFFOLDIFY:REMOVE-START ' . MARKER_LABEL;
const END_TOKEN = 'SCAFFOLDIFY:REMOVE-END ' . MARKER_LABEL;
const REPLACEMENT_CACHE_RELATIVE_PATH = 'cache/scaffoldify-replacements.json';
const REPLACEMENT_CACHE_VERSION = 1;

/** @var array<int, string> Demo dependencies to remove from require and require-dev. */
const REMOVE_COMPOSER_PACKAGES = [
    'guzzlehttp/guzzle',
];

const STANDARD_REPLACEMENT_KEYS = [
    'repository_url',
    'issues_url',
    'composer_package',
    'php_namespace',
    'repository_slug',
    'home_label',
    'author_name',
    'author_email',
];

const SKIP_DIR_NAMES = [
    '.git',
    'vendor',
    'node_modules',
    '.idea',
    '.vscode',
];

/**
 * Boilerplate-only files to delete after scaffold cleanup.
 *
 * Keep this list relative to --root and never use absolute or parent-traversing paths here.
 *
 * @var array<int, string>
 */
const REMOVE_PATHS = [
    'src/Models/ArithmeticModel.php',
    'src/Models/BlogModel.php',
    'src/Models/ApiMirrorModel.php',
    'src/Models/UseMirrorModel.php',
    'src/Models/RedirModel.php',
    'views/arithmetic.latte',
    'views/blog-editable.latte',
    'views/blog-readonly.latte',
    'views/footer.latte',
    'views/item.latte',
    'views/mirror.latte',
    'conf/db/migrations/20250803081249_first_blog_posts.php',
    'conf/db/migrations/20260222090000_create_arithmetic_attempts.php',
];

final class Assert
{
    /**
     * @param mixed $value
     * @phpstan-assert array<array-key, mixed> $value
     */
    public static function isArray($value, string $message = 'Expected array'): void
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException($message);
        }
    }

    /**
     * @param mixed $value
     * @phpstan-assert non-empty-string $value
     */
    public static function stringNotEmpty($value, string $message = 'Expected non-empty string'): void
    {
        if (!is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException($message);
        }
    }
}

final class Cli
{
    public static function confirm(string $label, bool $defaultYes = true): bool
    {
        $hint = $defaultYes ? 'Y/n' : 'y/N';
        self::out($label . " ({$hint}): ");
        $line = fgets(STDIN);
        if ($line === false) {
            return $defaultYes;
        }
        $value = strtolower(trim($line));
        if ($value === '') {
            return $defaultYes;
        }
        return in_array($value, ['y', 'yes'], true);
    }

    public static function err(string $message): void
    {
        fwrite(STDERR, $message);
    }

    public static function out(string $message): void
    {
        fwrite(STDOUT, $message);
    }

    public static function prompt(string $label, ?string $default = null): string
    {
        $suffix = ($default !== null && $default !== '') ? " [{$default}]" : '';
        self::out($label . $suffix . ': ');
        $line = fgets(STDIN);
        if ($line === false) {
            return $default ?? '';
        }
        $value = trim($line);
        if ($value === '' && $default !== null) {
            return $default;
        }
        return $value;
    }
}

final class FileWalker
{
    /**
     * @return \Generator<string> yields absolute file paths
     */
    public static function yieldFiles(string $root): \Generator
    {
        $root = rtrim($root, "\\/");

        $it = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);

        $filter = new \RecursiveCallbackFilterIterator($it, function (\SplFileInfo $current): bool {
            if ($current->isDir()) {
                return !in_array($current->getFilename(), SKIP_DIR_NAMES, true);
            }
            return true;
        });

        $rii = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::LEAVES_ONLY);

        /** @var \SplFileInfo $file */
        foreach ($rii as $file) {
            if ($file->isFile()) {
                yield $file->getPathname();
            }
        }
    }
}

final class ReplacementCache
{
    /** @var string */
    private $canonicalRoot;

    /** @var string */
    private $path;

    public function __construct(string $canonicalRoot)
    {
        $this->canonicalRoot = rtrim($canonicalRoot, "\\/");
        $this->path = $this->canonicalRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, REPLACEMENT_CACHE_RELATIVE_PATH);
    }

    private function isWithinCanonicalRoot(string $path): bool
    {
        $root = $this->normalizeComparablePath($this->canonicalRoot);
        $normalizedPath = $this->normalizeComparablePath($path);

        return $normalizedPath === $root || strpos($normalizedPath, $root . '/') === 0;
    }

    /**
     * @return array{standard:array<string,string>,extra:array<int,array{old:string,new:string}>}|null
     */
    public function load(): ?array
    {
        if (!file_exists($this->path) && !is_link($this->path)) {
            return null;
        }

        if (is_link($this->path)) {
            Cli::err("Warning: replacement cache is a symbolic link, ignoring: {$this->path}\n");
            return null;
        }

        $directory = dirname($this->path);
        $canonicalDirectory = realpath($directory);
        if (
            is_link($directory)
            || $canonicalDirectory === false
            || !$this->isWithinCanonicalRoot($canonicalDirectory)
        ) {
            Cli::err("Warning: replacement cache directory escaped root, ignoring: {$directory}\n");
            return null;
        }

        $contents = @file_get_contents($this->path);
        if ($contents === false) {
            Cli::err("Warning: cannot read replacement cache, using built-in defaults: {$this->path}\n");
            return null;
        }

        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            Cli::err("Warning: invalid replacement cache JSON, using built-in defaults: {$this->path}\n");
            return null;
        }

        return $this->validate($decoded);
    }

    private function normalizeComparablePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = rtrim($normalized, '/');

        if (DIRECTORY_SEPARATOR === '\\') {
            $normalized = strtolower($normalized);
        }

        return $normalized;
    }

    /**
     * @param array{standard:array<string,string>,extra:array<int,array{old:string,new:string}>} $replacements
     */
    public function save(array $replacements): bool
    {
        if (is_link($this->path)) {
            Cli::err("Warning: replacement cache is a symbolic link, not writing: {$this->path}\n");
            return false;
        }

        $directory = dirname($this->path);
        if (is_link($directory)) {
            Cli::err("Warning: replacement cache directory is a symbolic link, not writing: {$directory}\n");
            return false;
        }

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            Cli::err("Warning: cannot create replacement cache directory: {$directory}\n");
            return false;
        }

        $canonicalDirectory = realpath($directory);
        if ($canonicalDirectory === false || !$this->isWithinCanonicalRoot($canonicalDirectory)) {
            Cli::err("Warning: replacement cache directory escaped root, not writing: {$directory}\n");
            return false;
        }

        $payload = [
            'version' => REPLACEMENT_CACHE_VERSION,
            'standard' => $replacements['standard'],
            'extra' => $replacements['extra'],
        ];
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            Cli::err("Warning: cannot encode replacement cache as JSON: {$this->path}\n");
            return false;
        }

        $written = @file_put_contents($this->path, $encoded . "\n", LOCK_EX);
        if ($written === false) {
            Cli::err("Warning: cannot write replacement cache: {$this->path}\n");
            return false;
        }

        Cli::out("Saved replacement defaults: {$this->path}\n");
        return true;
    }

    /**
     * @param array<array-key, mixed> $decoded
     * @return array{standard:array<string,string>,extra:array<int,array{old:string,new:string}>}|null
     */
    private function validate(array $decoded): ?array
    {
        if (!isset($decoded['version']) || $decoded['version'] !== REPLACEMENT_CACHE_VERSION) {
            Cli::err("Warning: incompatible replacement cache version, using built-in defaults: {$this->path}\n");
            return null;
        }

        if (!isset($decoded['standard']) || !is_array($decoded['standard'])) {
            Cli::err("Warning: invalid replacement cache data, using built-in defaults: {$this->path}\n");
            return null;
        }

        $standard = [];
        foreach (STANDARD_REPLACEMENT_KEYS as $key) {
            if (!array_key_exists($key, $decoded['standard']) || !is_string($decoded['standard'][$key])) {
                Cli::err("Warning: invalid replacement cache data, using built-in defaults: {$this->path}\n");
                return null;
            }
            $standard[$key] = $decoded['standard'][$key];
        }

        if (!isset($decoded['extra']) || !is_array($decoded['extra'])) {
            Cli::err("Warning: invalid replacement cache data, using built-in defaults: {$this->path}\n");
            return null;
        }

        $extra = [];
        foreach ($decoded['extra'] as $pair) {
            if (
                !is_array($pair)
                || !array_key_exists('old', $pair)
                || !array_key_exists('new', $pair)
                || !is_string($pair['old'])
                || !is_string($pair['new'])
                || $pair['old'] === ''
            ) {
                Cli::err("Warning: invalid replacement cache data, using built-in defaults: {$this->path}\n");
                return null;
            }

            $extra[] = [
                'old' => $pair['old'],
                'new' => $pair['new'],
            ];
        }

        return [
            'standard' => $standard,
            'extra' => $extra,
        ];
    }
}

final class Scaffoldify
{
    /** @var string */
    private $cachePath;

    /** @var string */
    private $canonicalRoot;

    /** @var bool */
    private $dryRun;

    /** @var array<string,string> */
    private $replaceMap;

    /** @var array<string, bool> */
    private $removeTargetLookup;

    /** @var array<int, array{relative:string, absolute:string}> */
    private $removeTargets;

    /** @var string */
    private $root;

    /** @var string */
    private $scriptPath;

    /**
     * @param array<string, string> $replaceMap
     */
    public function __construct(string $root, bool $dryRun, array $replaceMap)
    {
        Assert::stringNotEmpty($root, 'Root must be a non-empty string');

        $canonicalRoot = realpath($root);
        if ($canonicalRoot === false || !is_dir($canonicalRoot)) {
            throw new \InvalidArgumentException('Root directory not found: ' . $root);
        }

        $scriptPath = realpath(__FILE__);
        if ($scriptPath === false) {
            throw new \RuntimeException('Unable to resolve scaffoldify.php path.');
        }

        $this->cachePath = $canonicalRoot
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, REPLACEMENT_CACHE_RELATIVE_PATH);
        $this->canonicalRoot = rtrim($canonicalRoot, "\\/");
        $this->dryRun = $dryRun;
        $this->replaceMap = $this->sortReplaceMapByKeyLength($replaceMap);
        $this->removeTargetLookup = [];
        $this->removeTargets = [];
        $this->root = rtrim($root, "\\/");
        $this->scriptPath = $scriptPath;

        $this->prepareRemoveTargets();
        $this->validateComposerManifest();
    }

    private function applyReplacements(string $content): string
    {
        if ($this->replaceMap === []) {
            return $content;
        }

        return str_replace(array_keys($this->replaceMap), array_values($this->replaceMap), $content);
    }

    /**
     * @return array{checked:int, deleted:int, missing:int, rejected:int}
     */
    private function deleteRemoveTargets(): array
    {
        $summary = [
            'checked' => 0,
            'deleted' => 0,
            'missing' => 0,
            'rejected' => 0,
        ];

        if ($this->removeTargets === []) {
            Cli::out("== Delete step: no remove targets configured ==\n");
            return $summary;
        }

        Cli::out("== Delete step: removing configured boilerplate files ==\n");

        foreach ($this->removeTargets as $targetInfo) {
            $summary['checked']++;

            $absolute = $targetInfo['absolute'];
            $relative = $targetInfo['relative'];

            if (!file_exists($absolute) && !is_link($absolute)) {
                $summary['missing']++;
                continue;
            }

            $canonical = realpath($absolute);
            if ($canonical === false) {
                Cli::err("Warning: unable to resolve delete target, skipping: {$relative}\n");
                $summary['rejected']++;
                continue;
            }

            if ($this->samePath($canonical, $this->canonicalRoot) || !$this->isWithinCanonicalRoot($canonical)) {
                Cli::err("Warning: delete target escaped root, skipping: {$relative}\n");
                $summary['rejected']++;
                continue;
            }

            if ($this->samePath($canonical, $this->scriptPath)) {
                Cli::err("Warning: self-delete is intentionally disabled, skipping: {$relative}\n");
                $summary['rejected']++;
                continue;
            }

            if ($this->dryRun) {
                Cli::out("would delete: {$canonical}\n");
                $summary['deleted']++;
                continue;
            }

            if ($this->rmrf($canonical)) {
                Cli::out("deleted: {$canonical}\n");
                $summary['deleted']++;
            } else {
                Cli::err("Warning: failed to delete: {$canonical}\n");
                $summary['rejected']++;
            }
        }

        return $summary;
    }

    private function isProbablyBinary(string $filePath): bool
    {
        $fh = @fopen($filePath, 'rb');
        if ($fh === false) {
            return false;
        }

        $chunk = (string) @fread($fh, 8192);
        @fclose($fh);

        if ($chunk === '') {
            return false;
        }

        return strpos($chunk, "\0") !== false;
    }

    private function isScheduledForDeletion(string $filePath): bool
    {
        $lookupKey = $this->normalizeComparablePath($filePath);
        return isset($this->removeTargetLookup[$lookupKey]);
    }

    private function isWithinCanonicalRoot(string $path): bool
    {
        $root = $this->normalizeComparablePath($this->canonicalRoot);
        $normalizedPath = $this->normalizeComparablePath($path);

        return $normalizedPath === $root || strpos($normalizedPath, $root . '/') === 0;
    }

    private function normalizeComparablePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = rtrim($normalized, '/');

        if (DIRECTORY_SEPARATOR === '\\') {
            $normalized = strtolower($normalized);
        }

        return $normalized;
    }

    private function prepareRemoveTargets(): void
    {
        foreach (REMOVE_PATHS as $relativeRaw) {
            $relative = $this->sanitizeRelativeDeletePath($relativeRaw);
            if ($relative === null) {
                Cli::err("Warning: invalid REMOVE_PATHS entry skipped: {$relativeRaw}\n");
                continue;
            }

            $absolute = $this->canonicalRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $lookupKey = $this->normalizeComparablePath($absolute);

            $this->removeTargets[] = [
                'relative' => $relative,
                'absolute' => $absolute,
            ];
            $this->removeTargetLookup[$lookupKey] = true;
        }
    }

    /**
     * @return array{content:string, packages:array<int, string>}
     */
    private function removeComposerPackages(string $content): array
    {
        $manifest = json_decode($content);
        if (json_last_error() !== JSON_ERROR_NONE || !($manifest instanceof \stdClass)) {
            throw new \RuntimeException('Invalid composer.json: expected a JSON object.');
        }

        $packages = [];
        foreach (['require', 'require-dev'] as $section) {
            if (!property_exists($manifest, $section)) {
                continue;
            }
            if (!($manifest->{$section} instanceof \stdClass)) {
                throw new \RuntimeException('Invalid composer.json: ' . $section . ' must be a JSON object.');
            }
            foreach (REMOVE_COMPOSER_PACKAGES as $package) {
                if (property_exists($manifest->{$section}, $package)) {
                    unset($manifest->{$section}->{$package});
                    $packages[] = $section . ': ' . $package;
                }
            }
        }

        if ($packages === []) {
            return ['content' => $content, 'packages' => []];
        }

        $encoded = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($encoded === false) {
            throw new \RuntimeException('Unable to encode composer.json.');
        }

        $indent = '    ';
        if (preg_match('/(?:\r\n|\n|\r)([ \t]+)"/', $content, $matches) === 1) {
            $indent = $matches[1];
        }
        $formatted = preg_replace_callback('/^( +)/m', function (array $matches) use ($indent): string {
            return str_repeat($indent, (int) (strlen($matches[1]) / 4));
        }, $encoded);
        if ($formatted === null) {
            throw new \RuntimeException('Unable to format composer.json.');
        }

        $lineEnding = "\n";
        if (preg_match('/\r\n|\n|\r/', $content, $matches) === 1) {
            $lineEnding = $matches[0];
        }
        $formatted = str_replace("\n", $lineEnding, $formatted);
        if (preg_match('/(?:\r\n|\n|\r)$/', $content) === 1) {
            $formatted .= $lineEnding;
        }

        return ['content' => $formatted, 'packages' => $packages];
    }

    /**
     * @return array{content:string, removedBlocks:int}
     */
    private function removeMarkedBlocks(string $content, string $filePath): array
    {
        $startCount = substr_count($content, START_TOKEN);
        $endCount = substr_count($content, END_TOKEN);

        if ($startCount !== $endCount) {
            Cli::err(
                "Warning: marker count mismatch in {$filePath} (starts={$startCount}, ends={$endCount})\n"
            );
        }

        $start = preg_quote(START_TOKEN, '/');
        $end = preg_quote(END_TOKEN, '/');
        $pattern = "/^[^\r\n]*{$start}[^\r\n]*\R?[\s\S]*?^[^\r\n]*{$end}[^\r\n]*\R?/m";

        $removedBlocks = 0;
        $result = preg_replace($pattern, '', $content, -1, $removedBlocks);

        if ($result === null) {
            Cli::err("Warning: preg_replace failed while removing marker blocks from {$filePath}\n");
            return ['content' => $content, 'removedBlocks' => 0];
        }

        return ['content' => $result, 'removedBlocks' => $removedBlocks];
    }

    private function rmrf(string $path): bool
    {
        if ($this->samePath($path, $this->canonicalRoot) || !$this->isWithinCanonicalRoot($path)) {
            return false;
        }

        if (is_file($path) || is_link($path)) {
            return @unlink($path);
        }

        if (!is_dir($path)) {
            return false;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var \SplFileInfo $item */
        foreach ($it as $item) {
            $itemPath = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($itemPath);
            } else {
                @unlink($itemPath);
            }
        }

        return @rmdir($path);
    }

    public function run(): int
    {
        Cli::out("== Scaffoldify (PHP CLI) ==\n");
        Cli::out("Root (input): {$this->root}\n");
        Cli::out("Root (resolved): {$this->canonicalRoot}\n");
        Cli::out("Dry-run: " . ($this->dryRun ? 'yes' : 'no') . "\n");
        Cli::out("Marker tokens: " . START_TOKEN . ' / ' . END_TOKEN . "\n");
        Cli::out("Configured delete targets: " . count($this->removeTargets) . "\n\n");

        $edited = 0;
        $filesWithBlocksRemoved = 0;
        $blocksRemoved = 0;
        $skippedBinary = 0;
        $skippedCache = 0;
        $skippedSelf = 0;
        $skippedScheduledDeletes = 0;
        $composerChanged = false;
        $composerPath = $this->canonicalRoot . DIRECTORY_SEPARATOR . 'composer.json';

        foreach (FileWalker::yieldFiles($this->canonicalRoot) as $filePath) {
            if ($this->samePath($filePath, $this->scriptPath)) {
                $skippedSelf++;
                continue;
            }

            if ($this->samePath($filePath, $this->cachePath)) {
                $skippedCache++;
                continue;
            }

            if ($this->isScheduledForDeletion($filePath)) {
                $skippedScheduledDeletes++;
                continue;
            }

            if ($this->isProbablyBinary($filePath)) {
                $skippedBinary++;
                continue;
            }

            $original = @file_get_contents($filePath);
            if ($original === false) {
                Cli::err("Warning: cannot read file: {$filePath}\n");
                continue;
            }

            $updated = $this->applyReplacements($original);
            $result = $this->removeMarkedBlocks($updated, $filePath);
            $updatedContent = $result['content'];
            $removedBlocksInFile = $result['removedBlocks'];
            $removedPackages = [];
            if ($this->samePath($filePath, $composerPath)) {
                $composerResult = $this->removeComposerPackages($updatedContent);
                $updatedContent = $composerResult['content'];
                $removedPackages = $composerResult['packages'];
            }

            if ($removedBlocksInFile > 0) {
                $filesWithBlocksRemoved++;
                $blocksRemoved += $removedBlocksInFile;
            }

            if ($updatedContent === $original) {
                continue;
            }

            if ($this->dryRun) {
                Cli::out("would edit: {$filePath}\n");
            } else {
                $ok = @file_put_contents($filePath, $updatedContent);
                if ($ok === false) {
                    Cli::err("Warning: cannot write file: {$filePath}\n");
                    if ($this->samePath($filePath, $composerPath)) {
                        return 2;
                    }
                    continue;
                }
                Cli::out("edited: {$filePath}\n");
            }
            foreach ($removedPackages as $package) {
                $verb = $this->dryRun ? 'would remove dependency' : 'removed dependency';
                Cli::out("{$verb}: {$package}\n");
                $composerChanged = true;
            }
            $edited++;
        }

        $editLabel = $this->dryRun ? 'Files that would be edited' : 'Files edited';
        $deleteLabel = $this->dryRun ? 'Delete targets that would be removed' : 'Delete targets removed';

        Cli::out("\n== Summary ==\n");
        Cli::out("{$editLabel}: {$edited}\n");
        Cli::out("Files with marker blocks removed: {$filesWithBlocksRemoved}\n");
        Cli::out("Marker blocks removed: {$blocksRemoved}\n");
        Cli::out("Binary-ish files skipped: {$skippedBinary}\n");
        Cli::out("Replacement cache files skipped: {$skippedCache}\n");
        Cli::out("Script file skipped: {$skippedSelf}\n");
        Cli::out("Delete-target files skipped during edit pass: {$skippedScheduledDeletes}\n\n");

        $deleteSummary = $this->deleteRemoveTargets();

        Cli::out("\n== Delete summary ==\n");
        Cli::out("Delete targets checked: {$deleteSummary['checked']}\n");
        Cli::out("{$deleteLabel}: {$deleteSummary['deleted']}\n");
        Cli::out("Delete targets missing: {$deleteSummary['missing']}\n");
        Cli::out("Delete targets rejected: {$deleteSummary['rejected']}\n");
        if ($composerChanged) {
            $prefix = $this->dryRun ? 'After applying these changes' : 'Next';
            Cli::out("\n{$prefix}, run composer update in the target project to synchronize dependencies.\n");
        }
        Cli::out("\nDone.\n");

        return 0;
    }

    private function samePath(string $a, string $b): bool
    {
        return $this->normalizeComparablePath($a) === $this->normalizeComparablePath($b);
    }

    private function sanitizeRelativeDeletePath(string $relative): ?string
    {
        $relative = trim($relative);
        if ($relative === '') {
            return null;
        }

        $normalized = str_replace('\\', '/', $relative);

        if (preg_match('/^(?:[A-Za-z]:\/|\/|\/\/)/', $normalized) === 1) {
            return null;
        }

        $segments = explode('/', $normalized);
        $safeSegments = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            if ($segment === '.' || $segment === '..') {
                return null;
            }
            $safeSegments[] = $segment;
        }

        if ($safeSegments === []) {
            return null;
        }

        return implode('/', $safeSegments);
    }

    /**
     * @param array<string,string> $replaceMap
     * @return array<string,string>
     */
    private function sortReplaceMapByKeyLength(array $replaceMap): array
    {
        if ($replaceMap === []) {
            return $replaceMap;
        }

        uksort($replaceMap, function (string $left, string $right): int {
            return strlen($right) <=> strlen($left);
        });

        return $replaceMap;
    }

    private function validateComposerManifest(): void
    {
        $path = $this->canonicalRoot . DIRECTORY_SEPARATOR . 'composer.json';
        if (is_link($path)) {
            throw new \RuntimeException('Refusing symbolic link composer.json: ' . $path);
        }
        if (!file_exists($path)) {
            return;
        }
        $canonical = realpath($path);
        if ($canonical === false || !$this->isWithinCanonicalRoot($canonical) || !is_file($path)) {
            throw new \RuntimeException('Invalid composer.json path: ' . $path);
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException('Cannot read composer.json: ' . $path);
        }
        $this->removeComposerPackages($content);
        $result = $this->removeMarkedBlocks($this->applyReplacements($content), $path);
        $this->removeComposerPackages($result['content']);
    }
}

final class App
{
    /**
     * @param array<string,string> $replaceMap
     */
    private static function addReplaceIfChanged(array &$replaceMap, string $old, string $new): void
    {
        if ($new !== $old) {
            $replaceMap[$old] = $new;
        }
    }

    /**
     * @param array{standard:array<string,string>,extra:array<int,array{old:string,new:string}>}|null $saved
     * @return array{
     *     replace_map:array<string,string>,
     *     standard:array<string,string>,
     *     extra:array<int,array{old:string,new:string}>
     * }
     */
    private static function buildReplacementPlan(bool $interactive, ?array $saved): array
    {
        if (!$interactive) {
            return [
                'replace_map' => [],
                'standard' => [],
                'extra' => [],
            ];
        }

        $oldRepoUrl = 'https://github.com/WorkOfStan/seablast-dist';
        $oldIssuesUrl = 'https://github.com/WorkOfStan/seablast-dist/issues';
        $oldComposerPackage = 'seablast/dist';
        $oldNamespace = 'Seablast\\Distribution';
        $oldRepoSlug = 'seablast-dist';
        $oldHomeLabel = 'HOME DIST';
        $oldAuthorName = 'Stanislav Rejthar';
        $oldAuthorEmail = 'rejthar@stanislavrejthar.com';
        $savedStandard = $saved['standard'] ?? [];

        Cli::out("== Step 0: Replacements ==\n");
        Cli::out("Enter NEW values. The script replaces the current seablast-dist identity strings.\n\n");

        $newRepoUrl = Cli::prompt(
            '1) New Git repository URL',
            $savedStandard['repository_url'] ?? $oldRepoUrl
        );
        $issuesDefault = $savedStandard['issues_url'] ?? (($newRepoUrl !== $oldRepoUrl)
            ? rtrim($newRepoUrl, '/') . '/issues'
            : $oldIssuesUrl);
        $newIssuesUrl = Cli::prompt('2) New issue tracker URL', $issuesDefault);
        $newComposerPackage = Cli::prompt(
            '3) New Composer package name',
            $savedStandard['composer_package'] ?? $oldComposerPackage
        );
        $newNamespace = Cli::prompt(
            '4) New PHP namespace (use backslashes)',
            $savedStandard['php_namespace'] ?? $oldNamespace
        );
        $newRepoSlug = Cli::prompt(
            '5) New repository slug / short project id',
            $savedStandard['repository_slug'] ?? $oldRepoSlug
        );
        $newHomeLabel = Cli::prompt(
            '6) New nav home label',
            $savedStandard['home_label'] ?? $oldHomeLabel
        );
        $newAuthorName = Cli::prompt(
            '7) New author name',
            $savedStandard['author_name'] ?? $oldAuthorName
        );
        $newAuthorEmail = Cli::prompt(
            '8) New author/support email',
            $savedStandard['author_email'] ?? $oldAuthorEmail
        );

        $standard = [
            'repository_url' => $newRepoUrl,
            'issues_url' => $newIssuesUrl,
            'composer_package' => $newComposerPackage,
            'php_namespace' => $newNamespace,
            'repository_slug' => $newRepoSlug,
            'home_label' => $newHomeLabel,
            'author_name' => $newAuthorName,
            'author_email' => $newAuthorEmail,
        ];
        $replaceMap = [];

        self::addReplaceIfChanged($replaceMap, $oldRepoUrl, $newRepoUrl);
        self::addReplaceIfChanged($replaceMap, $oldIssuesUrl, $newIssuesUrl);
        self::addReplaceIfChanged($replaceMap, $oldComposerPackage, $newComposerPackage);
        self::addReplaceIfChanged(
            $replaceMap,
            str_replace('\\', '\\\\', $oldNamespace) . '\\\\',
            str_replace('\\', '\\\\', $newNamespace) . '\\\\'
        );
        self::addReplaceIfChanged($replaceMap, $oldNamespace, $newNamespace);
        self::addReplaceIfChanged($replaceMap, $oldRepoSlug, $newRepoSlug);
        self::addReplaceIfChanged($replaceMap, $oldHomeLabel, $newHomeLabel);
        self::addReplaceIfChanged($replaceMap, $oldAuthorName, $newAuthorName);
        self::addReplaceIfChanged($replaceMap, $oldAuthorEmail, $newAuthorEmail);

        $extra = [];
        $savedExtra = $saved['extra'] ?? [];
        if ($savedExtra !== []) {
            Cli::out("\nReview saved extra replacement pairs. Press Enter to keep each shown value.\n\n");
        }

        foreach ($savedExtra as $index => $savedPair) {
            $number = $index + 1;
            $extraOld = Cli::prompt("Saved extra {$number} OLD string", $savedPair['old']);
            $extraNew = Cli::prompt("Saved extra {$number} NEW string", $savedPair['new']);
            if ($extraOld === '') {
                continue;
            }

            $extra[] = [
                'old' => $extraOld,
                'new' => $extraNew,
            ];
            $replaceMap[$extraOld] = $extraNew;
        }

        Cli::out("\nOptional: add extra replacement pairs.\n");
        Cli::out("Enter OLD string (exact), then NEW string. Leave OLD empty to finish.\n\n");

        while (true) {
            $extraOld = Cli::prompt('Extra OLD string (empty to finish)', '');
            if ($extraOld === '') {
                break;
            }

            $extraNew = Cli::prompt('Extra NEW string', '');
            $extra[] = [
                'old' => $extraOld,
                'new' => $extraNew,
            ];
            $replaceMap[$extraOld] = $extraNew;
        }

        Cli::out("\nPlanned replacements:\n");
        if ($replaceMap === []) {
            Cli::out("  (none)\n\n");
        } else {
            foreach ($replaceMap as $old => $new) {
                Cli::out("  - '{$old}' -> '{$new}'\n");
            }
            Cli::out("\n");
        }

        if (!Cli::confirm('Proceed?', true)) {
            Cli::out("Aborted.\n");
            exit(1);
        }

        return [
            'replace_map' => $replaceMap,
            'standard' => $standard,
            'extra' => $extra,
        ];
    }

    private static function help(): string
    {
        return <<<TXT
Usage:
  php scaffoldify.php [--root PATH] [--dry-run] [--no-interactive]

Options:
  --root PATH         Root folder to process (default: .)
  --dry-run           Preview project changes; may save local replacement defaults
  --no-interactive    Skip identity prompts/cache; remove demo blocks, files, and Composer dependencies

Interactive replacement defaults are stored in cache/scaffoldify-replacements.json.
Demo dependencies are removed from composer.json; run composer update afterward.

TXT;
    }

    /**
     * @param array<int, string> $argv
     */
    public static function main(array $argv): int
    {
        $args = self::parseArgs($argv);

        try {
            $canonicalRoot = self::resolveRoot($args['root']);
            $cache = new ReplacementCache($canonicalRoot);
            $saved = $args['interactive'] ? $cache->load() : null;
            $plan = self::buildReplacementPlan($args['interactive'], $saved);
            $tool = new Scaffoldify($args['root'], $args['dry_run'], $plan['replace_map']);

            if ($args['interactive']) {
                $cache->save([
                    'standard' => $plan['standard'],
                    'extra' => $plan['extra'],
                ]);
            }

            return $tool->run();
        } catch (\Throwable $exception) {
            Cli::err("Error: " . $exception->getMessage() . "\n");
            return 2;
        }
    }

    /**
     * @param array<int, string> $argv
     * @return array{root:string,dry_run:bool,interactive:bool}
     */
    private static function parseArgs(array $argv): array
    {
        $root = '.';
        $dryRun = false;
        $interactive = true;

        for ($i = 1; $i < count($argv); $i++) {
            $arg = $argv[$i];

            if ($arg === '--dry-run') {
                $dryRun = true;
                continue;
            }

            if ($arg === '--no-interactive') {
                $interactive = false;
                continue;
            }

            if ($arg === '--root') {
                $i++;
                if (!isset($argv[$i])) {
                    Cli::err("Error: --root requires a value\n");
                    exit(2);
                }
                $root = $argv[$i];
                continue;
            }

            if ($arg === '-h' || $arg === '--help') {
                Cli::out(self::help());
                exit(0);
            }

            Cli::err("Unknown argument: {$arg}\n\n" . self::help());
            exit(2);
        }

        Assert::stringNotEmpty($root, 'Root must be a non-empty string');

        return [
            'root' => $root,
            'dry_run' => $dryRun,
            'interactive' => $interactive,
        ];
    }

    private static function resolveRoot(string $root): string
    {
        $canonicalRoot = realpath($root);
        if ($canonicalRoot === false || !is_dir($canonicalRoot)) {
            throw new \InvalidArgumentException('Root directory not found: ' . $root);
        }

        return rtrim($canonicalRoot, "\\/");
    }
}

exit(App::main($argv ?? []));
