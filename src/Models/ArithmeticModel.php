<?php

declare(strict_types=1);

namespace Seablast\Distribution\Models;

use DateTimeImmutable;
use mysqli_result;
use Seablast\Seablast\Exceptions\DbmsException;
use Seablast\Seablast\SeablastConfiguration;
use Seablast\Seablast\SeablastModelInterface;
use Seablast\Seablast\Superglobals;
use stdClass;
use Tracy\Debugger;
use Tracy\ILogger;

/**
 * Demo arithmetic trainer backed by database persistence.
 */
class ArithmeticModel implements SeablastModelInterface
{
    use \Nette\SmartObject;

    private const TABLE = 'arithmetic_attempts';
    private const MIN_OPERAND = 1;
    private const MAX_OPERAND = 99;
    private const OPERATORS = [
        '+' => '+',
        '-' => '−',
        '*' => '×',
    ];

    /** @var SeablastConfiguration */
    private $configuration;
    /** @var Superglobals */
    private $superglobals;

    public function __construct(SeablastConfiguration $configuration, Superglobals $superglobals)
    {
        $this->configuration = $configuration;
        $this->superglobals = $superglobals;
    }

    public function knowledge(): stdClass
    {
        $viewModel = new stdClass();
        $viewModel->title = 'Arithmetic demo';

        if ($this->isPost()) {
            $viewModel->evaluation = $this->handleSubmission();
        }

        $viewModel->operation = $this->prepareNewOperation();
        $viewModel->recentAttempts = $this->fetchRecentAttempts();

        return $viewModel;
    }

