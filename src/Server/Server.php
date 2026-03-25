<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Instrumentation\CakePHP\Server;

use Cake\Http\Server as CakeServer;
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
use Cake\Http\MiddlewareQueue;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Cake\Core\ContainerApplicationInterface;
use Cake\Core\PluginApplicationInterface;

class Server extends CakeServer
{
	/**
	 * Run the request/response through the Application and its middleware.
	 *
	 * This will invoke the following methods:
	 *
	 * - App->bootstrap() - Perform any bootstrapping logic for your application here.
	 * - App->middleware() - Attach any application middleware here.
	 * - Trigger the 'Server.buildMiddleware' event. You can use this to modify the
	 *   from event listeners.
	 * - Run the middleware queue including the application.
	 *
	 * @param \Psr\Http\Message\ServerRequestInterface|null $request The request to use or null.
	 * @param \Cake\Http\MiddlewareQueue|null $middlewareQueue MiddlewareQueue or null.
	 * @return \Psr\Http\Message\ResponseInterface
	 * @throws \RuntimeException When the application does not make a response.
	 */
	public function run(
		?ServerRequestInterface $request = null,
		?MiddlewareQueue $middlewareQueue = null,
	): ResponseInterface {
		if ($middlewareQueue === null) {
			if ($this->app instanceof ContainerApplicationInterface) {
				$middlewareQueue = new MiddlewareQueue([], $this->app->getContainer());
			} else {
				$middlewareQueue = new MiddlewareQueue();
			}
		}

		$middleware = $this->app->middleware($middlewareQueue);
		if ($this->app instanceof PluginApplicationInterface) {
			$middleware = $this->app->pluginMiddleware($middleware);
		}

		$this->dispatchEvent('Server.buildMiddleware', ['middleware' => $middleware]);

		$response = $this->runner->run($middleware, $request, $this->app);

		if ($request instanceof ServerRequest) {
			$request->getSession()->close();
		}

		return $response;
	}
}
