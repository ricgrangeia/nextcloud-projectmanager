<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getCode()
 * @method void setCode(string $code)
 * @method string getName()
 * @method void setName(string $name)
 * @method bool getInEstimate()
 * @method void setInEstimate(bool $inEstimate)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Module extends Entity implements \JsonSerializable {
	protected int $projectId = 0;
	protected string $code = '';
	protected string $name = '';
	protected bool $inEstimate = true;
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('projectId', Types::INTEGER);
		$this->addType('code', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('inEstimate', Types::BOOLEAN);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'code' => $this->code,
			'name' => $this->name,
			'inEstimate' => $this->inEstimate,
			'sortOrder' => $this->sortOrder,
		];
	}
}
