<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getArea()
 * @method void setArea(string $area)
 * @method string getProfile()
 * @method void setProfile(string $profile)
 * @method string getScenario()
 * @method void setScenario(string $scenario)
 * @method ?string getExpected()
 * @method void setExpected(?string $expected)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method ?\DateTimeImmutable getTestDate()
 * @method void setTestDate(?\DateTimeImmutable $testDate)
 * @method ?string getNotes()
 * @method void setNotes(?string $notes)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class TestEntry extends Entity implements \JsonSerializable {
	public const STATUS_TO_TEST = 'to_test';
	public const STATUS_PASSED = 'passed';
	public const STATUS_FAILED = 'failed';

	protected int $projectId = 0;
	protected string $area = '';
	protected string $profile = '';
	protected string $scenario = '';
	protected ?string $expected = '';
	protected string $status = self::STATUS_TO_TEST;
	protected ?\DateTimeImmutable $testDate = null;
	protected ?string $notes = '';
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('projectId', Types::INTEGER);
		$this->addType('area', Types::STRING);
		$this->addType('profile', Types::STRING);
		$this->addType('scenario', Types::TEXT);
		$this->addType('expected', Types::TEXT);
		$this->addType('status', Types::STRING);
		$this->addType('testDate', Types::DATE_IMMUTABLE);
		$this->addType('notes', Types::TEXT);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'area' => $this->area,
			'profile' => $this->profile,
			'scenario' => $this->scenario,
			'expected' => $this->expected ?? '',
			'status' => $this->status,
			'testDate' => $this->testDate?->format('Y-m-d'),
			'notes' => $this->notes ?? '',
			'sortOrder' => $this->sortOrder,
		];
	}
}
