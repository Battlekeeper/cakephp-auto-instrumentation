<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\CakePHP\Hooks\Cake\Query;

use Cake\ORM\Query\SelectQuery as CakeSelectQuery;
use Cake\Database\Query\UpdateQuery as CakeUpdateQuery;
use Cake\Database\Query\InsertQuery as CakeInsertQuery;
use Cake\Database\Query\InsertQuery as CakeDeleteQuery;
use Cake\Database\Query as CakeQuery;
use Cake\ORM\Table as CakeTable;
use Cake\Database\Statement\Statement as CakeStatement;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Contrib\Instrumentation\CakePHP\Hooks\CakeHook;
use OpenTelemetry\Contrib\Instrumentation\CakePHP\Hooks\CakeHookTrait;
/**
 * @disregard P1010
 */
use function OpenTelemetry\Instrumentation\hook;
use Throwable;
use Cake\Log\Log;

class Query implements CakeHook
{
	use CakeHookTrait;

	public function instrument(): void
	{
		/**
		 * Hook into the _execute method of the SelectQuery class
		 *
		 * @disregard P1010
		 */
		hook(
			CakeSelectQuery::class,
			'_execute',
			pre: function (CakeSelectQuery $Query, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
				$sql = 'unknown_query';
				try {
					// Sometimes the repository cannot be accessed
					$table = $Query->getRepository()->getTable();
					// Sometimes the sql cannot be accessed because the repository cannot be accessed
					// Clean copy is used because ->sql() modifies the query object and can cause issues with the execution of the query
					$sql = $Query->cleanCopy()->sql();
				} catch (\Throwable $e) {
					$table = 'unknown_table';
					$sql = 'unknown_query';
				}

				$this->buildQuerySpan($table, 'SELECT', $sql);
			},
			post: static function (CakeSelectQuery $Query, array $params, $return, ?Throwable $exception) {
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
		 * Hook into the execute method of the Query Class
		 *
		 * @disregard P1010
		 */
		hook(
			CakeQuery::class,
			'execute',
			pre: function (CakeQuery $Query, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
				if ($Query instanceof CakeSelectQuery) {
					return;
				}
				if (!$Query instanceof CakeUpdateQuery && !$Query instanceof CakeInsertQuery && !$Query instanceof CakeDeleteQuery) {
					return;
				}

				$table = 'unknown_table';

				match (true) {
					$Query instanceof CakeUpdateQuery => $method = 'UPDATE',
					$Query instanceof CakeInsertQuery => $method = 'INSERT',
					$Query instanceof CakeDeleteQuery => $method = 'DELETE',
					default => $method = 'UNKNOWN',
				};
				$sql = $Query->sql();
				// look at the sql and get the table name after the UPDATE, INSERT INTO, or DELETE FROM
				if (preg_match('/^(UPDATE|INSERT INTO|DELETE FROM)\s+`??(\w+)`?\s+/i', $sql, $matches)) {
					$table = $matches[2];
				}

				$this->buildQuerySpan($table, $method, $sql);
			},
			post: static function (CakeQuery $Query, array $params, $return, ?Throwable $exception) {
				if ($Query instanceof CakeSelectQuery) {
					return;
				}

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
		 * Hook into the execute method of the Query Class
		 *
		 * @disregard P1010
		 */
		hook(
			CakeTable::class,
			'save',
			pre: function (CakeTable $Table, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
				// create span
				$name = $Table->getAlias();
				$this->buildQuerySpan($name, "SAVE", '', true);
			},
			post: static function (CakeTable $Table, array $params, $return, ?Throwable $exception) {
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
		 * Hook into the execute method of the Query Class
		 *
		 * @disregard P1010
		 */
		hook(
			CakeTable::class,
			'delete',
			pre: function (CakeTable $Table, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
				// create span
				$name = $Table->getAlias();
				$this->buildQuerySpan($name, "DELETE", '', true);
			},
			post: static function (CakeTable $Table, array $params, $return, ?Throwable $exception) {
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
		 * Hook into the execute method of the Query Class
		 *
		 * @disregard P1010
		 */
		hook(
			CakeTable::class,
			'dispatchEvent',
			pre: function (CakeTable $Table, array $params, string $class, string $function, ?string $filename, ?int $lineno) {
				if ($params[0] !== 'Model.beforeSave' && $params[0] !== 'Model.beforeDelete' && $params[0] !== 'Model.afterSave' && $params[0] !== 'Model.afterDelete') {
					return;
				}
				// create span
				$event = $params[0];
				$name = $Table->getAlias();
				$this->buildQuerySpan($name, $event, '', true);
			},
			post: static function (CakeTable $Table, array $params, $return, ?Throwable $exception) {
				if ($params[0] !== 'Model.beforeSave' && $params[0] !== 'Model.beforeDelete' && $params[0] !== 'Model.afterSave' && $params[0] !== 'Model.afterDelete') {
					return;
				}
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
				$sql = 'unknown_query';
				try {
					// Sometimes the repository cannot be accessed
					$table = $Query->getRepository()->getTable();
					// Sometimes the sql cannot be accessed because the repository cannot be accessed
					// Clean copy is used because ->sql() modifies the query object and can cause issues with the execution of the query
					$sql = $Query->cleanCopy()->sql();
				} catch (\Throwable $e) {
					$table = 'unknown_table';
					$sql = 'unknown_query';
				}
				$this->buildQuerySpan($table, 'COUNT', $sql);
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
