<?php

declare(strict_types=1);

namespace OCA\ProjectManager\Db;

use OCP\AppFramework\Db\Entity;
use OCP\DB\Types;

/**
 * @method int getProjectId()
 * @method void setProjectId(int $projectId)
 * @method string getSection()
 * @method void setSection(string $section)
 * @method string getName()
 * @method void setName(string $name)
 * @method string getPointRef()
 * @method void setPointRef(string $pointRef)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method ?string getBusinessValue()
 * @method void setBusinessValue(?string $businessValue)
 * @method ?string getExternalPending()
 * @method void setExternalPending(?string $externalPending)
 * @method int getSortOrder()
 * @method void setSortOrder(int $sortOrder)
 */
class Feature extends Entity implements \JsonSerializable {
	public const STATUS_NOT_STARTED = 'not_started';
	public const STATUS_IN_PROGRESS = 'in_progress';
	public const STATUS_DONE = 'done';

	protected int $projectId = 0;
	protected string $section = '';
	protected string $name = '';
	protected string $pointRef = '';
	protected string $status = self::STATUS_NOT_STARTED;
	protected ?string $businessValue = '';
	protected ?string $externalPending = '';
	protected int $sortOrder = 0;

	public function __construct() {
		$this->addType('projectId', Types::INTEGER);
		$this->addType('section', Types::STRING);
		$this->addType('name', Types::STRING);
		$this->addType('pointRef', Types::STRING);
		$this->addType('status', Types::STRING);
		$this->addType('businessValue', Types::TEXT);
		$this->addType('externalPending', Types::TEXT);
		$this->addType('sortOrder', Types::INTEGER);
	}

	public function jsonSerialize(): array {
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'section' => $this->section,
			'name' => $this->name,
			'pointRef' => $this->pointRef,
			'status' => $this->status,
			'businessValue' => $this->businessValue ?? '',
			'externalPending' => $this->externalPending ?? '',
			'sortOrder' => $this->sortOrder,
		];
	}
}