    private function isPost(): bool
    {
        return strtoupper((string) ($this->superglobals->server['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    private function prepareNewOperation(): stdClass
    {
        $operandA = random_int(self::MIN_OPERAND, self::MAX_OPERAND);
        $operandB = random_int(self::MIN_OPERAND, self::MAX_OPERAND);
        $operators = array_keys(self::OPERATORS);
        $operator = (string) $operators[random_int(0, count($operators) - 1)];

        $operation = new stdClass();
        $operation->operandA = $operandA;
        $operation->operandB = $operandB;
        $operation->operator = $operator;
        $operation->operatorSymbol = self::OPERATORS[$operator];
        $operation->startedAt = (int) round(microtime(true) * 1000);

        return $operation;
    }

    private function handleSubmission(): stdClass
    {
        $post = $this->superglobals->post;

        $operandA = $this->filterOperand($post['operand_a'] ?? null);
        $operandB = $this->filterOperand($post['operand_b'] ?? null);
        $operator = $this->filterOperator($post['operator'] ?? null);
        $userResult = $this->filterUserResult($post['user_answer'] ?? null);
        $duration = $this->computeDuration($post['started_at'] ?? null);

        $evaluation = new stdClass();

        if ($operandA === null || $operandB === null || $operator === null) {
            $evaluation->message = 'Nelze vyhodnotit – data formuláře chybí nebo jsou neplatná.';
            $evaluation->isCorrect = false;
            $evaluation->hasError = true;
            return $evaluation;
        }

        $correctResult = $this->calculate($operandA, $operandB, $operator);
        $isCorrect = $userResult !== null && $userResult === $correctResult;

        $storageError = $this->storeAttempt([
            'operand_a' => $operandA,
            'operand_b' => $operandB,
            'operator' => $operator,
            'correct_result' => $correctResult,
            'user_result' => $userResult,
            'is_correct' => $isCorrect,
            'response_ms' => $duration,
        ]);

        $evaluation->operandA = $operandA;
        $evaluation->operandB = $operandB;
        $evaluation->operatorSymbol = self::OPERATORS[$operator];
        $evaluation->correctResult = $correctResult;
        $evaluation->userResult = $userResult;
        $evaluation->isCorrect = $isCorrect;
        $evaluation->responseMs = $duration;
        $evaluation->message = $isCorrect ? 'Správně! Dobrá práce.' : 'Tentokrát to nevyšlo.';
        if ($storageError !== null) {
            $evaluation->hasError = true;
            $evaluation->message .= ' (Upozornění: výsledek se nepodařilo uložit do databáze.)';
        }

        return $evaluation;
    }

    private function filterOperand($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $intValue = (int) $value;
        if ($intValue < self::MIN_OPERAND || $intValue > self::MAX_OPERAND) {
            return null;
        }
        return $intValue;
    }

    private function filterOperator($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $value = trim($value);
        return array_key_exists($value, self::OPERATORS) ? $value : null;
    }

    private function filterUserResult($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $filtered = filter_var($value, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        return is_int($filtered) ? $filtered : null;
    }

    private function computeDuration($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return null;
        }
        $startedAt = (int) $value;
        $now = (int) round(microtime(true) * 1000);
        $duration = $now - $startedAt;
        return $duration >= 0 ? $duration : null;
    }

    private function calculate(int $a, int $b, string $operator): int
    {
        switch ($operator) {
            case '+':
                return $a + $b;
            case '-':
                return $a - $b;
            case '*':
                return $a * $b;
        }

        throw new \InvalidArgumentException('Unsupported operator ' . $operator);
    }

    private function storeAttempt(array $payload): ?string
    {
        try {
            $mysqli = $this->configuration->mysqli();
        } catch (DbmsException $exception) {
            Debugger::log($exception, ILogger::ERROR);
            return $exception->getMessage();
        }

        $table = $this->qualifiedTable(self::TABLE);
        $sql = sprintf(
            'INSERT INTO %s (operand_a, operand_b, operator, correct_result, user_result, is_correct, response_ms)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            $table
        );

        try {
            $stmt = $mysqli->prepareStrict($sql);
        } catch (DbmsException $exception) {
            Debugger::log($exception, ILogger::ERROR);
            return $exception->getMessage();
        }

        $operandA = $payload['operand_a'];
        $operandB = $payload['operand_b'];
        $operator = $payload['operator'];
        $correct = $payload['correct_result'];
        $userResult = $payload['user_result'];
        $isCorrect = $payload['is_correct'] ? 1 : 0;
        $responseMs = $payload['response_ms'];

        $stmt->bind_param(
            'iisiisi',
            $operandA,
            $operandB,
            $operator,
            $correct,
            $userResult,
            $isCorrect,
            $responseMs
        );

        try {
            $stmt->execute();
        } catch (\mysqli_sql_exception $exception) {
            Debugger::log($exception, ILogger::ERROR);
            return $exception->getMessage();
        }

        return null;
    }

    private function fetchRecentAttempts(): array
    {
        try {
            $mysqli = $this->configuration->mysqli();
        } catch (DbmsException $exception) {
            Debugger::log($exception, ILogger::INFO);
            return [];
        }

        $sql = sprintf(
            'SELECT operand_a, operand_b, operator, correct_result, user_result, is_correct, response_ms, created_at
             FROM %s ORDER BY id DESC LIMIT 10',
            $this->qualifiedTable(self::TABLE)
        );

        try {
            $result = $mysqli->queryStrict($sql);
        } catch (DbmsException $exception) {
            Debugger::log($exception, ILogger::ERROR);
            return [];
        }

        $attempts = [];
        if ($result instanceof mysqli_result) {
            while ($row = $result->fetch_assoc()) {
                $attempt = new stdClass();
                $attempt->operandA = (int) $row['operand_a'];
                $attempt->operandB = (int) $row['operand_b'];
                $attempt->operatorSymbol = self::OPERATORS[$row['operator']] ?? $row['operator'];
                $attempt->correctResult = (int) $row['correct_result'];
                $attempt->userResult = isset($row['user_result']) ? (int) $row['user_result'] : null;
                $attempt->isCorrect = (bool) $row['is_correct'];
                $attempt->responseMs = isset($row['response_ms']) ? (int) $row['response_ms'] : null;
                $attempt->createdAt = new DateTimeImmutable($row['created_at']);
                $attempts[] = $attempt;
            }
            $result->free();
        }

        return $attempts;
    }

    private function qualifiedTable(string $table): string
    {
        try {
            $prefix = $this->configuration->dbmsTablePrefix();
        } catch (DbmsException $exception) {
            Debugger::log($exception, ILogger::INFO);
            $prefix = '';
        }

        return sprintf('`%s%s`', $prefix, $table);
    }
}
