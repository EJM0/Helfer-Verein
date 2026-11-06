<?php

declare(strict_types=1);

namespace OCA\Helfer\Controller;

use Closure;
use OCA\Helfer\Service\NotFoundException;
use OCA\Helfer\Service\ValidationException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;

trait Errors {
	/** @param Closure(): mixed $callback */
	protected function handle(Closure $callback): JSONResponse {
		try {
			$result = $callback();
			return $result instanceof JSONResponse ? $result : new JSONResponse($result);
		} catch (NotFoundException) {
			return new JSONResponse(['message' => 'Not found'], Http::STATUS_NOT_FOUND);
		} catch (ValidationException $e) {
			return new JSONResponse(['message' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
