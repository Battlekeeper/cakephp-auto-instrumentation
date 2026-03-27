<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\CakePHP\Hooks;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\Attributes\CodeAttributes;
use OpenTelemetry\SemConv\Attributes\UrlAttributes;
use OpenTelemetry\SemConv\Attributes\HttpAttributes;
use OpenTelemetry\SemConv\Attributes\UserAgentAttributes;
use OpenTelemetry\SemConv\Attributes\ServerAttributes;
use OpenTelemetry\SemConv\Attributes\DbAttributes;


use Psr\Http\Message\ServerRequestInterface;

trait CakeHookTrait
{
	private static CakeHook $instance;

	private bool $isRoot;

	protected function __construct(
		protected CachedInstrumentation $instrumentation,
	) {
	}

	abstract public function instrument(): void;

	public static function hook(CachedInstrumentation $instrumentation): CakeHook {
		if (!isset(self::$instance)) {
			/**
			 * @disregard P1006
			*/
			self::$instance = new self($instrumentation);
			self::$instance->instrument();
		}

		return self::$instance;
	}

	/**
	 * @param ServerRequestInterface|null $request
	 * @param string $class
	 * @param string $function
	 * @param string|null $filename
	 * @param int|null $lineno
	 * @return mixed
	 * @psalm-suppress ArgumentTypeCoercion
	 */
	protected function buildSpan(?ServerRequestInterface $request, string $class, string $function, ?string $filename, ?int $lineno, ?string $overrideSpanName = null): mixed {
		$root = $request
			? $request->getAttribute(SpanInterface::class)
			: \OpenTelemetry\API\Trace\Span::getCurrent();

		$spanName = $root
			? sprintf('%s::%s', $class, $function)
			: sprintf('%s', $request?->getUri()->getPath() ?? 'unknown');
		if ($overrideSpanName) {
			$spanName = $overrideSpanName;
		}

		$builder = $this->instrumentation->tracer()->spanBuilder($spanName)
			->setSpanKind(SpanKind::KIND_SERVER)
			->setAttribute(CodeAttributes::CODE_FUNCTION_NAME, sprintf('%s::%s', $class, $function))
			->setAttribute(CodeAttributes::CODE_FILE_PATH, $filename)
			->setAttribute(CodeAttributes::CODE_LINE_NUMBER, $lineno);
		$parent = Context::getCurrent();
		if (!$root && $request) {
			$this->isRoot = true;
			//create http root span
			$parent = Globals::propagator()->extract($request->getHeaders());
			$span = $builder
				->setParent($parent)
				->setAttribute(UrlAttributes::URL_FULL, $request->getUri()->__toString())
				->setAttribute(HttpAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
				->setAttribute('http.request.body.size', $request->getHeaderLine('Content-Length'))
				->setAttribute(UrlAttributes::URL_SCHEME, $request->getUri()->getScheme())
				->setAttribute(UrlAttributes::URL_PATH, $request->getUri()->getPath())
				->setAttribute(UserAgentAttributes::USER_AGENT_ORIGINAL, $request->getHeaderLine('User-Agent'))
				->setAttribute(ServerAttributes::SERVER_ADDRESS, $request->getUri()->getHost())
				->setAttribute(ServerAttributes::SERVER_PORT, $request->getUri()->getPort())
				->startSpan();
			$request = $request->withAttribute(SpanInterface::class, $span);
		} else {
			$this->isRoot = false;
			$span = $builder->setSpanKind(SpanKind::KIND_INTERNAL)->startSpan();
		}
		Context::storage()->attach($span->storeInContext($parent));

		return $request;
	}

	protected function buildQuerySpan(string $repository, string $operation, string $query, bool $isInternal = false): SpanInterface {
		$spanName = $repository . '::' . $operation;
		$builder = $this->instrumentation->tracer()->spanBuilder($spanName);
		$span = $builder->setSpanKind($isInternal ? SpanKind::KIND_INTERNAL : SpanKind::KIND_CLIENT)
				// this will be service.peer.name in the future but for now peer.service is more widely recognized
				->setAttribute('peer.service', 'database')
				->setAttribute(DbAttributes::DB_QUERY_TEXT, $query)
				->startSpan();
		$parent = Context::getCurrent();
		Context::storage()->attach($span->storeInContext($parent));
		return $span;
	}

	protected function isRoot(): bool {
		return $this->isRoot;
	}
}
