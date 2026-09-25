<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getName()
 * @method void setName(string $name)
 * @method ?\DateTimeImmutable getTargetDate()
 * @method void setTargetDate(?\DateTimeImmutable $targetDate)
 * @method ?\DateTimeImmutable getReachedDate()
 * @method void setReachedDate(?\DateTimeImmutable $reachedDate)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Milestone extends Entity implements \JsonSerializable {
	protected int $projectId = 0;
	protected string $name = '';
	protected ?\DateTimeImmutable $targetDate = null;
	protected ?\DateTimeImmutable $reachedDate = null;
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('projectId', Types::INTEGER);
		$this->addType('name', Types::STRING);
		$this->addType('targetDate', Types::DATE_IMMUTABLE);
		$this->addType('reachedDate', Types::DATE_IMMUTABLE);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'name' => $this->name,
			'targetDate' => $this->targetDate?->format('Y-m-d'),
			'reachedDate' => $this->reachedDate?->format('Y-m-d'),
			'sortOrder' => $this->sortOrder,
		];
	}
}
