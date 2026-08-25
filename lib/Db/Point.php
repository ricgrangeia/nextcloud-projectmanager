<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getModuleId()
 * @method void setModuleId(int $moduleId)
 * @method string getCode()
 * @method void setCode(string $code)
 * @method string getDescription()
 * @method void setDescription(string $description)
 * @method ?float getEstimateH()
 * @method void setEstimateH(?float $estimateH)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Point extends Entity implements \JsonSerializable {
	public const STATUS_TODO = 'todo';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_PARTIAL = 'partial';
	public const STATUS_DONE = 'done';

	protected int $moduleId = 0;
	protected string $code = '';
	protected string $description = '';
	protected ?float $estimateH = null;
	protected string $status = self::STATUS_TODO;
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('moduleId', Types::INTEGER);
		$this->addType('code', Types::STRING);
		$this->addType('description', Types::TEXT);
		$this->addType('estimateH', Types::FLOAT);
		$this->addType('status', Types::STRING);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'moduleId' => $this->moduleId,
			'code' => $this->code,
			'description' => $this->description,
			'estimateH' => $this->estimateH,
			'status' => $this->status,
			'sortOrder' => $this->sortOrder,
		];
	}
}
