#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * scaffoldify.php
 *
 * Cross-platform PHP CLI script that:
 *  1) asks for replacement values for the current seablast-dist identity
 *  2) recursively replaces occurrences in files under --root
 *  3) removes blocks marked by SCAFFOLDIFY:REMOVE-START/END tokens
 *  4) removes boilerplate-only files from the built-in inventory
 *
 * Compatible with PHP 7.2+ and 8.1+.
 */

const MARKER_LABEL = 'dist-demo';
const START_TOKEN = 'SCAFFOLDIFY:REMOVE-START ' . MARKER_LABEL;
const END_TOKEN = 'SCAFFOLDIFY:REMOVE-END ' . MARKER_LABEL;

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
    'views/item.latte',
    'views/mirror.latte',
    'conf/db/migrations/20250803081249_first_blog_posts.php',
    'conf/db/migrations/20260222090000_create_arithmetic_attempts.php',
];

final class Assert
{
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
}

final class Cli
{
    public static function out(string $message): void
    {
        fwrite(STDOUT, $message);
    }

    public static function err(string $message): void
    {
        fwrite(STDERR, $message);
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

final class Scaffoldify
{
    /** @var bool */
    private $dryRun;

    /** @var string */
    private $root;

    /** @var string */
    private $canonicalRoot;

    /** @var string */
    private $scriptPath;

    /** @var array<string,string> */
    private $replaceMap;

    /** @var array<int, array{relative:string, absolute:string}> */
    private $removeTargets;

    /** @var array<string, bool> */
    private $removeTargetLookup;

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

        $this->root = rtrim($root, "\\/");
        $this->canonicalRoot = rtrim($canonicalRoot, "\\/");
        $this->dryRun = $dryRun;
        $this->scriptPath = $scriptPath;
        $this->replaceMap = $this->sortReplaceMapByKeyLength($replaceMap);
        $this->removeTargets = [];
        $this->removeTargetLookup = [];

        $this->prepareRemoveTargets();
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
        $skippedSelf = 0;
        $skippedScheduledDeletes = 0;

        foreach (FileWalker::yieldFiles($this->canonicalRoot) as $filePath) {
            if ($this->samePath($filePath, $this->scriptPath)) {
                $skippedSelf++;
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
                    continue;
                }
                Cli::out("edited: {$filePath}\n");
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
        Cli::out("Script file skipped: {$skippedSelf}\n");
        Cli::out("Delete-target files skipped during edit pass: {$skippedScheduledDeletes}\n\n");

        $deleteSummary = $this->deleteRemoveTargets();

        Cli::out("\n== Delete summary ==\n");
        Cli::out("Delete targets checked: {$deleteSummary['checked']}\n");
        Cli::out("{$deleteLabel}: {$deleteSummary['deleted']}\n");
        Cli::out("Delete targets missing: {$deleteSummary['missing']}\n");
        Cli::out("Delete targets rejected: {$deleteSummary['rejected']}\n");
        Cli::out("\nDone.\n");

        return 0;
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

    private function applyReplacements(string $content): string
    {
        if ($this->replaceMap === []) {
            return $content;
        }

        return str_replace(array_keys($this->replaceMap), array_values($this->replaceMap), $content);
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

    private function isProbablyBinary(string $filePath): bool
    {
        $fh = @fopen($filePath, 'rb');
        if ($fh === false) {
            return false;
        }

        $chunk = @fread($fh, 8192);
        @fclose($fh);

        if ($chunk === false || $chunk === '') {
            return false;
        }

        return strpos($chunk, "\0") !== false;
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

    private function samePath(string $a, string $b): bool
    {
        return $this->normalizeComparablePath($a) === $this->normalizeComparablePath($b);
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
}

final class App
{
    /**
     * @param array<int, string> $argv
     */
    public static function main(array $argv): int
    {
        $args = self::parseArgs($argv);
        $replaceMap = self::buildReplaceMap($args['interactive']);

        try {
            $tool = new Scaffoldify($args['root'], $args['dry_run'], $replaceMap);
        } catch (\Throwable $exception) {
            Cli::err("Error: " . $exception->getMessage() . "\n");
            return 2;
        }

        return $tool->run();
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

    private static function help(): string
    {
        return <<<TXT
Usage:
  php scaffoldify.php [--root PATH] [--dry-run] [--no-interactive]

Options:
  --root PATH         Root folder to process (default: .)
  --dry-run           Show what would change, do not write/delete anything
  --no-interactive    Do not prompt; skip identity replacements

TXT;
    }

    /**
     * @return array<string,string>
     */
    private static function buildReplaceMap(bool $interactive): array
    {
        $oldRepoUrl = 'https://github.com/WorkOfStan/seablast-dist';
        $oldIssuesUrl = 'https://github.com/WorkOfStan/seablast-dist/issues';
        $oldComposerPackage = 'seablast/dist';
        $oldNamespace = 'Seablast\\Distribution';
        $oldRepoSlug = 'seablast-dist';
        $oldHomeLabel = 'HOME DIST';
        $oldAuthorName = 'Stanislav Rejthar';
        $oldAuthorEmail = 'rejthar@stanislavrejthar.com';

        if (!$interactive) {
            return [];
        }

        Cli::out("== Step 0: Replacements ==\n");
        Cli::out("Enter NEW values. The script replaces the current seablast-dist identity strings.\n\n");

        $newRepoUrl = Cli::prompt('1) New Git repository URL', $oldRepoUrl);
        $issuesDefault = ($newRepoUrl !== $oldRepoUrl)
            ? rtrim($newRepoUrl, '/') . '/issues'
            : $oldIssuesUrl;
        $newIssuesUrl = Cli::prompt('2) New issue tracker URL', $issuesDefault);
        $newComposerPackage = Cli::prompt('3) New Composer package name', $oldComposerPackage);
        $newNamespace = Cli::prompt('4) New PHP namespace (use backslashes)', $oldNamespace);
        $newRepoSlug = Cli::prompt('5) New repository slug / short project id', $oldRepoSlug);
        $newHomeLabel = Cli::prompt('6) New nav home label', $oldHomeLabel);
        $newAuthorName = Cli::prompt('7) New author name', $oldAuthorName);
        $newAuthorEmail = Cli::prompt('8) New author/support email', $oldAuthorEmail);

        $replaceMap = [];

        self::addReplaceIfChanged($replaceMap, $oldRepoUrl, $newRepoUrl);
        self::addReplaceIfChanged($replaceMap, $oldIssuesUrl, $newIssuesUrl);
        self::addReplaceIfChanged($replaceMap, $oldComposerPackage, $newComposerPackage);
        self::addReplaceIfChanged($replaceMap, $oldNamespace, $newNamespace);
        self::addReplaceIfChanged($replaceMap, $oldRepoSlug, $newRepoSlug);
        self::addReplaceIfChanged($replaceMap, $oldHomeLabel, $newHomeLabel);
        self::addReplaceIfChanged($replaceMap, $oldAuthorName, $newAuthorName);
        self::addReplaceIfChanged($replaceMap, $oldAuthorEmail, $newAuthorEmail);

        Cli::out("\nOptional: add extra replacement pairs.\n");
        Cli::out("Enter OLD string (exact), then NEW string. Leave OLD empty to finish.\n\n");

        while (true) {
            $extraOld = Cli::prompt('Extra OLD string (empty to finish)', '');
            if ($extraOld === '') {
                break;
            }

            $extraNew = Cli::prompt('Extra NEW string', '');
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

        return $replaceMap;
    }

    /**
     * @param array<string,string> $replaceMap
     */
    private static function addReplaceIfChanged(array &$replaceMap, string $old, string $new): void
    {
        if ($new !== $old) {
            $replaceMap[$old] = $new;
        }
    }
}

exit(App::main($argv ?? []));
