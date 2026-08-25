<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method ?\DateTimeImmutable getWorkDate()
 * @method void setWorkDate(?\DateTimeImmutable $workDate)
 * @method float getHours()
 * @method void setHours(float $hours)
 */
class DayHours extends Entity implements \JsonSerializable {
	protected int $projectId = 0;
	protected ?\DateTimeImmutable $workDate = null;
	protected float $hours = 0.0;

	public function __construct() {
		$this->addType('projectId', Types::INTEGER);
		$this->addType('workDate', Types::DATE_IMMUTABLE);
		$this->addType('hours', Types::FLOAT);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'workDate' => $this->workDate?->format('Y-m-d'),
			'hours' => $this->hours,
		];
	}
}
