<?php

declare(strict_types=1);

namespace OCA\Helfer\Export;

use RuntimeException;
use ZipArchive;

/**
 * Minimal writer for .xlsx workbooks with several sheets.
 *
 * Cells are strings, ints/floats or a Cell describing a styled value.
 */
class XlsxWriter {
	public const STYLE_DEFAULT = 0;
	public const STYLE_HEADER = 1;
	public const STYLE_DATE = 2;
	public const STYLE_TIME = 3;
	public const STYLE_DECIMAL = 4;
	public const STYLE_DATETIME = 5;

	/** @var list<array{name: string, rows: list<list<mixed>>}> */
	private array $sheets = [];

	/** @param list<list<mixed>> $rows first row is the header */
	public function addSheet(string $name, array $rows): void {
		$name = trim(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $name));
		$name = mb_substr($name === '' ? 'Sheet' . (count($this->sheets) + 1) : $name, 0, 31);
		$this->sheets[] = ['name' => $name, 'rows' => $rows];
	}

	/** Convert a unix timestamp to an Excel serial date in the given time zone. */
	public static function excelDate(int $timestamp, \DateTimeZone $tz): float {
		$offset = $tz->getOffset((new \DateTimeImmutable('@' . $timestamp)));
		return ($timestamp + $offset) / 86400 + 25569;
	}

	public function toString(string $tempFile): string {
		$zip = new ZipArchive();
		if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
			throw new RuntimeException('Could not create xlsx file');
		}
		$zip->addFromString('[Content_Types].xml', $this->contentTypes());
		$zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
			. '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
			. '</Relationships>');
		$zip->addFromString('xl/workbook.xml', $this->workbook());
		$zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
		$zip->addFromString('xl/styles.xml', $this->styles());
		foreach ($this->sheets as $i => $sheet) {
			$zip->addFromString('xl/worksheets/sheet' . ($i + 1) . '.xml', $this->sheet($sheet['rows']));
		}
		$zip->close();
		$content = file_get_contents($tempFile);
		if ($content === false) {
			throw new RuntimeException('Could not read xlsx file');
		}
		return $content;
	}

	private function contentTypes(): string {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
			. '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
			. '<Default Extension="xml" ContentType="application/xml"/>'
			. '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
			. '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
		foreach ($this->sheets as $i => $_) {
			$xml .= '<Override PartName="/xl/worksheets/sheet' . ($i + 1) . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
		}
		return $xml . '</Types>';
	}

	private function workbook(): string {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheets>';
		foreach ($this->sheets as $i => $sheet) {
			$xml .= '<sheet name="' . $this->esc($sheet['name']) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
		}
		$xml .= '</sheets><definedNames>';
		foreach ($this->sheets as $i => $sheet) {
			$cols = max(1, ...array_map('count', $sheet['rows'] ?: [[]]));
			$rows = max(1, count($sheet['rows']));
			$xml .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">'
				. $this->esc("'" . str_replace("'", "''", $sheet['name']) . "'!\$A\$1:\$" . self::column($cols - 1) . '$' . $rows)
				. '</definedName>';
		}
		return $xml . '</definedNames></workbook>';
	}

	private function workbookRels(): string {
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
		foreach ($this->sheets as $i => $_) {
			$xml .= '<Relationship Id="rId' . ($i + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . ($i + 1) . '.xml"/>';
		}
		$n = count($this->sheets) + 1;
		$xml .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
		return $xml . '</Relationships>';
	}

	private function styles(): string {
		return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
			. '<numFmts count="4">'
			. '<numFmt numFmtId="164" formatCode="DD.MM.YYYY"/>'
			. '<numFmt numFmtId="165" formatCode="HH:MM"/>'
			. '<numFmt numFmtId="166" formatCode="0.00"/>'
			. '<numFmt numFmtId="167" formatCode="DD.MM.YYYY HH:MM"/>'
			. '</numFmts>'
			. '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
			. '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
			. '<fill><patternFill patternType="solid"><fgColor rgb="FFDDEBF7"/><bgColor indexed="64"/></patternFill></fill></fills>'
			. '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
			. '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
			. '<cellXfs count="6">'
			. '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
			. '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
			. '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
			. '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
			. '<xf numFmtId="166" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
			. '<xf numFmtId="167" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
			. '</cellXfs>'
			. '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
			. '</styleSheet>';
	}

	/** @param list<list<mixed>> $rows */
	private function sheet(array $rows): string {
		$colCount = 0;
		$widths = [];
		foreach ($rows as $row) {
			$colCount = max($colCount, count($row));
			foreach (array_values($row) as $c => $cell) {
				$len = $cell instanceof Cell ? $cell->displayWidth() : mb_strlen((string)$cell);
				$widths[$c] = max($widths[$c] ?? 0, min(60, $len));
			}
		}
		$xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
			. '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
			. '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
		if ($colCount > 0) {
			$xml .= '<cols>';
			for ($c = 0; $c < $colCount; $c++) {
				$xml .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . (max(8, ($widths[$c] ?? 8) + 2)) . '" customWidth="1"/>';
			}
			$xml .= '</cols>';
		}
		$xml .= '<sheetData>';
		foreach ($rows as $r => $row) {
			$xml .= '<row r="' . ($r + 1) . '">';
			foreach (array_values($row) as $c => $cell) {
				$ref = self::column($c) . ($r + 1);
				$style = $r === 0 ? self::STYLE_HEADER : self::STYLE_DEFAULT;
				$value = $cell;
				if ($cell instanceof Cell) {
					$style = $cell->style;
					$value = $cell->value;
				}
				if ($value === null || $value === '') {
					$xml .= '<c r="' . $ref . '"' . ($style ? ' s="' . $style . '"' : '') . '/>';
				} elseif (is_int($value) || is_float($value)) {
					$xml .= '<c r="' . $ref . '"' . ($style ? ' s="' . $style . '"' : '') . '><v>' . $value . '</v></c>';
				} else {
					$xml .= '<c r="' . $ref . '" t="inlineStr"' . ($style ? ' s="' . $style . '"' : '') . '><is><t xml:space="preserve">'
						. $this->esc((string)$value) . '</t></is></c>';
				}
			}
			$xml .= '</row>';
		}
		$xml .= '</sheetData>';
		if ($colCount > 0 && count($rows) > 0) {
			$xml .= '<autoFilter ref="A1:' . self::column($colCount - 1) . count($rows) . '"/>';
		}
		return $xml . '</worksheet>';
	}

	public static function column(int $index): string {
		$name = '';
		$index++;
		while ($index > 0) {
			$mod = ($index - 1) % 26;
			$name = chr(65 + $mod) . $name;
			$index = intdiv($index - 1, 26);
		}
		return $name;
	}

	private function esc(string $value): string {
		// Strip characters that are invalid in XML 1.0.
		$value = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $value) ?? '';
		return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
	}
}
