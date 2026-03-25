<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\CakePHP\Hooks\Cake\Query;

use Cake\ORM\Query\SelectQuery as CakeSelectQuery;
use Cake\ORM\Query\UpdateQuery as CakeUpdateQuery;
use Cake\ORM\Query\InsertQuery as CakeInsertQuery;
use Cake\ORM\Query\InsertQuery as CakeDeleteQuery;
use Cake\Database\Query as CakeQuery;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Instrumentation\CakePHP\Hooks\CakeHook;
use OpenTelemetry\Contrib\Instrumentation\CakePHP\Hooks\CakeHookTrait;
/**
 * @disregard P1010
*/
use function OpenTelemetry\Instrumentation\hook;
use Throwable;

class Query implements CakeHook
{
	use CakeHookTrait;

	public function instrument(): void {
		/**
		 * Hook into the execute method of the Query class, which is called for all query types (select, update, insert, delete)
		 *
		 * @disregard P1010
		*/
		hook(
			CakeQuery::class,
			'execute',
			pre: function (CakeQuery $Query, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
				// first check if query is of type select, update, insert or delete
				if (!$Query instanceof CakeSelectQuery && !$Query instanceof CakeUpdateQuery && !$Query instanceof CakeInsertQuery && !$Query instanceof CakeDeleteQuery) {
					return;
				}
				$repository = $Query->getRepository();
				$operation = match (true) {
					$Query instanceof CakeUpdateQuery => 'UPDATE',
					$Query instanceof CakeInsertQuery => 'INSERT',
					$Query instanceof CakeDeleteQuery => 'DELETE',
					default => 'SELECT',
				};
				$this->buildQuerySpan($repository->getTable(), $operation);
			},
			post: static function (CakeQuery $Query, array $params, $return, ?Throwable $exception) {
				$scope = Context::storage()->scope();
				if (!$scope) {
					return;
				}
				$scope->detach();
				$span = \OpenTelemetry\API\Trace\Span::fromContext($scope->context());
				if ($exception) {
					$span->recordException($exception);
					$span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
				}
				$span->end();
			},
		);
		/**
		 * Hook for count queries, they are cloned and executed outside of the SelectQuery
		 *
		 * @disregard P1010
		 */
		hook(
			CakeSelectQuery::class,
			'count',
			pre: function (CakeSelectQuery $Query, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
				$repository = $Query->getRepository();
				$this->buildQuerySpan($repository->getTable(), 'COUNT');
			},
			post: static function (CakeQuery $Query, array $params, $return, ?Throwable $exception) {
				$scope = Context::storage()->scope();
				if (!$scope) {
					return;
				}
				$scope->detach();
				$span = \OpenTelemetry\API\Trace\Span::fromContext($scope->context());
				if ($exception) {
					$span->recordException($exception);
					$span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
				}
				$span->end();
			},
		);
	}
}
