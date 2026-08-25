<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getPointId()
 * @method void setPointId(int $pointId)
 * @method string getDescription()
 * @method void setDescription(string $description)
 * @method ?\DateTimeImmutable getWorkDate()
 * @method void setWorkDate(?\DateTimeImmutable $workDate)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Leaf extends Entity implements \JsonSerializable {
	protected int $pointId = 0;
	protected string $description = '';
	protected ?\DateTimeImmutable $workDate = null;
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('pointId', Types::INTEGER);
		$this->addType('description', Types::TEXT);
		$this->addType('workDate', Types::DATE_IMMUTABLE);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'pointId' => $this->pointId,
			'description' => $this->description,
			'workDate' => $this->workDate?->format('Y-m-d'),
			'sortOrder' => $this->sortOrder,
		];
	}
}
