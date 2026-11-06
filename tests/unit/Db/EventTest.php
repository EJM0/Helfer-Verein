<?php

declare(strict_types=1);

namespace OCA\Helfer\Tests\Unit\Db;

use OCA\Helfer\Db\Event;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase {
	public function testEndAndCloseDates(): void {
		$event = new Event();
		$this->assertFalse($event->isEnded(1000));
		$this->assertFalse($event->isClosed(1000));

		$event->setEndDate(2000);
		$event->setCloseDate(3000);
		$this->assertFalse($event->isEnded(1999));
		$this->assertTrue($event->isEnded(2000));
		$this->assertFalse($event->isClosed(2999));
		$this->assertTrue($event->isClosed(3000));
	}

	public function testClosedAtWins(): void {
		$event = new Event();
		$event->setClosedAt(500);
		$this->assertTrue($event->isClosed(100));
	}

	public function testQuestionsAreSanitized(): void {
		$event = new Event();
		$event->setQuestions(json_encode([
			['id' => 'a1', 'label' => 'T-Shirt', 'required' => 1],
			['label' => 'no id'],
			'garbage',
		]));
		$this->assertSame([['id' => 'a1', 'label' => 'T-Shirt', 'required' => true]], $event->getQuestionList());

		$event->setGroupOptions('{"broken"');
		$this->assertSame([], $event->getGroupOptionList());
	}
}
