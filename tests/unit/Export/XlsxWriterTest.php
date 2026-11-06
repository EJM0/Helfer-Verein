<?php

declare(strict_types=1);

namespace OCA\Helfer\Tests\Unit\Export;

use OCA\Helfer\Export\Cell;
use OCA\Helfer\Export\XlsxWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class XlsxWriterTest extends TestCase {
	public function testColumnNames(): void {
		$this->assertSame('A', XlsxWriter::column(0));
		$this->assertSame('Z', XlsxWriter::column(25));
		$this->assertSame('AA', XlsxWriter::column(26));
		$this->assertSame('AZ', XlsxWriter::column(51));
	}

	public function testExcelDateUsesTimezone(): void {
		// 2027-06-13 08:00 UTC = 10:00 in Berlin (summer time)
		$ts = gmmktime(8, 0, 0, 6, 13, 2027);
		$serial = XlsxWriter::excelDate($ts, new \DateTimeZone('Europe/Berlin'));
		$this->assertEqualsWithDelta(46551 + 10 / 24, $serial, 0.0001);
	}

	public function testWritesTwoSheets(): void {
		$writer = new XlsxWriter();
		$writer->addSheet('Helfer', [['Name', 'Stunden'], ['Anna & <Ben>', new Cell(2.5, XlsxWriter::STYLE_DECIMAL)]]);
		$writer->addSheet('Einsätze/Zeiten', [['Name', 'Datum'], ['Anna', new Cell(46551, XlsxWriter::STYLE_DATE)]]);
		$file = tempnam(sys_get_temp_dir(), 'xlsx');
		$content = $writer->toString($file);
		file_put_contents($file, $content);

		$zip = new ZipArchive();
		$this->assertTrue($zip->open($file));
		$workbook = (string)$zip->getFromName('xl/workbook.xml');
		$this->assertStringContainsString('name="Helfer"', $workbook);
		// "/" is not allowed in sheet names
		$this->assertStringContainsString('name="Einsätze Zeiten"', $workbook);
		$sheet = (string)$zip->getFromName('xl/worksheets/sheet1.xml');
		$this->assertStringContainsString('Anna &amp; &lt;Ben&gt;', $sheet);
		$this->assertStringContainsString('<v>2.5</v>', $sheet);
		$this->assertNotFalse(simplexml_load_string($sheet));
		$this->assertNotFalse(simplexml_load_string($workbook));
		$zip->close();
		unlink($file);
	}

	public function testSheetWithoutFilter(): void {
		$writer = new XlsxWriter();
		$writer->addSheet('Information', [['Angabe', 'Wert'], ['Veranstaltung', 'Sommerfest']], false);
		$file = tempnam(sys_get_temp_dir(), 'xlsx');
		file_put_contents($file, $writer->toString($file));
		$zip = new ZipArchive();
		$this->assertTrue($zip->open($file));
		$this->assertStringNotContainsString('autoFilter', (string)$zip->getFromName('xl/worksheets/sheet1.xml'));
		$this->assertStringNotContainsString('definedNames', (string)$zip->getFromName('xl/workbook.xml'));
		$zip->close();
		unlink($file);
	}
}
