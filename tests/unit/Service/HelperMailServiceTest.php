<?php

declare(strict_types=1);

namespace OCA\Helfer\Tests\Unit\Service;

use OCA\Helfer\Db\Donation;
use OCA\Helfer\Db\Event;
use OCA\Helfer\Db\Signup;
use OCA\Helfer\Service\EventService;
use OCA\Helfer\Service\HelperMailService;
use OCA\Helfer\Service\ValidationException;
use OCP\IL10N;
use OCP\Mail\Provider\Address;
use OCP\Mail\Provider\IManager;
use OCP\Mail\Provider\IMessageSend;
use OCP\Mail\Provider\IService;
use OCP\Mail\Provider\Message;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

interface SendingService extends IService, IMessageSend {
}

final class HelperMailServiceTest extends TestCase {
	private IManager&MockObject $manager;
	private SendingService&MockObject $account;
	private HelperMailService $service;
	private Event $event;
	private ?Message $sent = null;

	protected function setUp(): void {
		$signupA = new Signup();
		$signupA->setName('Anna');
		$signupA->setEmail('Anna@Example.org');
		$signupB = new Signup();
		$signupB->setName('Ben');
		$signupB->setEmail(null);
		$donation = new Donation();
		$donation->setName('Chiara');
		$donation->setEmail('chiara@example.org');

		$events = $this->createMock(EventService::class);
		$events->method('getFull')->willReturn([
			'signups' => [$signupA, $signupB],
			'donations' => [$donation],
			'event' => new Event(), 'shifts' => [], 'stations' => [], 'slots' => [], 'donationLists' => [],
		]);

		$this->account = $this->createMock(SendingService::class);
		$this->account->method('id')->willReturn('7');
		$this->account->method('capable')->willReturn(true);
		$this->account->method('getLabel')->willReturn('Verein');
		$this->account->method('getPrimaryAddress')->willReturn(new Address('vorstand@verein.example', 'Vorstand'));
		$this->account->method('initiateMessage')->willReturn(new Message());
		$this->account->method('sendMessage')->willReturnCallback(function (Message $m): void {
			$this->sent = $m;
		});

		$this->manager = $this->createMock(IManager::class);
		$this->manager->method('has')->willReturn(true);
		$this->manager->method('services')->willReturn(['mail-provider' => ['7' => $this->account]]);
		$this->manager->method('findServiceById')->willReturnCallback(
			fn (string $uid, string $sid, ?string $pid) => ($sid === '7' && $pid === 'mail-provider') ? $this->account : null,
		);

		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnArgument(0);
		$this->service = new HelperMailService($events, $this->manager, $this->createMock(LoggerInterface::class), $l);
		$this->event = new Event();
	}

	public function testListsAccounts(): void {
		$this->assertSame([[
			'providerId' => 'mail-provider', 'serviceId' => '7', 'label' => 'Verein', 'address' => 'vorstand@verein.example',
		]], $this->service->accounts('admin'));
	}

	public function testHelpersOnlyInBccAndUnknownAddressesDropped(): void {
		$count = $this->service->send($this->event, 'admin', 'mail-provider', '7',
			['anna@example.org', 'CHIARA@example.org', 'fremd@evil.example'], "Info\r\nBcc: x@evil.example", 'Hallo zusammen');
		$this->assertSame(2, $count);
		$this->assertNotNull($this->sent);
		$this->assertSame(['vorstand@verein.example'], array_map(fn ($a) => $a->getAddress(), $this->sent->getTo()));
		$this->assertSame(['anna@example.org', 'chiara@example.org'], array_map(fn ($a) => $a->getAddress(), $this->sent->getBcc()));
		$this->assertSame(['Anna', 'Chiara'], array_map(fn ($a) => $a->getLabel(), $this->sent->getBcc()));
		$this->assertSame([], $this->sent->getCc());
		$this->assertSame('Info Bcc: x@evil.example', $this->sent->getSubject());
		$this->assertSame('Hallo zusammen', $this->sent->getBodyPlain());
	}

	public function testRejectsWhenNoKnownRecipient(): void {
		$this->expectException(ValidationException::class);
		$this->service->send($this->event, 'admin', 'mail-provider', '7', ['fremd@evil.example'], 'Info', 'Text');
	}

	public function testRejectsForeignAccount(): void {
		$this->expectException(ValidationException::class);
		$this->service->send($this->event, 'admin', 'mail-provider', '99', ['anna@example.org'], 'Info', 'Text');
	}

	public function testNoMailAppMeansNoAccounts(): void {
		$manager = $this->createMock(IManager::class);
		$manager->method('has')->willReturn(false);
		$service = new HelperMailService($this->createMock(EventService::class), $manager, $this->createMock(LoggerInterface::class), $this->createMock(IL10N::class));
		$this->assertSame([], $service->accounts('admin'));
	}
}
