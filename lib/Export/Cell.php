<?php

declare(strict_types=1);

namespace OCA\Helfer\Export;

/**
 * A spreadsheet cell with a specific number format.
 */
final class Cell {
	public function __construct(
		public readonly int|float|string|null $value,
		public readonly int $style = XlsxWriter::STYLE_DEFAULT,
	) {
	}

	public function displayWidth(): int {
		return match ($this->style) {
			XlsxWriter::STYLE_DATE => 10,
			XlsxWriter::STYLE_TIME => 5,
			XlsxWriter::STYLE_DATETIME => 16,
			XlsxWriter::STYLE_DECIMAL => 6,
			default => mb_strlen((string)$this->value),
		};
	}
}
