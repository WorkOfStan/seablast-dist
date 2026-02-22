<?php

declare(strict_types=1);

use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Migration\AbstractMigration;

final class CreateArithmeticAttempts extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('arithmetic_attempts');
        $table
            ->addColumn('operand_a', 'integer', ['signed' => false, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('operand_b', 'integer', ['signed' => false, 'limit' => MysqlAdapter::INT_TINY])
            ->addColumn('operator', 'string', ['limit' => 1])
            ->addColumn('correct_result', 'integer', ['limit' => MysqlAdapter::INT_REGULAR])
            ->addColumn('user_result', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'null' => true])
            ->addColumn('is_correct', 'boolean', ['default' => 0])
            ->addColumn('response_ms', 'integer', ['limit' => MysqlAdapter::INT_REGULAR, 'null' => true,
                'comment' => 'Time delta between render and submit in ms'])
            ->addColumn('created_at', 'timestamp', ['default' => 'CURRENT_TIMESTAMP'])
            ->addIndex(['created_at'])
            ->addIndex(['is_correct'])
            ->create();
    }
}
